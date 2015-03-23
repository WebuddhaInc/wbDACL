
DROP TABLE IF EXISTS `wbdacl_acl`;
CREATE TABLE IF NOT EXISTS `wbdacl_acl` (
  `acl_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `aro_id` bigint(20) unsigned NOT NULL COMMENT 'ARO ID',
  `aco_id` bigint(20) unsigned NOT NULL COMMENT 'ACO ID',
  `acl_pid` bigint(20) unsigned DEFAULT NULL COMMENT 'Parent ID',
  `acl_rid` bigint(20) unsigned DEFAULT NULL COMMENT 'Root Parent ID',
  `acl_lft` bigint(20) unsigned NOT NULL COMMENT 'Left ID',
  `acl_rgt` bigint(20) unsigned NOT NULL COMMENT 'Right ID',
  `acl_level` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'Level Depth',
  `acl_children` smallint(5) unsigned NOT NULL COMMENT 'Children',
  `acl_key` varchar(255) COLLATE utf8_bin NOT NULL COMMENT 'Control Key',
  `acl_chain` varchar(255) COLLATE utf8_bin NOT NULL COMMENT 'Control Key Chain',
  `acl_rule` enum('allow','deny') COLLATE utf8_bin NOT NULL DEFAULT 'allow' COMMENT 'Control Rule',
  `acl_data` blob COMMENT 'Control Data',
  `acl_status` enum('0','1') COLLATE utf8_bin NOT NULL DEFAULT '1' COMMENT 'Control Status',
  PRIMARY KEY (`acl_id`),
  UNIQUE KEY `UNIQUE` (`aro_id`,`aco_id`,`acl_key`),
  KEY `acl_pid` (`acl_pid`),
  KEY `acl_lft` (`acl_lft`),
  KEY `acl_rgt` (`acl_rgt`),
  KEY `acl_key` (`acl_key`),
  KEY `acl_chain` (`acl_chain`),
  KEY `acl_rule` (`acl_rule`),
  KEY `acl_status` (`acl_status`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `wbdacl_aco`;
CREATE TABLE IF NOT EXISTS `wbdacl_aco` (
  `aco_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `aco_pid` bigint(20) unsigned DEFAULT NULL COMMENT 'Parent ID',
  `aco_rid` bigint(20) unsigned DEFAULT NULL COMMENT 'Root Parent ID',
  `aco_lft` bigint(20) unsigned NOT NULL COMMENT 'Left ID',
  `aco_rgt` bigint(20) unsigned NOT NULL COMMENT 'Right ID',
  `aco_level` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'Level Depth',
  `aco_children` smallint(5) unsigned NOT NULL COMMENT 'Children',
  `aco_key` varchar(255) COLLATE utf8_bin DEFAULT NULL COMMENT 'Object Key',
  `aco_chain` varchar(255) COLLATE utf8_bin NOT NULL COMMENT 'Object Key Chain',
  `aco_label` varchar(255) COLLATE utf8_bin DEFAULT NULL COMMENT 'Object Label',
  `aco_data` blob COMMENT 'Object Data',
  `aco_status` enum('0','1') COLLATE utf8_bin NOT NULL DEFAULT '1' COMMENT 'Object Status',
  PRIMARY KEY (`aco_id`),
  UNIQUE KEY `aco_pid_key` (`aco_pid`,`aco_key`),
  UNIQUE KEY `aco_rid_chain` (`aco_rid`,`aco_chain`),
  KEY `aco_pid` (`aco_pid`),
  KEY `aco_lft` (`aco_lft`),
  KEY `aco_rgt` (`aco_rgt`),
  KEY `aco_key` (`aco_key`),
  KEY `aco_chain` (`aco_chain`),
  KEY `aco_status` (`aco_status`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `wbdacl_aro`;
CREATE TABLE IF NOT EXISTS `wbdacl_aro` (
  `aro_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `aro_pid` bigint(20) unsigned DEFAULT NULL COMMENT 'Parent ID',
  `aro_rid` bigint(20) unsigned DEFAULT NULL COMMENT 'Root Parent ID',
  `aro_lft` bigint(20) unsigned NOT NULL COMMENT 'Left ID',
  `aro_rgt` bigint(20) unsigned NOT NULL COMMENT 'Right ID',
  `aro_level` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'Level Depth',
  `aro_children` smallint(5) unsigned NOT NULL COMMENT 'Children',
  `aro_key` varchar(255) COLLATE utf8_bin DEFAULT NULL COMMENT 'Resource Key',
  `aro_chain` varchar(255) COLLATE utf8_bin NOT NULL COMMENT 'Resource Key Chain',
  `aro_label` varchar(255) COLLATE utf8_bin DEFAULT NULL COMMENT 'Resource Label',
  `aro_data` blob COMMENT 'Resource Data',
  `aro_status` enum('0','1') COLLATE utf8_bin NOT NULL DEFAULT '1' COMMENT 'Resource Status',
  PRIMARY KEY (`aro_id`),
  UNIQUE KEY `aro_pid_key` (`aro_pid`,`aro_key`),
  UNIQUE KEY `aro_rid_chain` (`aro_rid`,`aro_chain`),
  KEY `aro_pid` (`aro_pid`),
  KEY `aro_lft` (`aro_lft`),
  KEY `aro_rgt` (`aro_rgt`),
  KEY `aro_key` (`aro_key`),
  KEY `aro_chain` (`aro_chain`),
  KEY `aro_status` (`aro_status`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
