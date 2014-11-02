
DROP TABLE IF EXISTS `zz_users`;
CREATE TABLE `zz_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(128) NOT NULL,
  `moderator` tinyint(1) NOT NULL,
  `admin` tinyint(1) NOT NULL,
  `password` varchar(64) NOT NULL,
  `autoLoginHash` varchar(256) NOT NULL,
  `email` varchar(128) DEFAULT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revoked` smallint(1) NOT NULL DEFAULT '0',
  `change_hash` varchar(40) DEFAULT NULL,
  `change_expiration` timestamp NULL DEFAULT NULL,
  `revoked_reason` varchar(64) NOT NULL,
  `characterID` INT(11) NULL DEFAULT NULL,
  `merged` INT(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `login_index` (`username`,`password`),
  KEY `revoked` (`revoked`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

