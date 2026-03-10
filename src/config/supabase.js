/* ============================================================
   LFS — Lusaka Fitness Squad
   src/config/supabase.js — Supabase client (server-side)
   
   Uses the service_role key so the app can bypass RLS for
   admin gallery operations. Never expose this key to the client.
   ============================================================ */

'use strict';

const { createClient } = require('@supabase/supabase-js');

const url = process.env.SUPABASE_URL;
const serviceRoleKey = process.env.SUPABASE_SERVICE_ROLE_KEY;

if (!url || !serviceRoleKey) {
  console.warn('[LFS] SUPABASE_URL or SUPABASE_SERVICE_ROLE_KEY missing. Gallery will not work.');
}

const supabase = createClient(url || 'https://placeholder.supabase.co', serviceRoleKey || 'placeholder', {
  auth: { persistSession: false },
});

module.exports = supabase;
