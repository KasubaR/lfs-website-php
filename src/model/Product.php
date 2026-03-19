<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/model/Product.php — Product model constants + helpers
 *
 * Database: MySQL table `products`
 * Data layer: src/services/ProductService.php
 *
 * Fields:
 *   id, name, slug, price, comparePrice, description, shortDescription,
 *   images (JSON), thumbnail, category, gender, tags (JSON),
 *   sizes (JSON), totalStock, featured, isActive, sortOrder,
 *   createdAt, updatedAt
 */

declare(strict_types=1);

class Product
{
    /** Product categories (matches DB CHECK constraint). */
    public const CATEGORIES = [
        'running-kits',
        't-shirts',
        'caps',
        'shorts',
        'accessories',
        'other',
    ];

    /** Human-readable labels for category slugs. */
    public const CATEGORY_LABELS = [
        'running-kits' => 'Running Kits',
        't-shirts'     => 'T-Shirts',
        'caps'         => 'Caps',
        'shorts'       => 'Shorts',
        'accessories'  => 'Accessories',
        'other'        => 'Other',
    ];

    /** Gender options (matches DB CHECK constraint). */
    public const GENDER_OPTIONS = ['male', 'female', 'unisex'];

    /** Human-readable labels for gender values. */
    public const GENDER_LABELS = [
        'male'   => 'Male',
        'female' => 'Female',
        'unisex' => 'Unisex',
    ];

    /**
     * Category options for filter UIs (e.g. shop sidebar).
     * Returns [['value' => '', 'label' => 'All Categories'], ...]
     *
     * @return array<array{value: string, label: string}>
     */
    public static function getCategoryOptions(): array
    {
        $options = [['value' => '', 'label' => 'All Categories']];
        foreach (self::CATEGORIES as $value) {
            $options[] = ['value' => $value, 'label' => self::CATEGORY_LABELS[$value]];
        }
        return $options;
    }

    /**
     * Gender options for filter UIs.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function getGenderOptions(): array
    {
        $options = [['value' => '', 'label' => 'All']];
        foreach (self::GENDER_OPTIONS as $value) {
            $options[] = ['value' => $value, 'label' => self::GENDER_LABELS[$value]];
        }
        return $options;
    }
}
