-- Manual fallback: run once on a 1.0.4 database. Automatic Module Manager upgrades are repeatable.
ALTER TABLE module_treatment_planner_plans ADD generation_metadata text DEFAULT NULL;
