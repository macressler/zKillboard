
DROP TABLE IF EXISTS `zz_killid`;
CREATE TABLE `zz_killid` (
  `killID` int(32) NOT NULL,
  `writ` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`killID`),
  KEY `writ` (`writ`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
