<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/model/Event.php — Event model constants
 *
 * Database: MySQL table `events`
 * Data layer: src/services/EventService.php
 *
 * Event fields (camelCase in app / snake_case in DB):
 *   id, title, description, location, eventDate, distance,
 *   category, registrationOpen, registrationClose, bannerImage, featureOnHome,
 *   series, createdBy, createdAt, updatedAt
 */

declare(strict_types=1);

class Event
{
    /** Default category options for events (matches DB CHECK constraint). */
    public const CATEGORIES = [
        'LSD',
        'Road Race',
        'Training',
        'Training Camp',
        'Social',
        'Other',
    ];
}
