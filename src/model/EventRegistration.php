<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/model/EventRegistration.php — Event registration constants
 *
 * Database: MySQL table `event_registrations`
 * Data layer: src/services/EventRegistrationService.php
 */

declare(strict_types=1);

class EventRegistration
{
    /** Registration status values (matches DB CHECK constraint). */
    public const STATUS = ['Registered', 'Completed', 'Cancelled'];

    /** Payment status values (matches DB CHECK constraint). */
    public const PAYMENT_STATUS = ['pending', 'paid', 'refunded', 'free'];
}
