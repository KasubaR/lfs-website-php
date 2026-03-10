'use strict';

/* ============================================================
   LFS — Lusaka Fitness Squad
   admin/controllers/product.controller.js
 
   Product admin: list, create, edit, update, delete.
   Uses product.service (Supabase) and product model constants.
   ============================================================ */

const path = require('path');
const fs = require('fs');
const productService = require('../../services/product.service');
const { PRODUCT_CATEGORIES, GENDER_OPTIONS } = require('../../model/product');
const { slugify, formatPrice } = require('../../utility/helpers');

const IS_PROD = process.env.NODE_ENV === 'production';
const DEFAULT_PUBLIC_ROOT = path.join(__dirname, '..', '..', '..', 'public');
const PUBLIC_ROOT = (IS_PROD && process.env.PUBLIC_ROOT) ? process.env.PUBLIC_ROOT : DEFAULT_PUBLIC_ROOT;
const PRODUCT_UPLOAD_ROOT = path.join(PUBLIC_ROOT, 'uploads', 'products');

/* ════════════════════════════════════════════════════════════
   HELPERS
   ════════════════════════════════════════════════════════════ */

function parseTags(raw) {
  if (!raw) return [];
  return String(raw)
    .split(',')
    .map((t) => t.trim())
    .filter(Boolean);
}

/**
 * Parse sizes from a comma-separated string like:
 *   "S:10, M:5, L:0"
 * into [{ size: 'S', stock: 10 }, ...]
 */
function parseSizes(raw) {
  if (!raw) return [];
  return String(raw)
    .split(',')
    .map((entry) => entry.trim())
    .filter(Boolean)
    .map((entry) => {
      const [size, stockStr] = entry.split(':').map((p) => p.trim());
      const stock = Number.parseInt(stockStr, 10);
      return {
        size,
        stock: Number.isFinite(stock) && stock >= 0 ? stock : 0,
      };
    })
    .filter((s) => s.size);
}

function normaliseCheckbox(value) {
  return value === 'on' || value === 'true' || value === true;
}

/* ════════════════════════════════════════════════════════════
   LIST — GET /admin/products
   ════════════════════════════════════════════════════════════ */

async function getProducts(req, res, next) {
  try {
    const { page = 1, category = '', search = '' } = req.query;

    const { products, total, pages } = await productService.getProducts(
      {
        category: category || undefined,
        sort: 'latest',
        page: Number(page) || 1,
        limit: 20,
      },
      { admin: true },
    );

    // Simple search filter on name/description in-memory for now
    const term = String(search || '').toLowerCase().trim();
    const filtered = term
      ? products.filter((p) =>
          (p.name || '').toLowerCase().includes(term) ||
          (p.description || '').toLowerCase().includes(term),
        )
      : products;

    res.render('products/list', {
      layout: 'layouts/admin',
      pageTitle: 'Products',
      activePage: 'products',
      products: filtered,
      total,
      pages,
      currentPage: Number(page) || 1,
      filters: {
        category,
        search,
      },
      PRODUCT_CATEGORIES,
      formatPrice,
    });
  } catch (err) {
    next(err);
  }
}

/* ════════════════════════════════════════════════════════════
   CREATE (GET) — GET /admin/products/create
   ════════════════════════════════════════════════════════════ */

async function getCreateProduct(_req, res, next) {
  try {
    res.render('products/form', {
      layout: 'layouts/admin',
      pageTitle: 'New Product',
      activePage: 'products',
      product: null,
      PRODUCT_CATEGORIES,
      GENDER_OPTIONS,
      isEdit: false,
      error: null,
    });
  } catch (err) {
    next(err);
  }
}

/* ════════════════════════════════════════════════════════════
   CREATE (POST) — POST /admin/products
   ════════════════════════════════════════════════════════════ */

