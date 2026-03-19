<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/model/ContactMessage.php — Contact message constants
 *
 * Database: MySQL table `contact_messages`
 * Data layer: src/services/ContactMessageService.php
 */

declare(strict_types=1);

class ContactMessage
{
    /** Message status values (matches DB CHECK constraint). */
    public const STATUS = ['New', 'Read', 'Responded'];
}
