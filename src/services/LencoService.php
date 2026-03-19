<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/services/LencoService.php
 *
 * Thin wrapper around the Lenco Collections API.
 * No knowledge of sessions, orders, or HTTP responses.
 */

declare(strict_types=1);

require_once __DIR__ . '/LencoApiException.php';

class LencoService
{
    private const BASE_URL_V2    = 'https://api.lenco.co/access/v2';
    private const BASE_URL_V1    = 'https://api.lenco.co/access/v1';
    private const MAX_RETRIES    = 3;
    private const TIMEOUT_SEC    = 30;
    private const CONNECT_SEC    = 10;

    private string $apiKey;
    private string $webhookSecret;

    public function __construct()
    {
        $this->apiKey        = getenv('LENCO_API_SECRET_KEY') ?: '';
        $this->webhookSecret = getenv('LENCO_WEBHOOK_SECRET') ?: '';
    }

    /** True if Lenco API is configured (key set). When false, payment methods will throw. */
    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    // ================================================================
    // Public API
    // ================================================================

    /**
     * Initiate a mobile-money collection.
     *
     * @param  array  $order     Must have: orderNumber, totals.total, currency, country
     * @param  string $phone     E.g. +260971234567
     * @param  string $operator  'airtel' | 'mtn'
     */
    public function initiateMobileMoneyPayment(array $order, string $phone, string $operator): array
    {
        $reference = $this->generateReference($order['orderNumber']);

        $payload = [
            'phone'       => $phone,
            'operator'    => strtolower($operator),
            'amount'      => (float) $order['totals']['total'],
            'currency'    => $order['currency'] ?? 'ZMW',
            'reference'   => $reference,
            'country'     => $order['country']  ?? 'ZM',
            'description' => 'LFS order ' . ($order['orderNumber'] ?? ''),
        ];

        $response   = $this->request('POST', self::BASE_URL_V2 . '/collections/mobile-money', $payload);
        $collection = $response['data'] ?? $response;

        return $this->normalise($collection, $reference);
    }

    /**
     * Verify payment status.
     *
     * @param  string $identifier  Your reference or Lenco collection ID
     * @param  bool   $byReference Use /collections/status/:ref (true) or /collections/:id (false)
     */
    public function verifyPayment(string $identifier, bool $byReference = true): array
    {
        $path = $byReference
            ? self::BASE_URL_V2 . '/collections/status/' . urlencode($identifier)
            : self::BASE_URL_V2 . '/collections/'        . urlencode($identifier);

        try {
            $response   = $this->request('GET', $path);
            $collection = $response['data'] ?? $response;
            return $this->normalise($collection);
        } catch (LencoApiException $e) {
            if ($e->getHttpStatus() === 404 || stripos($e->getMessage(), 'not found') !== false) {
                return ['status' => 'pending', 'internalStatus' => 'pending', 'found' => false];
            }
            throw $e;
        }
    }

    /**
     * Verify HMAC-SHA256 webhook signature.
     * Returns true if valid, false if invalid or secret not configured.
     */
    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        if ($this->webhookSecret === '') {
            // In dev with no secret configured, allow through but log
            error_log('[LencoService] LENCO_WEBHOOK_SECRET not set — skipping signature check');
            return getenv('APP_ENV') !== 'production';
        }

        $signature  = '';
        $candidates = ['x-lenco-signature', 'x-signature', 'signature', 'authorization'];
        foreach ($candidates as $name) {
            if (!empty($headers[$name])) {
                $signature = $headers[$name];
                break;
            }
        }

        if ($signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $this->webhookSecret);
        $received = ltrim(preg_replace('/^sha256=/i', '', trim($signature)));