async function postCreateProduct(req, res, next) {
  try {
    const {
      name,
      slug,
      price,
      comparePrice,
      description,
      shortDescription,
      category,
      gender,
      tags,
      sizes,
      totalStock,
      featured,
      isActive,
      sortOrder,
    } = req.body;

    if (!name || !price || !category || !gender) {
      return res.render('products/form', {
        layout: 'layouts/admin',
        pageTitle: 'New Product',
        activePage: 'products',
        product: req.body,
        PRODUCT_CATEGORIES,
        GENDER_OPTIONS,
        isEdit: false,
        error: 'Name, price, category and gender are required.',
      });
    }

    const numericPrice = Number(price);
    if (!Number.isFinite(numericPrice) || numericPrice < 0) {
      return res.render('products/form', {
        layout: 'layouts/admin',
        pageTitle: 'New Product',
        activePage: 'products',
        product: req.body,
        PRODUCT_CATEGORIES,
        GENDER_OPTIONS,
        isEdit: false,
        error: 'Price must be a non-negative number.',
      });
    }

    const data = {
      name: name.trim(),
      slug: slug ? slugify(slug) : slugify(name),
      price: numericPrice,
      comparePrice:
        comparePrice && comparePrice !== ''
          ? Number(comparePrice)
          : undefined,
      description,
      shortDescription,
      category,
      gender,
      tags: parseTags(tags),
      sizes: parseSizes(sizes),
      totalStock:
        totalStock && totalStock !== ''
          ? Number.parseInt(totalStock, 10)
          : 0,
      featured: normaliseCheckbox(featured),
      isActive: normaliseCheckbox(isActive),
      sortOrder:
        sortOrder && sortOrder !== ''
          ? Number.parseInt(sortOrder, 10)
          : 0,
    };

    // Merge any uploaded image URLs from productImageUpload middleware
    if (Array.isArray(req.productImages) && req.productImages.length) {
      data.images = req.productImages;
      data.thumbnail = req.productImages[0];
    }

    try {
      const created = await productService.createProduct(data);

      // If images were uploaded for a new product, move them from the temporary
      // upload key folder to a folder named by the real product id and update URLs.
      if (
        created &&
        created.id &&
        Array.isArray(req.productImages) &&
        req.productImages.length &&
        req.productUploadKey
      ) {
        const oldKey = String(req.productUploadKey);
        const newKey = String(created.id);
        const oldDir = path.join(PRODUCT_UPLOAD_ROOT, oldKey);
        const newDir = path.join(PRODUCT_UPLOAD_ROOT, newKey);

        if (fs.existsSync(oldDir)) {
          fs.mkdirSync(path.dirname(newDir), { recursive: true });
          try {
            fs.renameSync(oldDir, newDir);
          } catch {
            // If rename fails, leave files where they are; URLs will still work.
          }
        }

        const images = (data.images || req.productImages).map((url) =>
          String(url).replace(`/uploads/products/${oldKey}/`, `/uploads/products/${newKey}/`),
        );
        const thumbnail = data.thumbnail || images[0] || null;

        await productService.updateProduct(created.id, {
          images,
          thumbnail,
        });
      }

      return res.redirect('/admin/products');
    } catch (err) {
      const message =
        err && err.message
          ? err.message
          : 'Could not create product. Please try again.';
      return res.render('products/form', {
        layout: 'layouts/admin',
        pageTitle: 'New Product',
        activePage: 'products',
        product: req.body,
        PRODUCT_CATEGORIES,
        GENDER_OPTIONS,
        isEdit: false,
        error: message,
      });
    }
  } catch (err) {
    next(err);
  }
}

/* ════════════════════════════════════════════════════════════
   EDIT (GET) — GET /admin/products/:id/edit
   ════════════════════════════════════════════════════════════ */

