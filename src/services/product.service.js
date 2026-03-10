/* ============================================================
   LFS — Lusaka Fitness Squad
   src/services/product.service.js — Product data layer (Supabase)

   Expects Supabase table: products
   Columns (snake_case): id, name, slug, price, compare_price, description,
     short_description, images, thumbnail, category, gender, tags, sizes,
     total_stock, featured, is_active, sort_order, created_at, updated_at
   ============================================================ */

'use strict';

const supabase = require('../config/supabase');

/* ════════════════════════════════════════════════════════════
   HELPERS
   ════════════════════════════════════════════════════════════ */

/** Basic URL/string normaliser for image paths. */
function sanitizeImageUrl(url, fallback = '') {
  if (!url) return fallback;
  const v = String(url).trim();
  return v || fallback;
}

/** Map Supabase row to camelCase shape expected by shop controller and EJS. */
function toProduct(row) {
  if (!row) return null;
  return {
    _id:              row.id,
    id:               row.id,
    name:             row.name,
    slug:             row.slug,
    price:            row.price ?? 0,
    comparePrice:     row.compare_price ?? null,
    description:      row.description ?? '',
    shortDescription: row.short_description ?? '',
    images:           Array.isArray(row.images) ? row.images : [],
    thumbnail:        sanitizeImageUrl(
      row.thumbnail,
      '/images/products/placeholder.webp'
    ),
    category:         row.category,
    gender:           row.gender ?? 'unisex',
    tags:             Array.isArray(row.tags) ? row.tags : [],
    sizes:            Array.isArray(row.sizes) ? row.sizes : [],
    totalStock:       row.total_stock ?? 0,
    featured:         row.featured ?? false,
    isActive:         row.is_active !== false,
    sortOrder:        row.sort_order ?? 0,
    createdAt:        row.created_at,
    updatedAt:        row.updated_at,
  };
}

/**
 * Core listing function.
 * Public:   admin = false  → only is_active = true
 * Admin:    admin = true   → all rows (active + inactive)
 *
 * @param {object} opts - category, gender, size, minPrice, maxPrice, sort, page, limit
 * @param {object} [options]
 * @param {boolean} [options.admin=false] - when true, do not filter by is_active
 * @returns {Promise<{ products, total, page, limit, pages }>}
 */
async function getProducts(opts = {}, { admin = false } = {}) {
  const {
    category,
    gender,
    size,
    minPrice,
    maxPrice,
    sort = 'latest',
    page = 1,
    limit = 12,
  } = opts;

  let q = supabase.from('products').select('*', { count: 'exact' });

  if (!admin) {
    q = q.eq('is_active', true);
  }

  if (category) q = q.eq('category', category);
  if (gender) q = q.eq('gender', gender);
  if (minPrice != null && minPrice !== '') q = q.gte('price', Number(minPrice));
  if (maxPrice != null && maxPrice !== '') q = q.lte('price', Number(maxPrice));
  /* Filter by size: sizes is jsonb array of { size, stock }. Contains object with this size. */
  if (size) q = q.contains('sizes', [{ size: String(size) }]);

  const sortMap = {
    latest:       { column: 'created_at', ascending: false },
    popular:      { column: 'sort_order', ascending: false },
    'price-asc':  { column: 'price', ascending: true },
    'price-desc': { column: 'price', ascending: false },
  };
  const { column, ascending } = sortMap[sort] || sortMap.latest;
  q = q.order(column, { ascending });

  const from = (Number(page) - 1) * Number(limit);
  const to = from + Number(limit) - 1;
  const { data, error, count } = await q.range(from, to);

  if (error) throw error;

  const total = count ?? 0;
  const pages = Math.ceil(total / Number(limit)) || 1;

  return {
    products: (data || []).map(toProduct),
    total,
    page: Number(page),
    limit: Number(limit),
    pages,
  };
}

/**
 * Backwards-compatible public listing used by existing shop controller.
 * @deprecated Prefer getProducts(opts, { admin: false })
 */
async function findPublic(opts = {}) {
  return getProducts(opts, { admin: false });
}

/**
 * Find one product by slug.
 * Public:  admin = false → only active
 * Admin:   admin = true  → any status
 */
async function getProductBySlug(slug, { admin = false } = {}) {
  let q = supabase.from('products').select('*').eq('slug', slug);
  if (!admin) q = q.eq('is_active', true);
  const { data, error } = await q.maybeSingle();
  if (error) throw error;
  return toProduct(data);
}

