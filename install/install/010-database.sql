-- ============================================================================
-- Zolinga RMS: Rights Management System core schema.
-- All DDL is idempotent (IF NOT EXISTS / DROP TRIGGER IF EXISTS).
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Users
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rmsUsers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary Key: Unique RMS User ID.',
  `username` varchar(128) NOT NULL COMMENT 'User login e/mail.',
  `password` varchar(1024) DEFAULT NULL COMMENT 'Password hash.',
  `lang` char(5) NOT NULL DEFAULT 'en_US',
  `removed` int(10) unsigned DEFAULT 0 COMMENT 'Date and time the registry item was removed.',
  `canLogin` tinyint(1) DEFAULT 1 COMMENT 'User can login.',
  `created` int(10) unsigned DEFAULT NULL COMMENT 'Auto updated by trigger. Date and time the registry item was created.',
  `modified` int(10) unsigned DEFAULT NULL COMMENT 'Auto updated by trigger. Date and time the registry item was last modified.',
  `lastLogin` int(10) unsigned DEFAULT NULL COMMENT 'Date and time of last login.',
  `lastLoginFrom` varchar(255) DEFAULT NULL COMMENT 'IP address of last login.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rmsUsers_UNIQUE` (`username`,`removed`),
  KEY `rmsUsers_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Stores configuration data in a key-value pair format.';

-- ----------------------------------------------------------------------------
-- Commands (rights tokens)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rmsCommands` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `command` varchar(1024) NOT NULL,
  `hash` binary(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash_UNIQUE` (`hash`),
  UNIQUE KEY `command_UNIQUE` (`command`) USING HASH
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- User rights (user x command join)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rmsRights` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned NOT NULL,
  `commandHash` binary(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rmsRights_UNIQUE` (`userId`,`commandHash`),
  KEY `fk_rmsRights_user_idx` (`userId`),
  KEY `fk_rmsRights_2` (`commandHash`),
  CONSTRAINT `fk_rmsRights_1` FOREIGN KEY (`userId`) REFERENCES `rmsUsers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rmsRights_2` FOREIGN KEY (`commandHash`) REFERENCES `rmsCommands` (`hash`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- User metadata (key-value store per user)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rmsMeta` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned NOT NULL,
  `prop` varchar(255) NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `userId_UNIQUE` (`userId`,`prop`),
  KEY `fk_rmsMeta_1_idx` (`userId`),
  CONSTRAINT `fk_rmsMeta_1` FOREIGN KEY (`userId`) REFERENCES `rmsUsers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

====================================================================
-- Triggers
-- ============================================================================
DROP TRIGGER IF EXISTS `before_update_rmsUsers`;

DELIMITER ;;
CREATE TRIGGER `before_update_rmsUsers`
BEFORE UPDATE ON rmsUsers
FOR EACH ROW
SET 
NEW.modified = UNIX_TIMESTAMP(),
NEW.created = IFNULL(OLD.created, UNIX_TIMESTAMP()) ;;
