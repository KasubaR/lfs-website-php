/* ============================================================
   LFS — Lusaka Fitness Squad
   routes/contact.routes.js — Contact page & form submission

   Route map (mounted at /contact):
     GET  /contact  → contact page
     POST /contact  → contact form submission
   ============================================================ */

'use strict';

const express = require('express');
const router = express.Router();

/* ════════════════════════════════════════════════════════════
   GET /
   Render contact page.
   ════════════════════════════════════════════════════════════ */
router.get('/', (req, res) => {
    res.render('pages/contact-us', {
        title: 'Contact Us',
        description: 'Get in touch with LFS — Lusaka Fitness Squad. Contact us for membership, events, or find your nearest satellite captain.',
        page: 'contact',
        submitted: req.query.submitted === 'true',
    });
});

/* ════════════════════════════════════════════════════════════
   POST /
   Handle contact form submission.
   ════════════════════════════════════════════════════════════ */
router.post('/', async (req, res) => {
    const { firstName, lastName, email, phone, satellite, message } = req.body;

    const errors = [];
    if (!firstName?.trim()) errors.push('First name is required.');
    if (!lastName?.trim()) errors.push('Last name is required.');
    if (!email?.trim()) errors.push('Email address is required.');
    if (!message?.trim()) errors.push('Message is required.');

    if (errors.length) {
        return res.status(422).json({ success: false, errors });
    }

    const submission = {
        name: `${firstName.trim()} ${lastName.trim()}`,
        email: email.trim().toLowerCase(),
        phone: phone?.trim() || '',
        satellite: satellite?.trim() || 'Not specified',
        message: message.trim(),
        timestamp: new Date().toISOString(),
        ip: req.ip,
    };

    console.log('[LFS Contact Form]', submission);

    if (req.headers['content-type']?.includes('application/json')) {
        return res.json({ success: true, message: 'Your message has been sent. We\'ll be in touch!' });
    }

    res.redirect('/contact?submitted=true');
});

module.exports = router;