/**
 * Find one product by id.
 * Public:  admin = false → only active
 * Admin:   admin = true  → any status
 */
async function getProductById(id, { admin = false } = {}) {
  let q = supabase.from('products').select('*').eq('id', id);
  if (!admin) q = q.eq('is_active', true);
  const { data, error } = await q.maybeSingle();
  if (error) throw error;
  return toProduct(data);
}

/** Backwards-compatible wrappers used by existing code. */
async function findOneBySlug(slug) {
  return getProductBySlug(slug, { admin: false });
}

async function findById(id) {
  return getProductById(id, { admin: false });
}

/**
 * Find products by category, excluding one id. For "related products".
 */
async function findRelatedByCategory(category, excludeId, limit = 4) {
  let q = supabase
    .from('products')
    .select('*')
    .eq('category', category)
    .eq('is_active', true)
    .neq('id', excludeId)
    .order('created_at', { ascending: false })
    .limit(limit);
  const { data, error } = await q;
  if (error) throw error;
  return (data || []).map(toProduct);
}

/* ════════════════════════════════════════════════════════════
   MUTATIONS (Admin)
   ════════════════════════════════════════════════════════════ */

/**
 * Create a product (admin).
 * @param {Object} data - Partial Product fields in camelCase.
 */
async function createProduct(data) {
  const row = {
    name:              data.name,
    slug:              data.slug,
    price:             data.price,
    compare_price:     data.comparePrice ?? null,
    description:       data.description ?? '',
    short_description: data.shortDescription ?? '',
    images:            Array.isArray(data.images) ? data.images : [],
    thumbnail:         data.thumbnail ?? '/images/products/placeholder.webp',
    category:          data.category,
    gender:            data.gender ?? 'unisex',
    tags:              Array.isArray(data.tags) ? data.tags : [],
    sizes:             Array.isArray(data.sizes) ? data.sizes : [],
    total_stock:       data.totalStock ?? 0,
    featured:          data.featured ?? false,
    is_active:         data.isActive !== false,
    sort_order:        data.sortOrder ?? 0,
  };

  const { data: inserted, error } = await supabase
    .from('products')
    .insert(row)
    .select()
    .single();
  if (error) throw error;
  return toProduct(inserted);
}

/**
 * Update a product by id (admin).
 * Accepts partial fields; only provided keys are updated.
 */
async function updateProduct(id, data) {
  const row = { updated_at: new Date().toISOString() };

  if (data.name !== undefined)              row.name = data.name;
  if (data.slug !== undefined)              row.slug = data.slug;
  if (data.price !== undefined)             row.price = data.price;
  if (data.comparePrice !== undefined)      row.compare_price = data.comparePrice;
  if (data.description !== undefined)       row.description = data.description ?? '';
  if (data.shortDescription !== undefined)  row.short_description = data.shortDescription ?? '';
  if (data.images !== undefined)            row.images = Array.isArray(data.images) ? data.images : [];
  if (data.thumbnail !== undefined)         row.thumbnail = data.thumbnail;
  if (data.category !== undefined)          row.category = data.category;
  if (data.gender !== undefined)            row.gender = data.gender;
  if (data.tags !== undefined)              row.tags = Array.isArray(data.tags) ? data.tags : [];
  if (data.sizes !== undefined)             row.sizes = Array.isArray(data.sizes) ? data.sizes : [];
  if (data.totalStock !== undefined)        row.total_stock = data.totalStock;
  if (data.featured !== undefined)          row.featured = data.featured;
  if (data.isActive !== undefined)          row.is_active = data.isActive;
  if (data.sortOrder !== undefined)         row.sort_order = data.sortOrder;

  const { data: updated, error } = await supabase
    .from('products')
    .update(row)
    .eq('id', id)
    .select()
    .maybeSingle();
  if (error) throw error;
  return updated ? toProduct(updated) : null;
}

/**
 * Delete a product by id.
 * NOTE: This is a hard delete; use updateProduct(id, { isActive: false })
 * for a soft delete strategy.
 */
async function deleteProduct(id) {
  const { error } = await supabase.from('products').delete().eq('id', id);
  if (error) throw error;
}

module.exports = {
  findPublic,
  findOneBySlug,
  findById,
  findRelatedByCategory,
  toProduct,
  // new API surface
  getProducts,
  getProductBySlug,
  getProductById,
  createProduct,
  updateProduct,
  deleteProduct,
  sanitizeImageUrl,
};
