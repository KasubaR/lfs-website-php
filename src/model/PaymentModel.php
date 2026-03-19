<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/models/PaymentModel.php
 */

declare(strict_types=1);

class PaymentModel
{
    public const TERMINAL_STATUSES = ['completed', 'failed', 'cancelled', 'refunded'];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = $this->connect();
    }

    public function create(array $data): int
    {
        $now  = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(<<<SQL
            INSERT INTO payments (
                order_number, payment_method, amount, currency, status,
                customer_name, customer_email, customer_phone,
                lenco_transaction_id, lenco_reference, lenco_provider,
                lenco_status, lenco_response, transaction_id,
                payment_instructions, expires_at, metadata,
                created_at, updated_at
            ) VALUES (
                :order_number, :payment_method, :amount, :currency, :status,
                :customer_name, :customer_email, :customer_phone,
                :lenco_transaction_id, :lenco_reference, :lenco_provider,
                :lenco_status, :lenco_response, :transaction_id,
                :payment_instructions, :expires_at, :metadata,
                :created_at, :updated_at
            )
        SQL);

        $ci = $data['customerInfo'] ?? [];
        $stmt->execute([
            ':order_number'         => $data['orderNumber'],
            ':payment_method'       => $data['paymentMethod'],
            ':amount'               => $data['amount'],
            ':currency'             => $data['currency']             ?? 'ZMW',
            ':status'               => $data['status']               ?? 'pending',
            ':customer_name'        => $ci['name']                   ?? '',
            ':customer_email'       => strtolower($ci['email']       ?? ''),
            ':customer_phone'       => $ci['phone']                  ?? '',
            ':lenco_transaction_id' => $data['lencoTransactionId']   ?? null,
            ':lenco_reference'      => $data['lencoReference']       ?? null,
            ':lenco_provider'       => $data['lencoProvider']        ?? null,
            ':lenco_status'         => $data['lencoStatus']          ?? null,
            ':lenco_response'       => isset($data['lencoResponse'])
                                        ? json_encode($data['lencoResponse']) : null,
            ':transaction_id'       => $data['transactionId']        ?? null,
            ':payment_instructions' => $data['paymentInstructions']  ?? null,
            ':expires_at'           => $data['expiresAt']            ?? null,
            ':metadata'             => isset($data['metadata'])
                                        ? json_encode($data['metadata']) : null,
            ':created_at'           => $now,
            ':updated_at'           => $now,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update payment status. Skips if already terminal.
     */
    public function updateStatus(int $id, string $status, array $extra = []): bool
    {
        $payment = $this->findById($id);
        if (!$payment) return false;
        if (in_array($payment['status'], self::TERMINAL_STATUSES, true)) return false;

        $fields = ['status = :status', 'updated_at = :now'];
        $params = [':status' => $status, ':now' => date('Y-m-d H:i:s'), ':id' => $id];

        $map = [
            'lencoStatus'       => 'lenco_status',
            'completedAt'       => 'completed_at',
            'failureReason'     => 'failure_reason',
            'failedAt'          => 'failed_at',
            'webhookReceived'   => 'webhook_received',
            'webhookPayload'    => 'webhook_payload',
            'webhookReceivedAt' => 'webhook_received_at',
        ];
        foreach ($map as $phpKey => $dbCol) {
            if (array_key_exists($phpKey, $extra)) {
                $pk          = ':' . $phpKey;
                $fields[]    = "{$dbCol} = {$pk}";
                $val         = $extra[$phpKey];
                $params[$pk] = is_array($val) ? json_encode($val) : $val;
            }
        }

        $sql = 'UPDATE payments SET ' . implode(', ', $fields) . ' WHERE id = :id';
        return $this->pdo->prepare($sql)->execute($params) && true;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payments WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByTransactionId(string $txId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM payments WHERE transaction_id = :id OR lenco_transaction_id = :id2 LIMIT 1'
        );
        $stmt->execute([':id' => $txId, ':id2' => $txId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByLencoReference(string $ref): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payments WHERE lenco_reference = :ref LIMIT 1');
        $stmt->execute([':ref' => $ref]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Return the most-recent payment row for the given order number,
     * or null if no payment has been attempted yet.
     * "Most recent" is determined by created_at DESC so that retried
     * payments do not surface a stale failed row.
     */
    public function findByOrderNumber(string $orderNumber): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM payments
             WHERE  order_number = :on
             ORDER  BY created_at DESC
             LIMIT  1'
        );
        $stmt->execute([':on' => $orderNumber]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function connect(): PDO
    {
        static $instance = null;
        if ($instance !== null) return $instance;

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: 'localhost',
            (int)(getenv('DB_PORT') ?: 3306),
            getenv('DB_NAME') ?: 'lfs_db'
        );

        $instance = new PDO(
            $dsn,
            getenv('DB_USER') ?: 'root',
            getenv('DB_PASS') ?: '',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        return $instance;
    }
}
