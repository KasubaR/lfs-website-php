<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/services/FaqService.php
 *
 * Read-side service for the `faqs` table.
 *
 * DB schema:
 *   id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
 *   question    VARCHAR(500)  NOT NULL
 *   answer      TEXT          NOT NULL
 *   category    VARCHAR(100)  DEFAULT NULL
 *   sort_order  SMALLINT      NOT NULL DEFAULT 0
 *   created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

class FaqService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connect();
    }

    // ────────────────────────────────────────────────────────
    // Public API
    // ────────────────────────────────────────────────────────

    /**
     * Return all FAQs ordered by sort_order, then creation date.
     *
     * @return list<array<string,mixed>>
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, question, answer, category, sort_order, created_at
               FROM faqs
              ORDER BY sort_order ASC, created_at ASC'
        );
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Return FAQs for a specific category (case-insensitive).
     *
     * @param  string $category  Category slug or label.
     * @return list<array<string,mixed>>
     */
    public function getByCategory(string $category): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, question, answer, category, sort_order, created_at
               FROM faqs
              WHERE category = :category
              ORDER BY sort_order ASC, created_at ASC'
        );
        $stmt->execute([':category' => $category]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Return all unique category names (non-null), sorted alphabetically.
     *
     * @return list<string>
     */
    public function getCategories(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT category
               FROM faqs
              WHERE category IS NOT NULL AND category <> ''
              ORDER BY category ASC"
        );
        if (!$stmt) {
            return [];
        }
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category');
    }

    /**
     * Fetch a single FAQ by ID.
     *
     * @return array<string,mixed>|null
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, question, answer, category, sort_order, created_at
               FROM faqs
              WHERE id = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Total number of FAQs (for validating sort_order range).
     */
    public function getCount(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM faqs');
        return $stmt ? (int) $stmt->fetchColumn() : 0;
    }

    /**
     * Next sort_order for appending (max + 1, or 1 if table empty).
     * Used when user leaves sort_order as 0 (auto).
     */
    public function getNextSortOrder(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM faqs');
        return $stmt ? (int) $stmt->fetchColumn() : 1;
    }

    // ────────────────────────────────────────────────────────
    // Write helpers (used by admin)
    // ────────────────────────────────────────────────────────

    /**
     * Insert a new FAQ row.
     * If sort_order is 0, assigns next available (append). Otherwise clamps to 1..(count+1).
     *
     * @param  array<string,mixed> $data  Keys: question, answer, category (opt), sort_order (opt).
     * @return int  New row ID.
     */
    public function create(array $data): int
    {
        $requested = (int) ($data['sort_order'] ?? 0);
        $count     = $this->getCount();
        if ($requested <= 0) {
            $sortOrder = $this->getNextSortOrder();
        } else {
            $sortOrder = min($requested, $count + 1);
            $sortOrder = max(1, $sortOrder);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO faqs (question, answer, category, sort_order, created_at)
             VALUES (:question, :answer, :category, :sort_order, NOW())'
        );
        $stmt->execute([
            ':question'   => trim($data['question'] ?? ''),
            ':answer'     => trim($data['answer']   ?? ''),
            ':category'   => $data['category']   ?? null,
            ':sort_order' => $sortOrder,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update an existing FAQ row.
     * If sort_order is 0, sets to last position (count). Otherwise clamps to 1..count.
     *
     * @param  int                 $id
     * @param  array<string,mixed> $data  Same keys as create().
     * @return bool  True if a row was changed.
     */
    public function update(int $id, array $data): bool
    {
        $requested = (int) ($data['sort_order'] ?? 0);
        $count     = $this->getCount();
        if ($requested <= 0) {
            $sortOrder = $count > 0 ? $count : 1;
        } else {
            $sortOrder = min($requested, $count);
            $sortOrder = max(1, $sortOrder);
        }

        $stmt = $this->pdo->prepare(
            'UPDATE faqs
                SET question   = :question,
                    answer     = :answer,
                    category   = :category,
                    sort_order = :sort_order
              WHERE id = :id'
        );
        $stmt->execute([
            ':question'   => trim($data['question'] ?? ''),
            ':answer'     => trim($data['answer']   ?? ''),
            ':category'   => $data['category']   ?? null,
            ':sort_order' => $sortOrder,
            ':id'         => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a FAQ by ID.
     *
     * @return bool True if a row was deleted.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM faqs WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
