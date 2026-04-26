-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 12, 2026 at 09:13 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lfs_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `albums`
--

CREATE TABLE `albums` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `title` varchar(500) NOT NULL,
  `description` text DEFAULT '',
  `category` varchar(100) DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `location` varchar(255) DEFAULT '',
  `event` varchar(255) DEFAULT '',
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '[]' CHECK (json_valid(`tags`)),
  `cover_image` varchar(500) DEFAULT NULL,
  `external_url` varchar(500) DEFAULT NULL,
  `media_count` int(10) UNSIGNED DEFAULT 0,
  `featured` tinyint(1) DEFAULT 0,
  `homepage_slider` tinyint(1) DEFAULT 0,
  `event_highlight` tinyint(1) DEFAULT 0,
  `sort_priority` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `albums`
--

INSERT INTO `albums` (`id`, `title`, `description`, `category`, `date`, `location`, `event`, `tags`, `cover_image`, `external_url`, `media_count`, `featured`, `homepage_slider`, `event_highlight`, `sort_priority`, `created_at`, `updated_at`) VALUES
('f4b7f320-1d80-11f1-900f-3822e21845b6', 'LSD', '', 'Race', '2026-02-20 00:00:00', 'Lusaka', 'LSD', '[]', '/uploads/gallery/covers/cover_f29aa9b0dc2cc99d6f83a3aae5a3e609af692d00.jpg', 'https://thecontentfactory.pixieset.com/21022026lsd/', 1, 1, 1, 0, 0, '2026-03-11 19:31:52', '2026-03-11 19:32:42');

-- --------------------------------------------------------

--
-- Table structure for table `blog_posts`
--

CREATE TABLE `blog_posts` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` longtext DEFAULT '',
  `featured_image` varchar(500) DEFAULT '',
  `author_id` char(36) DEFAULT NULL,
  `category` varchar(50) NOT NULL,
  `published` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT '',
  `message` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'New',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `description` text DEFAULT '',
  `location` varchar(255) DEFAULT '',
  `event_date` datetime NOT NULL,
  `distance` varchar(50) DEFAULT '',
  `recurrence_type` varchar(20) NOT NULL DEFAULT 'none',
  `recurrence_days` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT '',
  `registration_open` datetime DEFAULT NULL,
  `registration_close` datetime DEFAULT NULL,
  `registration_type` varchar(20) NOT NULL DEFAULT 'open',
  `banner_image` varchar(500) DEFAULT NULL,
  `feature_on_home` tinyint(1) NOT NULL DEFAULT 0,
  `brochure_pdf` varchar(500) DEFAULT NULL,
  `created_by` char(36) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `slug`, `description`, `location`, `event_date`, `distance`, `recurrence_type`, `recurrence_days`, `category`, `registration_open`, `registration_close`, `registration_type`, `banner_image`, `created_by`, `created_at`, `updated_at`) VALUES
('069e16c4-1d90-11f1-811d-3822e21845b6', 'LFS Saturday', 'lfs-saturday', 'Thanks', 'Hosted by a different satellite group each week.', '2026-03-14 20:17:00', '10K', 'weekly', 'saturday', 'LSD', '2026-03-11 20:17:00', '2026-03-13 20:18:00', 'members', '/images/events/event-f29aa9b0dc2cc99d6f83a3aae5a3e609af692d00.jpg', NULL, '2026-03-11 21:19:44', '2026-03-11 21:25:14');

-- --------------------------------------------------------

--
-- Table structure for table `event_distance_routes`
--

CREATE TABLE `event_distance_routes` (
  `id` char(36) NOT NULL,
  `event_id` char(36) NOT NULL,
  `label` varchar(80) NOT NULL,
  `route_image` varchar(500) DEFAULT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_event_distance_routes_event` (`event_id`),
  CONSTRAINT `fk_event_distance_routes_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_registrations`
--

CREATE TABLE `event_registrations` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `event_id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `bib_number` varchar(50) DEFAULT '',
  `status` varchar(30) NOT NULL DEFAULT 'Registered',
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending',
  `registered_at` datetime DEFAULT current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_results`
--

