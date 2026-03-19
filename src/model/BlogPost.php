<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/model/BlogPost.php — Blog post constants + helpers
 *
 * Database: MySQL table `blog_posts`
 * Data layer: src/services/BlogPostService.php
 *
 * Fields:
 *   id, title, slug, excerpt, content, featuredImage, author,
 *   category, tags (JSON), status, featured, views,
 *   publishDate, createdAt, updatedAt
 */

declare(strict_types=1);

class BlogPost
{
    /** Blog categories (matches DB CHECK constraint). */
    public const CATEGORIES = [
        'Club News',
        'Race Reports',
        'Training Tips',
        'Announcements',
    ];

    /** Human-readable labels for categories. */
    public const CATEGORY_LABELS = [
        'Club News'     => 'Club News',
        'Race Reports'  => 'Race Reports',
        'Training Tips' => 'Training Tips',
        'Announcements' => 'Announcements',
    ];

    /** Allowed status values (matches DB ENUM). */
    public const STATUSES = ['draft', 'published'];

    /**
     * Category options for filter/dropdown UIs.
     * Returns [['value' => '', 'label' => 'All Categories'], ...]
     *
     * @return array<array{value: string, label: string}>
     */
    public static function getCategoryOptions(): array
    {
        $options = [['value' => '', 'label' => 'All Categories']];
        foreach (self::CATEGORIES as $cat) {
            $options[] = ['value' => $cat, 'label' => self::CATEGORY_LABELS[$cat]];
        }
        return $options;
    }

    /**
     * Status options for admin form selects.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function getStatusOptions(): array
    {
        return [
            ['value' => 'draft',     'label' => 'Draft'],
            ['value' => 'published', 'label' => 'Published'],
        ];
    }

    /**
     * Validate a blog post payload.
     *
     * @param  array $data  Normalised data array from BlogController::extractPostData()
     * @return array<string,string>  Field → error message
     */
    public static function validate(array $data): array
    {
        $errors = [];

        // Title
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Title is required.';
        } elseif (mb_strlen($title) > 200) {
            $errors['title'] = 'Title must be at most 200 characters.';
        }

        // Slug
        $slug = trim((string)($data['slug'] ?? ''));
        if ($slug === '') {
            $errors['slug'] = 'Slug is required (it will normally be auto-generated from the title).';
        } elseif (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
            $errors['slug'] = 'Slug may only contain lowercase letters, numbers, and hyphens.';
        }

        // Category
        $category = (string)($data['category'] ?? '');
        if ($category !== '' && !in_array($category, self::CATEGORIES, true)) {
            $errors['category'] = 'Please choose a valid category.';
        }

        // Status
        $status = (string)($data['status'] ?? '');
        if ($status === '' || !in_array($status, self::STATUSES, true)) {
            $errors['status'] = 'Status must be either draft or published.';
        }

        // Publish date (when provided)
        if (!empty($data['publishDate'])) {
            $ts = strtotime((string)$data['publishDate']);
            if ($ts === false) {
                $errors['publishDate'] = 'Publish date is not a valid date/time.';
            }
        }

        return $errors;
    }
}
