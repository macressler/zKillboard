--



DROP TABLE IF EXISTS `zz_social`;

CREATE TABLE `zz_social` (

  `killID` bigint(16) NOT NULL,

  `insertTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`killID`)

) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=COMPRESSED;



--

-- Table structure for table `zz_stats`
