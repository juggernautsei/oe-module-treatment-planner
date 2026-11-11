
CREATE TABLE IF NOT EXISTS `module_treatment_planner_usage_queue` (
  `event_key` char(32) NOT NULL,
  `provider_id` int NOT NULL,
  `usage_datetime` datetime NOT NULL,
  `model` varchar(50) NOT NULL,
  `prompt_tokens` int NOT NULL,
  `completion_tokens` int NOT NULL,
  `duration_seconds` float NOT NULL,
  `estimated_cost` decimal(10,6) DEFAULT NULL,
  `attempts` int NOT NULL DEFAULT 0,
  `last_attempt` datetime DEFAULT NULL,
  PRIMARY KEY (`event_key`),
  KEY `usage_datetime` (`usage_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
