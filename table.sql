-- Treatment Planner module schema (install)

CREATE TABLE IF NOT EXISTS `module_treatment_planner_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `encrypted` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `module_treatment_planner_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` bigint(20) NOT NULL,
  `encounter_id` bigint(20) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `content` text DEFAULT NULL,
  `sections` text DEFAULT NULL,
  `status` enum('draft','pending_review','approved','finalized') NOT NULL DEFAULT 'draft',
  `version` int(11) NOT NULL DEFAULT 1,
  `date_created` datetime DEFAULT current_timestamp(),
  `date_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_encounter_index` (`patient_id`,`encounter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `module_treatment_planner_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `prompt_template` text NOT NULL,
  `conditions` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `date_created` datetime DEFAULT current_timestamp(),
  `date_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `module_treatment_planner_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider_id` int(11) NOT NULL,
  `usage_datetime` datetime NOT NULL,
  `model` varchar(50) NOT NULL,
  `prompt_tokens` int(11) NOT NULL DEFAULT 0,
  `completion_tokens` int(11) NOT NULL DEFAULT 0,
  `total_tokens` int(11) NOT NULL DEFAULT 0,
  `duration_seconds` float NOT NULL DEFAULT 0,
  `estimated_cost` decimal(10,6) NOT NULL DEFAULT 0.000000,
  PRIMARY KEY (`id`),
  KEY `provider_id_index` (`provider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
