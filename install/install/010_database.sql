create table rmsUsers (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary Key: Unique RMS User ID.',
    `username` VARCHAR(128) NOT NULL COMMENT 'User login e/mail.',
    `password` VARCHAR(1024) DEFAULT NULL COMMENT 'Password hash.',
    `lang` VARCHAR(5) DEFAULT 'en_US' COMMENT 'User language.',
    `removed` INT(10) UNSIGNED DEFAULT 0 COMMENT 'Date and time the registry item was removed.', 
    `canLogin` BOOLEAN DEFAULT 1 COMMENT 'User can login.',
    `created` INT(10) UNSIGNED DEFAULT NULL COMMENT 'Auto updated by trigger. Date and time the registry item was created.',
    `modified` INT(10) UNSIGNED DEFAULT NULL COMMENT 'Auto updated by trigger. Date and time the registry item was last modified.',
    `lastLogin` INT(10) UNSIGNED DEFAULT NULL COMMENT 'Date and time of last login.',
    `lastLoginFrom` VARCHAR(39) DEFAULT NULL COMMENT 'IP address of last login.',
    PRIMARY KEY (`id`),
    INDEX `rmsUsers_username` (`username`),
    UNIQUE KEY `rmsUsers_UNIQUE` (`username`, `removed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Stores configuration data in a key-value pair format.';


CREATE TRIGGER before_update_rmsUsers
BEFORE UPDATE ON rmsUsers
FOR EACH ROW
SET 
NEW.modified = UNIX_TIMESTAMP(),
NEW.created = IFNULL(OLD.created, UNIX_TIMESTAMP());

CREATE TABLE `rmsCommands` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `command` varchar(1024) NOT NULL,
  `hash` binary(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash_UNIQUE` (`hash`),
  UNIQUE KEY `command_UNIQUE` (`command`) USING HASH
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `rmsRights` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `userId` INT UNSIGNED NOT NULL,
  `commandHash` BINARY(20) NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_rmsRights_user_idx` (`userId` ASC),
  UNIQUE KEY `rmsRights_UNIQUE` (`userId` ASC, `commandHash` ASC),
  CONSTRAINT `fk_rmsRights_1`
    FOREIGN KEY (`userId`)
    REFERENCES `rmsUsers` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_rmsRights_2`
    FOREIGN KEY (`commandHash`)
    REFERENCES `rmsCommands` (`hash`)
    ON DELETE CASCADE
    ON UPDATE CASCADE);

CREATE TABLE `rmsMeta` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned NOT NULL,
  `prop` varchar(255) NOT NULL,
  `data` JSON NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `userId_UNIQUE` (`userId`,`prop`),
  KEY `fk_rmsMeta_1_idx` (`userId`),
  CONSTRAINT `fk_rmsMeta_1` FOREIGN KEY (`userId`) REFERENCES `rmsUsers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

