<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/model/OrderModel.php
 *
 * DB operations for the orders + order_items tables.
 * Uses PDO prepared statements exclusively.
 */

declare(strict_types=1);

class OrderModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = $this->connect();
    }

    // ================================================================
    // Write
    // ================================================================

    /**
     * Create an order + its line items in a transaction.
     * Returns the new order ID.
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(<<<SQL
                INSERT INTO orders
                    (order_number, customer_name, customer_email, customer_phone,
                     notes, subtotal, total, status, created_at, updated_at)
                VALUES
                    (:order_number, :customer_name, :customer_email, :customer_phone,
                     :notes, :subtotal, :total, :status, :created_at, :updated_at)
            SQL);

            $stmt->execute([
                ':order_number'   => $data['orderNumber'],
                ':customer_name'  => $data['customerName'],
                ':customer_email' => strtolower($data['customerEmail']),
                ':customer_phone' => $data['customerPhone'] ?? '',
                ':notes'          => $data['notes']         ?? '',
                ':subtotal'       => $data['subtotal'],
                ':total'          => $data['total'],
                ':status'         => $data['status']        ?? 'pending_payment',
                ':created_at'     => $now,
                ':updated_at'     => $now,
            ]);

            $orderId = (int) $this->pdo->lastInsertId();

            // Insert line items
            $itemStmt = $this->pdo->prepare(<<<SQL
                INSERT INTO order_items
                    (order_id, product_id, name, size, qty, unit_price, line_total)
                VALUES
                    (:order_id, :product_id, :name, :size, :qty, :unit_price, :line_total)
            SQL);

            foreach ($data['items'] as $item) {
                $qty   = (int)   ($item['qty']   ?? 1);
                $price = (float) ($item['price'] ?? 0);
                $itemStmt->execute([
                    ':order_id'   => $orderId,
                    ':product_id' => $item['productId'] ?? '',
                    ':name'       => $item['name']       ?? '',
                    ':size'       => $item['size']       ?? '',
                    ':qty'        => $qty,
                    ':unit_price' => $price,
                    ':line_total' => $price * $qty,
                ]);
            }

            $this->pdo->commit();
            return $orderId;

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Update order status.
     *
     * @param  int|string $identifier     Order ID or order_number
     * @param  string     $status         New status
     * @param  bool       $byOrderNumber  If true, $identifier is treated as order_number
     */
    public function updateStatus(int|string $identifier, string $status, bool $byOrderNumber = false): void
    {
        $col = $byOrderNumber ? 'order_number' : 'id';
        $this->pdo->prepare(
            "UPDATE orders SET status = :status, updated_at = :now WHERE {$col} = :id"
        )->execute([
            ':status' => $status,
            ':now'    => date('Y-m-d H:i:s'),
            ':id'     => $identifier,
        ]);
    }

    // ================================================================
    // Read
    // ================================================================

    /**
     * Paginated list of orders (no line items).
     *
     * @param array $options {
     *   status?  string  Filter to a single status value
     *   limit?   int     Max rows to return (default 25)
     *   offset?  int     Rows to skip (default 0)
     * }
     * @return array[]  Flat array of order rows
     */
    public function getAll(array $options = []): array
    {
        $limit  = max(1, (int)($options['limit']  ?? 25));
        $offset = max(0, (int)($options['offset'] ?? 0));

        $where  = [];
        $params = [];

        if (isset($options['status']) && $options['status'] !== '') {
            $where[]           = 'status = :status';
            $params[':status'] = $options['status'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->pdo->prepare(
            "SELECT id, order_number, customer_name, customer_email, customer_phone,
                    subtotal, total, status, created_at, updated_at
             FROM orders
             $whereSql
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Count orders, optionally filtered to a single status.
     *
     * @param  string|null $status  Exact status value, or null for all orders
     * @return int
     */
    public function countByStatus(?string $status = null): int
    {
        if ($status === null) {
            return (int) $this->pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM orders WHERE status = :status');
        $stmt->execute([':status' => $status]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Fetch a single order with its line items, looked up by primary key.
     * Returns the order row plus an `items` array, or null if not found.
     * Same shape as findByOrderNumber().
     */
    public function findById(int $id): ?array
    {
        return $this->fetchWithItems('o.id', $id);
    }

    /**
     * Fetch a single order with its line items, looked up by order_number.
     * Returns the order row plus an `items` array, or null if not found.
     */
    public function findByOrderNumber(string $orderNumber): ?array
    {
        return $this->fetchWithItems('o.order_number', $orderNumber);
    }

    // ================================================================
    // Private
    // ================================================================

    /**
     * Shared fetch helper used by findById() and findByOrderNumber().
     * Runs a single JOIN query with GROUP_CONCAT to pull the order row
     * and all its line items in one round-trip.
     *
     * @param  string     $col    Fully-qualified column to match (e.g. 'o.id')
     * @param  int|string $value  Value to match against $col
     */
    private function fetchWithItems(string $col, int|string $value): ?array
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT o.*,
                   GROUP_CONCAT(
                       JSON_OBJECT(
                           'name',      oi.name,
                           'size',      oi.size,
                           'qty',       oi.qty,
                           'unitPrice', oi.unit_price,
                           'lineTotal', oi.line_total
                       )
                   ) AS items_json
            FROM   orders o
            LEFT   JOIN order_items oi ON oi.order_id = o.id
            WHERE  {$col} = :value
            GROUP  BY o.id
            LIMIT  1
        SQL);

        $stmt->execute([':value' => $value]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        $row['items'] = $row['items_json']
            ? json_decode('[' . $row['items_json'] . ']', true)
            : [];
        unset($row['items_json']);

        return $row;
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
