'use strict';

/* ============================================================
   LFS — Lusaka Fitness Squad
   admin/routes/products.routes.js — Product admin routes

   Mount point: /admin/products (from admin.routes.js)

   Responsibilities:
     • List products
     • Create product
     • Edit / update product
     • Delete product

   NOTE: All routes are expected to be protected by admin auth
   middleware higher up in the stack (see admin.routes.js).
   ============================================================ */

const express = require('express');
const router = express.Router();

const productController = require('../controllers/product.controller');
const productImageUpload = require('../middleware/productImageUpload');

/* ════════════════════════════════════════════════════════════
   LIST
   GET /admin/products
   ════════════════════════════════════════════════════════════ */

router.get('/', productController.getProducts);

/* ════════════════════════════════════════════════════════════
   CREATE
   GET /admin/products/create
   POST /admin/products
   ════════════════════════════════════════════════════════════ */

router.get('/create', productController.getCreateProduct);

router.post(
  '/',
  productImageUpload,
  productController.postCreateProduct
);

/* ════════════════════════════════════════════════════════════
   EDIT / UPDATE
   GET /admin/products/:id/edit
   POST /admin/products/:id
   ════════════════════════════════════════════════════════════ */

router.get('/:id/edit', productController.getEditProduct);

router.post(
  '/:id',
  productImageUpload,
  productController.postUpdateProduct
);

/* ════════════════════════════════════════════════════════════
   DELETE
   POST /admin/products/:id/delete
   ════════════════════════════════════════════════════════════ */

router.post('/:id/delete', productController.postDeleteProduct);

module.exports = router;

