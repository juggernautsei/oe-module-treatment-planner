-- Historical estimates retain their original values; new unpriced calls use NULL.
ALTER TABLE module_treatment_planner_usage MODIFY estimated_cost decimal(10,6) NULL DEFAULT NULL;