CREATE TABLE `event_results` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `event_id` char(36) NOT NULL,
  `runner_name` varchar(255) NOT NULL,
  `position` int(10) UNSIGNED NOT NULL,
  `time` varchar(20) NOT NULL DEFAULT '',
  `category` varchar(100) DEFAULT '',
  `club` varchar(255) DEFAULT '',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `category` varchar(100) DEFAULT '',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gallery_settings`
--

CREATE TABLE `gallery_settings` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `banner_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gallery_settings`
--

INSERT INTO `gallery_settings` (`id`, `banner_image`) VALUES
(1, '/images/gallery/gallery-f29aa9b0dc2cc99d6f83a3aae5a3e609af692d00.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `media`
--

CREATE TABLE `media` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `album_id` char(36) NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `stored_name` varchar(255) DEFAULT NULL,
  `type` enum('photo','video') NOT NULL,
  `mimetype` varchar(100) DEFAULT NULL,
  `size` bigint(20) DEFAULT NULL,
  `urls` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '{}' CHECK (json_valid(`urls`)),
  `caption` text DEFAULT '',
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '[]' CHECK (json_valid(`tags`)),
  `featured` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `homepage_slider` tinyint(1) NOT NULL DEFAULT 0,
  `event_highlight` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `media`
--

INSERT INTO `media` (`id`, `album_id`, `filename`, `stored_name`, `type`, `mimetype`, `size`, `urls`, `caption`, `tags`, `featured`, `sort_order`, `created_at`, `updated_at`, `homepage_slider`, `event_highlight`) VALUES
('128a7a3c-1d81-11f1-900f-3822e21845b6', 'f4b7f320-1d80-11f1-900f-3822e21845b6', 'cover_f29aa9b0dc2cc99d6f83a3aae5a3e609af692d00.jpg', 'cover_f29aa9b0dc2cc99d6f83a3aae5a3e609af692d00.jpg', 'photo', NULL, NULL, '{\"original\":\"/uploads/gallery/covers/cover_f29aa9b0dc2cc99d6f83a3aae5a3e609af692d00.jpg\",\"large\":\"/uploads/gallery/covers/cover_f29aa9b0dc2cc99d6f83a3aae5a3e609af692d00.jpg\",\"medium\":\"/uploads/gallery/covers/cover_f29aa9b0dc2cc99d6f83a3aae5a3e609af692d00.jpg\",\"thumbnail\":\"/uploads/gallery/covers/cover_f29aa9b0dc2cc99d6f83a3aae5a3e609af692d00.jpg\"}', 'LSD cover', '[]', 1, 0, '2026-03-11 19:32:42', '2026-03-11 20:12:45', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `user_id` char(36) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending',
  `order_status` varchar(20) NOT NULL DEFAULT 'Pending',
  `pickup_location` varchar(255) DEFAULT '',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `order_id` char(36) NOT NULL,
  `product_id` char(36) NOT NULL,
  `size` varchar(20) DEFAULT '',
  `quantity` int(10) UNSIGNED NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `compare_price` decimal(10,2) DEFAULT NULL,
  `description` text DEFAULT '',
  `short_description` text DEFAULT '',
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '[]' CHECK (json_valid(`images`)),
  `thumbnail` varchar(500) DEFAULT '/images/products/placeholder.webp',
  `category` varchar(50) NOT NULL,
  `gender` varchar(20) NOT NULL DEFAULT 'unisex',
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '[]' CHECK (json_valid(`tags`)),
  `sizes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '[]' CHECK (json_valid(`sizes`)),
  `total_stock` int(10) UNSIGNED DEFAULT 0,
  `featured` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `albums`
--
ALTER TABLE `albums`
  ADD PRIMARY KEY (`id`),
  ADD KEY `albums_date` (`date`),
  ADD KEY `albums_created_at` (`created_at`),
  ADD KEY `albums_featured` (`featured`),
  ADD KEY `albums_category` (`category`);

--
-- Indexes for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `blog_posts_slug` (`slug`),
  ADD KEY `blog_posts_author_id` (`author_id`),
  ADD KEY `blog_posts_category` (`category`),
  ADD KEY `blog_posts_published` (`published`),
  ADD KEY `blog_posts_created_at` (`created_at`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contact_messages_status` (`status`),
  ADD KEY `contact_messages_created_at` (`created_at`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_events_slug` (`slug`),
  ADD KEY `events_event_date` (`event_date`),
  ADD KEY `events_category` (`category`),
  ADD KEY `events_created_at` (`created_at`);

--
-- Indexes for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_user` (`event_id`,`user_id`),
  ADD KEY `event_registrations_event_id` (`event_id`),
  ADD KEY `event_registrations_user_id` (`user_id`),
  ADD KEY `event_registrations_status` (`status`),
  ADD KEY `event_registrations_registered_at` (`registered_at`);

--
-- Indexes for table `event_results`
--
ALTER TABLE `event_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_results_event_id` (`event_id`),
  ADD KEY `event_results_position` (`event_id`,`position`),
  ADD KEY `event_results_category` (`category`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `faqs_category` (`category`),
  ADD KEY `faqs_created_at` (`created_at`);

--
-- Indexes for table `gallery_settings`
--
ALTER TABLE `gallery_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `media`
--
ALTER TABLE `media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `media_album_id` (`album_id`),
  ADD KEY `media_created_at` (`created_at`),
  ADD KEY `media_type` (`type`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `orders_user_id` (`user_id`),
  ADD KEY `orders_order_status` (`order_status`),
  ADD KEY `orders_created_at` (`created_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_items_order_id` (`order_id`),
  ADD KEY `order_items_product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `products_slug` (`slug`),
  ADD KEY `products_is_active` (`is_active`),
  ADD KEY `products_category` (`category`),
  ADD KEY `products_created_at` (`created_at`),
  ADD KEY `products_price` (`price`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD CONSTRAINT `event_registrations_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_results`
--
ALTER TABLE `event_results`
  ADD CONSTRAINT `event_results_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `media`
--
ALTER TABLE `media`
  ADD CONSTRAINT `media_ibfk_1` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
