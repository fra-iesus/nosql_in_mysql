CREATE TABLE `ois_ai` (
  `class` char(255) COLLATE utf8_czech_ci NOT NULL,
  `ai_name` char(255) COLLATE utf8_czech_ci NOT NULL,
  `ai` bigint(20) UNSIGNED NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `ois_blob` (
  `uid` bigint(20) UNSIGNED NOT NULL,
  `prop_name` char(255) COLLATE utf8_czech_ci NOT NULL,
  `prop_value` longblob
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `ois_datetime` (
  `uid` bigint(20) UNSIGNED NOT NULL,
  `prop_name` char(255) COLLATE utf8_czech_ci NOT NULL,
  `prop_value` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `ois_float` (
  `uid` bigint(20) UNSIGNED NOT NULL,
  `prop_name` char(255) COLLATE utf8_czech_ci NOT NULL,
  `prop_value` double DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `ois_int` (
  `uid` bigint(20) UNSIGNED NOT NULL,
  `prop_name` char(255) COLLATE utf8_czech_ci NOT NULL,
  `prop_value` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `ois_main` (
  `uid` bigint(20) UNSIGNED NOT NULL COMMENT 'Unique ID of Object',
  `class` char(255) COLLATE utf8_czech_ci NOT NULL COMMENT 'Object Class Name',
  `fkeys` tinyint(1) NOT NULL DEFAULT '0',
  `info` char(255) COLLATE utf8_czech_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `ois_str` (
  `uid` bigint(20) UNSIGNED NOT NULL,
  `prop_name` char(255) COLLATE utf8_czech_ci NOT NULL,
  `prop_value` longtext COLLATE utf8_czech_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;


ALTER TABLE `ois_ai`
  ADD PRIMARY KEY (`class`,`ai_name`),
  ADD UNIQUE KEY `class` (`class`),
  ADD KEY `idx_ai_name` (`ai_name`);

ALTER TABLE `ois_blob`
  ADD PRIMARY KEY (`uid`,`prop_name`),
  ADD KEY `idx_prop_name` (`prop_name`),
  ADD KEY `idx_uid` (`uid`),
  ADD KEY `idx_prop_value` (`prop_value`(255));

ALTER TABLE `ois_datetime`
  ADD PRIMARY KEY (`uid`,`prop_name`),
  ADD KEY `idx_prop_name` (`prop_name`),
  ADD KEY `idx_uid` (`uid`),
  ADD KEY `idx_prop_value` (`prop_value`);

ALTER TABLE `ois_float`
  ADD PRIMARY KEY (`uid`,`prop_name`),
  ADD KEY `idx_prop_name` (`prop_name`),
  ADD KEY `idx_uid` (`uid`),
  ADD KEY `idx_prop_value` (`prop_value`);

ALTER TABLE `ois_int`
  ADD PRIMARY KEY (`uid`,`prop_name`),
  ADD KEY `idx_prop_name` (`prop_name`),
  ADD KEY `idx_uid` (`uid`),
  ADD KEY `idx_prop_value` (`prop_value`);

ALTER TABLE `ois_main`
  ADD PRIMARY KEY (`uid`),
  ADD UNIQUE KEY `uid` (`uid`),
  ADD KEY `class` (`class`);
ALTER TABLE `ois_main` ADD FULLTEXT KEY `ft_class` (`class`);

ALTER TABLE `ois_str`
  ADD PRIMARY KEY (`uid`,`prop_name`),
  ADD KEY `idx_prop_name` (`prop_name`),
  ADD KEY `idx_uid` (`uid`),
  ADD KEY `idx_prop_value` (`prop_value`(255));
ALTER TABLE `ois_str` ADD FULLTEXT KEY `ft_prop_value` (`prop_value`);


ALTER TABLE `ois_main`
  MODIFY `uid` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique ID of an object', AUTO_INCREMENT=19999;
