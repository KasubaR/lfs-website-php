-- Optional event brochure (PDF) for public download on event details.
ALTER TABLE `events`
  ADD COLUMN `brochure_pdf` varchar(500) DEFAULT NULL
    AFTER `feature_on_home`;
