
CREATE TABLE IF NOT EXISTS `module_treatment_planner_revisions` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `plan_id` int NOT NULL,
  `version` int NOT NULL,
  `content` text,
  `sections` text,
  `status` varchar(32) NOT NULL,
  `provider_id` int NOT NULL,
  `date_updated` datetime NOT NULL,
  `changed_by` int NOT NULL,
  `date_archived` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plan_version` (`plan_id`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
