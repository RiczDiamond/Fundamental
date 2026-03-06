-- Migration: add logging columns for payload, hash and dedupe
ALTER TABLE `logs`
  ADD COLUMN `payload` TEXT NULL,
  ADD COLUMN `payload_hash` VARCHAR(64) NULL,
  ADD COLUMN `is_suspicious` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN `duplicate_count` INT NOT NULL DEFAULT 1,
  ADD COLUMN `last_seen` DATETIME NULL,
  ADD INDEX (`is_suspicious`),
  ADD INDEX (`payload_hash`);
