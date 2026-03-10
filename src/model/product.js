/* ============================================================
   LFS — Lusaka Fitness Squad
   src/model/product.js — Product model (shape + constants)

   Database: Supabase table `products` (see supabase-schema.sql)
   Data layer: src/services/product.service.js
   ============================================================ */

'use strict';

/**
 * Size stock entry — one size variant with quantity.
 * @typedef {Object} SizeStock
 * @property {string} size - e.g. "S", "M", "L"
 * @property {number} stock
 */

/**
 * Product — shop product (merchandise).
 * @typedef {Object} Product
 * @property {string} id - UUID
 * @property {string} name
 * @property {string} slug - URL-friendly unique
 * @property {number} price
 * @property {number|null} [comparePrice] - was price (for sale display)
 * @property {string} [description]
 * @property {string} [shortDescription]
 * @property {string[]} [images] - URLs
 * @property {string} [thumbnail] - URL
 * @property {string} category - running-kits | t-shirts | caps | shorts | accessories | other
 * @property {string} [gender] - male | female | unisex
 * @property {string[]} [tags]
 * @property {SizeStock[]} [sizes] - per-size stock
 * @property {number} [totalStock]
 * @property {boolean} [featured]
 * @property {boolean} [isActive]
 * @property {number} [sortOrder]
 * @property {string} [createdAt]
 * @property {string} [updatedAt]
 */

/** Product categories (matches DB check). */
const PRODUCT_CATEGORIES = [
  'running-kits',
  't-shirts',
  'caps',
  'shorts',
  'accessories',
  'other',
];

/** Human-readable labels for category slugs. */
const PRODUCT_CATEGORY_LABELS = {
  'running-kits': 'Running Kits',
  't-shirts':     'T-Shirts',
  'caps':         'Caps',
  'shorts':       'Shorts',
  'accessories':  'Accessories',
  'other':        'Other',
};

/** Gender options (matches DB check). */
const GENDER_OPTIONS = ['male', 'female', 'unisex'];

/** Human-readable labels for gender values. */
const GENDER_LABELS = {
  male:   'Male',
  female: 'Female',
  unisex: 'Unisex',
};

/**
 * Category options for filter UIs (e.g. shop sidebar).
 * @returns {Array<{value: string, label: string}>}
 */
function getCategoryOptions() {
  return [
    { value: '', label: 'All Categories' },
    ...PRODUCT_CATEGORIES.map((value) => ({ value, label: PRODUCT_CATEGORY_LABELS[value] })),
  ];
}

/**
 * Gender options for filter UIs.
 * @returns {Array<{value: string, label: string}>}
 */
function getGenderOptions() {
  return [
    { value: '', label: 'All' },
    ...GENDER_OPTIONS.map((value) => ({ value, label: GENDER_LABELS[value] })),
  ];
}

module.exports = {
  PRODUCT_CATEGORIES,
  PRODUCT_CATEGORY_LABELS,
  GENDER_OPTIONS,
  GENDER_LABELS,
  getCategoryOptions,
  getGenderOptions,
};
