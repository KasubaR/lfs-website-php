/* ============================================================
   LFS — Lusaka Fitness Squad
   routes/index.js — Main page routes

   Route map:
     GET  /  → home page
   ============================================================ */

'use strict';

const path = require('path');
const fs = require('fs');
const express = require('express');
const router = express.Router();
const galleryService = require('../services/gallery.service');
const eventService = require('../services/event.service');
const productService = require('../services/product.service');
const { formatPrice } = require('../utility/helpers');

/** Fallback album folder for gallery preview when Supabase is down or has no media. */
const GALLERY_PREVIEW_FALLBACK_FOLDER = '21.02.2026-LSD';
const GALLERY_PREVIEW_FALLBACK_PATH = path.join(__dirname, '..', '..', 'public', 'images', GALLERY_PREVIEW_FALLBACK_FOLDER);
const IMAGE_EXTENSIONS = new Set(['.webp', '.jpg', '.jpeg', '.png']);
const HOMEPAGE_PREVIEW_LIMIT = 6;

/**
 * Read photos from the fallback album folder on disk (public/images/21.02.2026-LSD).
 * Returns array of { urls: { medium, large, original }, albumId: '', caption }.
 */
function getHomepageFallbackMedia() {
    if (!fs.existsSync(GALLERY_PREVIEW_FALLBACK_PATH) || !fs.statSync(GALLERY_PREVIEW_FALLBACK_PATH).isDirectory()) {
        return [];
    }
    const baseUrl = `/images/${GALLERY_PREVIEW_FALLBACK_FOLDER}`;
    const files = fs.readdirSync(GALLERY_PREVIEW_FALLBACK_PATH)
        .filter((f) => IMAGE_EXTENSIONS.has(path.extname(f).toLowerCase()))
        .sort()
        .slice(0, HOMEPAGE_PREVIEW_LIMIT);
    return files.map((file) => ({
        urls: { medium: `${baseUrl}/${file}`, large: `${baseUrl}/${file}`, original: `${baseUrl}/${file}` },
        albumId: '',
        caption: 'LFS — 21.02.2026 LSD',
    }));
}

/* ════════════════════════════════════════════════════════════
   SAMPLE DATA
   Replace with real DB queries / CMS API calls as the project
   matures. Keeping data here makes the controller easy to swap.
   ════════════════════════════════════════════════════════════ */

/** Map event category to home page tag color (for event cards). */
function eventTagColor(category) {
    const map = { 'LSD': 'green', 'Road Race': 'orange', 'Training': 'red', 'Training Camp': 'red', 'Social': 'gold', 'Other': '' };
    return map[category] || '';
}

/** Map eventService event to home view shape { title, date, location, distance, tag, tagColor, link }. */
function mapEventForHome(e) {
    const d = e.eventDate ? new Date(e.eventDate) : null;
    const dateStr = d ? `${d.toLocaleDateString('en-GB', { weekday: 'short' })}, ${d.getDate()} ${d.toLocaleDateString('en-GB', { month: 'long' })} ${d.getFullYear()}` : 'TBA';
    return {
        title: e.title,
        date: dateStr,
        location: e.location || 'TBA',
        distance: e.distance || '—',
        tag: e.category || 'Event',
        tagColor: eventTagColor(e.category),
        link: '/events/' + (e.slug || e.id),
    };
}

const PRODUCTS = [
    {
        name: 'LFS Running Jersey',
        sub: 'Official Club Kit',
        price: 'K350',
        badge: 'Bestseller',
        badgeColor: 'gold',
        image: 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?q=80&w=800&auto=format&fit=crop',
    },
    {
        name: 'LFS Branded Cap',
        sub: 'Headwear',
        price: 'K180',
        badge: 'New',
        badgeColor: 'green',
        image: 'https://images.unsplash.com/photo-1575428652377-a2d80e2277fc?q=80&w=800&auto=format&fit=crop',
    },
    {
        name: 'LFS Running Shorts',
        sub: 'Performance Wear',
        price: 'K280',
        badge: null,
        badgeColor: null,
        image: 'https://images.unsplash.com/photo-1518314916381-77a37c2a49ae?q=80&w=800&auto=format&fit=crop',
    },
    {
        name: 'Full Kit Bundle',
        sub: 'Jersey + Cap + Shorts',
        price: 'K750',
        badge: 'Bundle',
        badgeColor: 'orange',
        image: 'https://images.unsplash.com/photo-1556905055-8f358a7a47b2?q=80&w=800&auto=format&fit=crop',
    },
];

