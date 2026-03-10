-- ============================================================
-- LFS — Lusaka Fitness Squad
-- Supabase schema: run this in your project's SQL Editor
-- (Dashboard → SQL Editor → New query → paste → Run)
-- ============================================================

-- ── GALLERY: albums ─────────────────────────────────────────
create table if not exists public.albums (
  id uuid primary key default gen_random_uuid(),
  title text not null,
  description text default '',
  category text,
  date timestamptz,
  location text default '',
  event text default '',
  tags jsonb default '[]',
  cover_image text,
  external_url text,
  media_count integer default 0 check (media_count >= 0),
  featured boolean default false,
  homepage_slider boolean default false,
  event_highlight boolean default false,
  sort_priority integer default 0,
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

create index if not exists albums_date on public.albums(date desc);
create index if not exists albums_created_at on public.albums(created_at desc);
create index if not exists albums_featured on public.albums(featured);
create index if not exists albums_category on public.albums(category);

-- ── GALLERY: media ──────────────────────────────────────────
create table if not exists public.media (
  id uuid primary key default gen_random_uuid(),
  album_id uuid not null references public.albums(id) on delete cascade,
  filename text,
  stored_name text,
  type text not null check (type in ('photo', 'video')),
  mimetype text,
  size bigint,
  urls jsonb default '{}',
  caption text default '',
  tags jsonb default '[]',
  featured boolean default false,
  sort_order integer default 0,
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

create index if not exists media_album_id on public.media(album_id);
create index if not exists media_created_at on public.media(created_at desc);
create index if not exists media_type on public.media(type);

-- ── SHOP: products ───────────────────────────────────────────
create table if not exists public.products (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  slug text not null unique,
  price numeric not null check (price >= 0),
  compare_price numeric check (compare_price is null or compare_price >= 0),
  description text default '',
  short_description text default '',
  images jsonb default '[]',
  thumbnail text default '/images/products/placeholder.webp',
  category text not null check (category in ('running-kits','t-shirts','caps','shorts','accessories','other')),
  gender text not null default 'unisex' check (gender in ('male','female','unisex')),
  tags jsonb default '[]',
  sizes jsonb default '[]',
  total_stock integer default 0 check (total_stock >= 0),
  featured boolean default false,
  is_active boolean default true,
  sort_order integer default 0,
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

create index if not exists products_slug on public.products(slug);
create index if not exists products_is_active on public.products(is_active);
create index if not exists products_category on public.products(category);
create index if not exists products_created_at on public.products(created_at desc);
create index if not exists products_price on public.products(price);

-- ── SHOP: orders ─────────────────────────────────────────────
-- Tracks purchases (total, payment method, status, pickup)
create table if not exists public.orders (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null,
  total_amount numeric not null check (total_amount >= 0),
  payment_method text not null check (payment_method in ('MTN', 'Airtel', 'Bank Transfer', 'Card')),
  payment_status text not null default 'pending' check (payment_status in ('pending', 'paid', 'failed', 'refunded')),
  order_status text not null default 'Pending' check (order_status in ('Pending', 'Paid', 'Processing', 'Completed', 'Cancelled')),
  pickup_location text default '',
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

create index if not exists orders_user_id on public.orders(user_id);
create index if not exists orders_order_status on public.orders(order_status);
create index if not exists orders_created_at on public.orders(created_at desc);

-- ── SHOP: order_items ────────────────────────────────────────
-- Items in each order (product, size, quantity, price at order time)
create table if not exists public.order_items (
  id uuid primary key default gen_random_uuid(),
  order_id uuid not null references public.orders(id) on delete cascade,
  product_id uuid not null references public.products(id) on delete restrict,
  size text default '',
  quantity integer not null check (quantity >= 1),
  price numeric not null check (price >= 0),
  created_at timestamptz default now()
);

create index if not exists order_items_order_id on public.order_items(order_id);
create index if not exists order_items_product_id on public.order_items(product_id);

-- ── EVENTS ───────────────────────────────────────────────────
-- LFS events and races (e.g. Saturday LSD Run, LFS Half Marathon, Training Camp)
create table if not exists public.events (
  id uuid primary key default gen_random_uuid(),
  title text not null,
  description text default '',
  location text default '',
  event_date timestamptz not null,
  distance text default '',
  category text default '',
  registration_open timestamptz,
  registration_close timestamptz,
  banner_image text,
  created_by uuid,
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

create index if not exists events_event_date on public.events(event_date desc);
create index if not exists events_category on public.events(category);
create index if not exists events_created_at on public.events(created_at desc);

-- ── EVENT REGISTRATIONS ─────────────────────────────────────
-- Tracks members registered for events (bib, status, payment)
create table if not exists public.event_registrations (
  id uuid primary key default gen_random_uuid(),
  event_id uuid not null references public.events(id) on delete cascade,
  user_id uuid not null,
  bib_number text default '',
  status text not null default 'Registered' check (status in ('Registered', 'Completed', 'Cancelled')),
  payment_status text not null default 'pending' check (payment_status in ('pending', 'paid', 'refunded', 'free')),
  registered_at timestamptz default now(),
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

create unique index if not exists event_registrations_event_user on public.event_registrations(event_id, user_id);
create index if not exists event_registrations_event_id on public.event_registrations(event_id);
create index if not exists event_registrations_user_id on public.event_registrations(user_id);
create index if not exists event_registrations_status on public.event_registrations(status);
create index if not exists event_registrations_registered_at on public.event_registrations(registered_at desc);

-- ── EVENT RESULTS ───────────────────────────────────────────
-- Race results (position, time, category, club)
create table if not exists public.event_results (
  id uuid primary key default gen_random_uuid(),
  event_id uuid not null references public.events(id) on delete cascade,
  runner_name text not null,
  position integer not null check (position >= 1),
  time text not null default '',  -- e.g. "02:14:32"
  category text default '',
  club text default '',
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

create index if not exists event_results_event_id on public.event_results(event_id);
create index if not exists event_results_position on public.event_results(event_id, position);
create index if not exists event_results_category on public.event_results(category);

-- ── BLOG: blog_posts ─────────────────────────────────────────
-- News and updates (title, slug, content, category, published)
create table if not exists public.blog_posts (
  id uuid primary key default gen_random_uuid(),
  title text not null,
  slug text not null unique,
  content text default '',
  featured_image text default '',
  author_id uuid,
  category text not null check (category in ('Club News', 'Race Reports', 'Training Tips', 'Announcements')),
  published boolean default false,
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

create index if not exists blog_posts_slug on public.blog_posts(slug);
create index if not exists blog_posts_author_id on public.blog_posts(author_id);
create index if not exists blog_posts_category on public.blog_posts(category);
create index if not exists blog_posts_published on public.blog_posts(published);
create index if not exists blog_posts_created_at on public.blog_posts(created_at desc);

-- ── CONTACT: contact_messages ───────────────────────────────
-- Messages from the contact form
create table if not exists public.contact_messages (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  email text not null,
  subject text default '',
  message text not null,
  status text not null default 'New' check (status in ('New', 'Read', 'Responded')),
  created_at timestamptz default now()
);

create index if not exists contact_messages_status on public.contact_messages(status);
create index if not exists contact_messages_created_at on public.contact_messages(created_at desc);

-- ── FAQ ─────────────────────────────────────────────────────
-- Frequently asked questions
create table if not exists public.faqs (
  id uuid primary key default gen_random_uuid(),
  question text not null,
  answer text not null,
  category text default '',
  created_at timestamptz default now()
);

create index if not exists faqs_category on public.faqs(category);
create index if not exists faqs_created_at on public.faqs(created_at desc);

-- Optional: enable RLS but allow service_role full access (your app uses service_role)
alter table public.albums enable row level security;
alter table public.media enable row level security;
alter table public.products enable row level security;
alter table public.orders enable row level security;
alter table public.order_items enable row level security;
alter table public.events enable row level security;
alter table public.event_registrations enable row level security;
alter table public.event_results enable row level security;
alter table public.blog_posts enable row level security;
alter table public.contact_messages enable row level security;
alter table public.faqs enable row level security;

-- Policy: allow all for service_role (server-side key bypasses RLS by default in Supabase)
-- If you need anon/authenticated access later, add policies here.