async function getEditProduct(req, res, next) {
  try {
    const product = await productService.getProductById(req.params.id, {
      admin: true,
    });
    if (!product) {
      return res.redirect('/admin/products');
    }

    res.render('products/form', {
      layout: 'layouts/admin',
      pageTitle: 'Edit Product',
      activePage: 'products',
      product,
      PRODUCT_CATEGORIES,
      GENDER_OPTIONS,
      isEdit: true,
      error: null,
    });
  } catch (err) {
    next(err);
  }
}

/* ════════════════════════════════════════════════════════════
   UPDATE (POST) — POST /admin/products/:id
   ════════════════════════════════════════════════════════════ */

async function postUpdateProduct(req, res, next) {
  try {
    const { id } = req.params;
    const existing = await productService.getProductById(id, { admin: true });
    if (!existing) {
      return res.redirect('/admin/products');
    }

    const {
      name,
      slug,
      price,
      comparePrice,
      description,
      shortDescription,
      category,
      gender,
      tags,
      sizes,
      totalStock,
      featured,
      isActive,
      sortOrder,
    } = req.body;

    if (!name || !price || !category || !gender) {
      return res.render('products/form', {
        layout: 'layouts/admin',
        pageTitle: 'Edit Product',
        activePage: 'products',
        product: { ...existing, ...req.body },
        PRODUCT_CATEGORIES,
        GENDER_OPTIONS,
        isEdit: true,
        error: 'Name, price, category and gender are required.',
      });
    }

    const numericPrice = Number(price);
    if (!Number.isFinite(numericPrice) || numericPrice < 0) {
      return res.render('products/form', {
        layout: 'layouts/admin',
        pageTitle: 'Edit Product',
        activePage: 'products',
        product: { ...existing, ...req.body },
        PRODUCT_CATEGORIES,
        GENDER_OPTIONS,
        isEdit: true,
        error: 'Price must be a non-negative number.',
      });
    }

    const data = {
      name: name.trim(),
      slug: slug ? slugify(slug) : slugify(name),
      price: numericPrice,
      comparePrice:
        comparePrice && comparePrice !== ''
          ? Number(comparePrice)
          : null,
      description,
      shortDescription,
      category,
      gender,
      tags: parseTags(tags),
      sizes: parseSizes(sizes),
      totalStock:
        totalStock && totalStock !== ''
          ? Number.parseInt(totalStock, 10)
          : 0,
      featured: normaliseCheckbox(featured),
      isActive: normaliseCheckbox(isActive),
      sortOrder:
        sortOrder && sortOrder !== ''
          ? Number.parseInt(sortOrder, 10)
          : 0,
    };

    // Merge uploaded images (append to existing)
    if (Array.isArray(req.productImages) && req.productImages.length) {
      const existingImages = Array.isArray(existing.images)
        ? existing.images
        : [];
      data.images = [...existingImages, ...req.productImages];
      data.thumbnail = existing.thumbnail || req.productImages[0];
    }

    try {
      await productService.updateProduct(id, data);
      return res.redirect('/admin/products');
    } catch (err) {
      const message =
        err && err.message
          ? err.message
          : 'Could not update product. Please try again.';
      return res.render('products/form', {
        layout: 'layouts/admin',
        pageTitle: 'Edit Product',
        activePage: 'products',
        product: { ...existing, ...req.body },
        PRODUCT_CATEGORIES,
        GENDER_OPTIONS,
        isEdit: true,
        error: message,
      });
    }
  } catch (err) {
    next(err);
  }
}

/* ════════════════════════════════════════════════════════════
   DELETE — POST /admin/products/:id/delete
   Soft delete by setting is_active = false.
   For hard delete, use productService.deleteProduct(id) instead.
   ════════════════════════════════════════════════════════════ */

async function postDeleteProduct(req, res, next) {
  try {
    const { id } = req.params;
    await productService.updateProduct(id, { isActive: false });
    res.redirect('/admin/products');
  } catch (err) {
    next(err);
  }
}

module.exports = {
  getProducts,
  getCreateProduct,
  postCreateProduct,
  getEditProduct,
  postUpdateProduct,
  postDeleteProduct,
};

