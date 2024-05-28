CREATE TABLE IF NOT EXISTS `rmsMeta` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned NOT NULL,
  `prop` varchar(255) NOT NULL,
  `data` JSON NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `userId_UNIQUE` (`userId`,`prop`),
  KEY `fk_rmsMeta_1_idx` (`userId`),
  CONSTRAINT `fk_rmsMeta_1` FOREIGN KEY (`userId`) REFERENCES `rmsUsers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
