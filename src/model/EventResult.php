<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/model/EventResult.php — Event result shape
 *
 * Database: MySQL table `event_results`
 * Data layer: src/services/EventResultService.php
 *
 * Fields:
 *   id, eventId, runnerName, position, time,
 *   category, club, createdAt, updatedAt
 *
 * No constants — no CHECK constraints on this table beyond the above.
 */

declare(strict_types=1);

class EventResult
{
    // No domain constants required for this model.
    // Add result categories here if/when a DB CHECK constraint is introduced.
}