        // Constant-time compare with padding to avoid length-based timing leaks
        $a = str_pad($received, strlen($expected), '0');
        $b = str_pad($expected, strlen($a),         '0');
        return hash_equals($b, $a) && strlen($received) === strlen($expected);
    }

    /**
     * Parse a raw webhook payload into a normalised array.
     */
    public function parseWebhookPayload(array $raw): array
    {
        $data = $raw['data'] ?? $raw;
        return [
            'transactionId'  => $data['id']            ?? $data['transactionId']  ?? null,
            'reference'      => $data['reference']      ?? null,
            'lencoReference' => $data['lencoReference'] ?? null,
            'orderNumber'    => $data['orderNumber']    ?? $data['metadata']['orderNumber'] ?? null,
            'status'         => $data['status']         ?? null,
            'amount'         => $data['amount']         ?? null,
            'currency'       => $data['currency']       ?? 'ZMW',
            'completedAt'    => $data['completedAt']    ?? null,
            'failedAt'       => isset($data['reasonForFailure']) ? date('c') : null,
            'failureReason'  => $data['reasonForFailure'] ?? $data['failureReason'] ?? null,
        ];
    }

    /**
     * Map Lenco's status string to your internal status.
     */
    public function mapLencoStatus(string $lencoStatus): string
    {
        return match(strtolower($lencoStatus)) {
            'successful', 'success', 'completed' => 'completed',
            'failed'                              => 'failed',
            'cancelled', 'expired'               => 'cancelled',
            'processing'                          => 'processing',
            default                               => 'pending',
        };
    }

    /**
     * Generate a unique payment reference.
     * Format: LFS-{orderNumber}-{msTimestamp}-{8hexRandom}
     */
    public function generateReference(string $orderNumber): string
    {
        $ts   = (int)(microtime(true) * 1000);
        $rand = bin2hex(random_bytes(4));
        return strtoupper("LFS-{$orderNumber}-{$ts}-{$rand}");
    }

    // ================================================================
    // Private helpers
    // ================================================================

    private function request(string $method, string $url, array $body = [], int $attempt = 0): array
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('LENCO_API_SECRET_KEY environment variable is not set. Set it in public/index.php (or your env) to enable payments.');
        }

        $ch = curl_init();

        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SEC,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_SEC,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/json',
            ],
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_THROW_ON_ERROR);
        }

        curl_setopt_array($ch, $opts);

        $raw      = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            $retryable = true;
            if ($attempt < self::MAX_RETRIES) {
                usleep($this->retryDelay($attempt) * 1000);
                return $this->request($method, $url, $body, $attempt + 1);
            }
            throw new LencoApiException('cURL error: ' . $curlErr, 0, $retryable);
        }

        $decoded = json_decode($raw, true);
        if ($decoded === null) {
            throw new LencoApiException('Invalid JSON response from Lenco', $httpCode, false);
        }

        if (isset($decoded['status']) && $decoded['status'] === false) {
            throw new LencoApiException($decoded['message'] ?? 'Lenco API error', $httpCode, false, $decoded);
        }

        if ($httpCode >= 400) {
            $retryable = $httpCode >= 500 || in_array($httpCode, [408, 429]);
            if ($retryable && $attempt < self::MAX_RETRIES) {
                usleep($this->retryDelay($attempt) * 1000);
                return $this->request($method, $url, $body, $attempt + 1);
            }
            throw new LencoApiException(
                $decoded['message'] ?? 'Lenco API error',
                $httpCode,
                $retryable,
                $decoded
            );
        }

        return $decoded;
    }

    private function normalise(array $col, ?string $fallbackRef = null): array
    {
        $status = $col['status'] ?? 'pending';
        return [
            'transactionId'       => $col['id']             ?? $col['transactionId'] ?? null,
            'lencoReference'      => $col['lencoReference']  ?? null,
            'reference'           => $col['reference']       ?? $fallbackRef,
            'status'              => $status,
            'internalStatus'      => $this->mapLencoStatus($status),
            'amount'              => $col['amount']           ?? null,
            'currency'            => $col['currency']         ?? 'ZMW',
            'paymentInstructions' => $col['paymentInstructions'] ?? null,
            'paymentUrl'          => $col['paymentUrl']       ?? null,
            'expiresAt'           => $col['expiresAt']        ?? null,
            'failureReason'       => $col['reasonForFailure'] ?? $col['failureReason'] ?? null,
            'rawResponse'         => $col,
        ];
    }

    private function retryDelay(int $attempt): int
    {
        $base   = (int) min(1000 * (2 ** $attempt), 10000);
        $jitter = (int) ($base * 0.3 * (mt_rand() / mt_getrandmax()));
        return $base + $jitter;
    }
}
