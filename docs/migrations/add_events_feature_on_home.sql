-- One event may be marked to show its banner as the first image in the home page hero.
ALTER TABLE `events`
  ADD COLUMN `feature_on_home` TINYINT(1) NOT NULL DEFAULT 0
    AFTER `banner_image`;
