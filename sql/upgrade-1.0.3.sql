
CREATE TABLE IF NOT EXISTS `module_treatment_planner_requests` (
  `request_key` char(64) NOT NULL,
  `patient_id` bigint NOT NULL,
  `encounter_id` bigint NOT NULL,
  `provider_id` int NOT NULL,
  `input_hash` char(64) NOT NULL,
  `state` varchar(16) NOT NULL,
  `plan_id` int DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime NOT NULL,
  PRIMARY KEY (`request_key`),
  KEY `patient_encounter` (`patient_id`, `encounter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
