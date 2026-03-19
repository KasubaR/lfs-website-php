<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/model/Gallery.php — Gallery constants (albums + media)
 *
 * Database: MySQL tables `albums`, `media`
 * Data layer: src/services/GalleryService.php
 *
 * Album fields:
 *   id, title, description, category, date, location, event,
 *   tags (JSON), coverImage, mediaCount, featured, homepageSlider,
 *   eventHighlight, sortPriority, externalUrl, createdAt, updatedAt
 *
 * Media fields:
 *   id, albumId, filename, storedName, type, mimetype, size,
 *   urls (JSON), caption, tags (JSON), featured, sortOrder,
 *   createdAt, updatedAt
 */

declare(strict_types=1);

class Gallery
{
    /** Allowed media types (matches DB ENUM). */
    public const MEDIA_TYPES = ['photo', 'video'];

    /** Optional album categories for admin dropdowns. */
    public const ALBUM_CATEGORIES = [
        'Race',
        'Training',
        'LSD',
        'Social',
        'Other',
    ];
}
