DROP TABLE IF EXISTS `zz_users_crest`;
CREATE TABLE `zz_users_crest` (
	`userID` INT(11) NOT NULL DEFAULT '0',
	`characterID` INT(11) NOT NULL DEFAULT '0',
	`characterName` VARCHAR(256) NULL DEFAULT NULL COLLATE 'utf8_bin',
	`scopes` VARCHAR(256) NULL DEFAULT NULL COLLATE 'utf8_bin',
	`tokenType` VARCHAR(256) NULL DEFAULT NULL COLLATE 'utf8_bin',
	`characterOwnerHash` VARCHAR(256) NULL DEFAULT NULL COLLATE 'utf8_bin',
	`corporationID` INT(11) NULL DEFAULT NULL,
	`corporationName` VARCHAR(256) NULL DEFAULT NULL COLLATE 'utf8_bin',
	`corporationTicker` VARCHAR(256) NULL DEFAULT NULL COLLATE 'utf8_bin',
	`allianceID` INT(11) NULL DEFAULT NULL,
	`allianceName` VARCHAR(256) NULL DEFAULT NULL COLLATE 'utf8_bin',
	`allianceTicker` VARCHAR(256) NULL DEFAULT NULL COLLATE 'utf8_bin',
	PRIMARY KEY (`characterID`)
)
COLLATE='utf8_bin'
ENGINE=InnoDB
;