const POSTS = [
    {
        title: 'LFS Closes 2024 With Record Participation',
        excerpt: 'Over 500 runners crossed the finish line at our Year-End Fun Run — the biggest turnout in LFS history.',
        date: 'Dec 10, 2024',
        category: 'News',
        image: 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?q=80&w=800&auto=format&fit=crop',
        link: '/news/year-end-2024',
    },
    {
        title: '2025 Membership Is Now Open',
        excerpt: 'The 2025 membership window is officially open. Renew or register before end of April to stay connected.',
        date: 'Jan 3, 2025',
        category: 'Membership',
        image: 'https://images.unsplash.com/photo-1552674605-d1f74c4f719b?q=80&w=800&auto=format&fit=crop',
        link: '/news/membership-2025',
    },
    {
        title: 'New Satellite Captains Announced for 2025',
        excerpt: 'LFS leadership has confirmed satellite captains across all six locations for the new season. Meet your new captains.',
        date: 'Jan 15, 2025',
        category: 'Community',
        image: 'https://images.unsplash.com/photo-1517649763962-0c623066013b?q=80&w=800&auto=format&fit=crop',
        link: '/news/captains-2025',
    },
];

/* ════════════════════════════════════════════════════════════
   GET /
   ════════════════════════════════════════════════════════════ */
router.get('/', async (_req, res, next) => {
    const [galleryResult, eventsResult, productsResult] = await Promise.allSettled([
        galleryService.getHomepageMedia(HOMEPAGE_PREVIEW_LIMIT),
        eventService.getUpcomingEvents(5),
        productService.getProducts({ limit: 8 }, { admin: false }),
    ]);

    let galleryPreview = galleryResult.status === 'fulfilled' ? galleryResult.value : [];
    if (galleryPreview.length === 0) {
        galleryPreview = getHomepageFallbackMedia();
    }

    let events = eventsResult.status === 'fulfilled'
        ? eventsResult.value.map(mapEventForHome)
        : [];

    let homeProducts = [];
    if (productsResult.status === 'fulfilled' && Array.isArray(productsResult.value) && productsResult.value.length) {
        homeProducts = productsResult.value.map((prod) => {
            const priceLabel = formatPrice ? formatPrice(prod.price) : prod.price;
            let image = prod.thumbnail || '';
            if ((!image || image === '/images/products/placeholder.webp') && Array.isArray(prod.images) && prod.images.length) {
                const first = prod.images[0];
                image = typeof first === 'string' ? first : first.url || first.src || image;
            }
            if (!image) {
                image = 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?q=80&w=2124&auto=format&fit=crop';
            }
            return {
                name:  prod.name,
                sub:   prod.shortDescription || prod.description || '',
                price: priceLabel,
                badge: prod.featured ? 'Featured' : null,
                badgeColor: prod.featured ? 'gold' : null,
                image,
            };
        });
    }
    if (!homeProducts.length) {
        homeProducts = PRODUCTS;
    }

    const heroImage = '/images/LSD07.02.2026-3.jpg';
    res.render('pages/home', {
        title: 'Home',
        description: 'Zambia\'s biggest running community. Train. Run. Compete. Together. Join LFS today.',
        page: 'home',
        events,
        products: homeProducts,
        posts: POSTS,
        galleryPreview,
        styles: '<link rel="preload" as="image" href="' + heroImage + '"><link rel="stylesheet" href="/css/home.css">',
        scripts: '<script src="/js/home.js" defer></script>',
    });
});

/* ════════════════════════════════════════════════════════════
   GET /events
   ════════════════════════════════════════════════════════════ */

// Events are served from Supabase via eventService — see GET /events below.
// To seed initial data, use the admin panel at /admin/events.

router.get('/events', async (req, res, next) => {
    try {
        const events = await eventService.getEvents({ limit: 100 });
        res.render('pages/events', {
            title: 'Events & Races',
            description: 'Upcoming and past LFS events — road races, LSD runs, training camps, and community events in Lusaka, Zambia.',
            page: 'events',
            events,
            styles:  '<link rel="stylesheet" href="/css/events.css">',
            scripts: '<script src="/js/events.js"></script>',
        });
    } catch (err) {
        next(err);
    }
});

/* ════════════════════════════════════════════════════════════
   GET /events/:slug
   ════════════════════════════════════════════════════════════ */
router.get('/events/:slug', async (req, res, next) => {
    try {
        const event = await eventService.getEventBySlug(req.params.slug);
        if (!event) return res.redirect('/events');
        res.render('pages/event-details', {
            title: event.title,
            description: event.description || '',
            page: 'events',
            event,
            styles:  '<link rel="stylesheet" href="/css/events.css">',
            scripts: '<script src="/js/events.js"></script>',
        });
    } catch (err) {
        next(err);
    }
});

/* ════════════════════════════════════════════════════════════
   GET /about
   ════════════════════════════════════════════════════════════ */
router.get('/about', async (_req, res, next) => {
    try {
        const galleryResult = await galleryService.getHomepageMedia(HOMEPAGE_PREVIEW_LIMIT);
        let galleryPreview = galleryResult || [];
        if (!Array.isArray(galleryPreview)) galleryPreview = [];
        if (galleryPreview.length === 0) {
            galleryPreview = getHomepageFallbackMedia();
        }

        res.render('pages/about', {
            title: 'About',
            description: 'Learn about Lusaka Fitness Squad — Zambia\'s biggest running community. Our history, mission, values, leadership, and satellites.',
            page: 'about',
            galleryPreview,
        });
    } catch (err) {
        next(err);
    }
});

module.exports = router;