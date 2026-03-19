<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/services/ContactMessageService.php
 *
 * Persists contact-form submissions to the `contact_messages` table.
 *
 * DB schema assumed:
 *   id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
 *   name        VARCHAR(120) NOT NULL
 *   email       VARCHAR(254) NOT NULL
 *   phone       VARCHAR(30)  DEFAULT NULL   -- optional; add column if missing
 *   satellite   VARCHAR(60)  DEFAULT NULL   -- optional; add column if missing
 *   subject     VARCHAR(200) DEFAULT NULL
 *   message     TEXT         NOT NULL
 *   status      ENUM('New','Read','Responded') NOT NULL DEFAULT 'New'
 *   created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../model/ContactMessage.php';

class ContactMessageService
{
    private PDO $pdo;
    private const MAX_REPLY_CHARS = 5000;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connect();
    }

    // ────────────────────────────────────────────────────────
    // Public API
    // ────────────────────────────────────────────────────────

    /**
     * Persist a new contact message.
     *
     * Accepted keys in $data:
     *   firstName  string  required
     *   lastName   string  required
     *   email      string  required
     *   message    string  required
     *   phone      string  optional
     *   satellite  string  optional
     *   subject    string  optional
     *
     * @param  array<string,string> $data  Validated form data.
     * @return int  Newly inserted row ID.
     * @throws RuntimeException on DB error.
     */
    public function create(array $data): int
    {
        $name    = trim(($data['firstName'] ?? '') . ' ' . ($data['lastName'] ?? ''));
        $email   = trim($data['email']   ?? '');
        $message = trim($data['message'] ?? '');

        if ($name === '' || $email === '' || $message === '') {
            throw new \InvalidArgumentException('name, email, and message are required.');
        }

        $sql = '
            INSERT INTO contact_messages
                (name, email, phone, satellite, subject, message, status, created_at)
            VALUES
                (:name, :email, :phone, :satellite, :subject, :message, :status, NOW())
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name'      => $name,
            ':email'     => $email,
            ':phone'     => $data['phone']     ?? null,
            ':satellite' => $data['satellite'] ?? null,
            ':subject'   => $data['subject']   ?? null,
            ':message'   => $message,
            ':status'    => ContactMessage::STATUS[0], // 'New'
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Fetch all messages, newest first.
     *
     * @return list<array<string,mixed>>
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM contact_messages ORDER BY created_at DESC'
        );
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Fetch a single message by ID.
     *
     * @return array<string,mixed>|null
     */
    public function getById(string $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM contact_messages WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Update the status of a message.
     *
     * @param  int    $id      Row ID.
     * @param  string $status  One of ContactMessage::STATUS values.
     * @return bool   True if a row was updated.
     * @throws \InvalidArgumentException for unrecognised status.
     */
    public function updateStatus(string $id, string $status): bool
    {
        if (!in_array($status, ContactMessage::STATUS, true)) {
            throw new \InvalidArgumentException(
                "Invalid status '$status'. Allowed: " . implode(', ', ContactMessage::STATUS)
            );
        }

        $stmt = $this->pdo->prepare(
            'UPDATE contact_messages SET status = :status WHERE id = :id'
        );
        $stmt->execute([':status' => $status, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Count messages by status.
     *
     * @return array<string,int>  e.g. ['New' => 3, 'Read' => 10, 'Responded' => 5]
     */
    public function countByStatus(): array
    {
        $counts = array_fill_keys(ContactMessage::STATUS, 0);

        $stmt = $this->pdo->query(
            "SELECT status, COUNT(*) AS cnt
               FROM contact_messages
              GROUP BY status"
        );
        if ($stmt) {
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $counts[$row['status']] = (int) $row['cnt'];
            }
        }
        return $counts;
    }

    /**
     * Delete a message by ID.
     *
     * @return bool True if a row was deleted.
     */
    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM contact_messages WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Persist an admin reply for a contact message.
     *
     * @param string $messageId  contact_messages.id (UUID char(36) in current schema)
     * @param string $reply      Admin reply text
     * @return string|int        Newly inserted reply ID
     */
    public function createReply(string $messageId, string $reply): string|int
    {
        $messageId = trim($messageId);
        $reply     = trim($reply);

        if ($messageId === '' || $reply === '') {
            throw new \InvalidArgumentException('messageId and reply are required.');
        }

        // Basic normalization and safety cleanup before persistence.
        $reply = preg_replace("/\r\n?/", "\n", $reply) ?? $reply;
        $reply = strip_tags($reply);

        $replyLen = function_exists('mb_strlen')
            ? mb_strlen($reply, 'UTF-8')
            : strlen($reply);
        if ($replyLen > self::MAX_REPLY_CHARS) {
            throw new \InvalidArgumentException(
                'Reply exceeds maximum length of ' . self::MAX_REPLY_CHARS . ' characters.'
            );
        }

        $sql = '
            INSERT INTO contact_replies
                (id, contact_message_id, reply_message, created_at)
            VALUES
                (UUID(), :message_id, :reply_message, NOW())
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':message_id'    => $messageId,
            ':reply_message' => $reply,
        ]);

        // UUID PK is explicitly generated in SQL.
        $idStmt = $this->pdo->prepare(
            'SELECT id FROM contact_replies WHERE contact_message_id = :message_id ORDER BY created_at DESC LIMIT 1'
        );
        $idStmt->execute([':message_id' => $messageId]);
        $id = (string)($idStmt->fetchColumn() ?: '');

        return $id !== '' ? $id : (int)$this->pdo->lastInsertId();
    }

    /**
     * Fetch all replies for a given contact message (newest first).
     *
     * @param  string $messageId
     * @return list<array<string,mixed>>
     */
    public function getRepliesByMessageId(string $messageId): array
    {
        $messageId = trim($messageId);
        if ($messageId === '') {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, contact_message_id, reply_message, created_at
               FROM contact_replies
              WHERE contact_message_id = :message_id
              ORDER BY created_at DESC'
        );
        $stmt->execute([':message_id' => $messageId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
