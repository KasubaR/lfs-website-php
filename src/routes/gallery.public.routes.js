/* ============================================================
   LFS — Lusaka Fitness Squad
   routes/gallery.public.routes.js — Public gallery (no auth)
   
   Mount at /gallery in app.js.
   ============================================================ */

'use strict';

const express = require('express');
const controller = require('../controllers/gallery.public.controller');
const router = express.Router();

router.get('/', controller.getIndex);
router.get('/:id', controller.getAlbum);

module.exports = router;
