-- Per-event distance options with optional route map images (shown on event details).
CREATE TABLE `event_distance_routes` (
  `id` char(36) NOT NULL,
  `event_id` char(36) NOT NULL,
  `label` varchar(80) NOT NULL,
  `route_image` varchar(500) DEFAULT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_event_distance_routes_event` (`event_id`),
  CONSTRAINT `fk_event_distance_routes_event`
    FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
