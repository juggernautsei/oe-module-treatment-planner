-- Additive evidence, review and progress tables; no existing records are changed.

CREATE TABLE IF NOT EXISTS module_treatment_planner_evidence (
 plan_id int NOT NULL, snapshot longtext NOT NULL, PRIMARY KEY(plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS module_treatment_planner_reviews (
 id bigint NOT NULL AUTO_INCREMENT, plan_id int NOT NULL, plan_version int NOT NULL,
 reviewer_id int NOT NULL, status varchar(32) NOT NULL, note text NOT NULL, date_created datetime NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY plan_version_review(plan_id,plan_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS module_treatment_planner_progress (
 id bigint NOT NULL AUTO_INCREMENT, plan_id int NOT NULL, plan_version int NOT NULL,
 encounter_id bigint NOT NULL, actor_id int NOT NULL, request_key char(64) NOT NULL,
 objective varchar(255) NOT NULL, status varchar(32) NOT NULL, measure varchar(255) NOT NULL,
 note text NOT NULL, date_created datetime NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY request_key(request_key), KEY plan_progress(plan_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
