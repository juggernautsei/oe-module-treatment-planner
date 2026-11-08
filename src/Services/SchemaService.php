<?php
namespace OpenEMR\Modules\TreatmentPlanner\Services;
use OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException;

use OpenEMR\Common\Database\QueryUtils;
use RuntimeException;

/** Additive, repeatable upgrades; DDL is deliberately outside plan transactions. */
class SchemaService
{
    public static function migrate(): void
    {
        $lock = sqlQuery("SELECT GET_LOCK(CONCAT('tp_schema:', SHA1(DATABASE())), 10) AS acquired");
        if ((int)($lock['acquired'] ?? 0) !== 1) {
            throw new UserFacingException('Could not acquire the treatment planner upgrade lock. Retry the upgrade.');
        }
        try {
            $schema = file_get_contents(dirname(__DIR__, 2) . '/table.sql');
            if ($schema === false) { throw new UserFacingException('Module schema file is missing.'); }
            $schema = preg_replace('/^\s*--.*$/m', '', $schema);
            foreach (explode(';', $schema) as $statement) {
                if (trim($statement) !== '') { QueryUtils::sqlStatementThrowException(trim($statement)); }
            }
            $column = sqlQuery("SELECT IS_NULLABLE FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'module_treatment_planner_usage'
                AND COLUMN_NAME = 'estimated_cost'");
            if (($column['IS_NULLABLE'] ?? '') === 'NO') {
                QueryUtils::sqlStatementThrowException('ALTER TABLE module_treatment_planner_usage MODIFY estimated_cost decimal(10,6) NULL DEFAULT NULL');
            }
            $metadata = sqlQuery("SELECT COUNT(*) AS metadata_count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'module_treatment_planner_plans' AND COLUMN_NAME = 'generation_metadata'");
            if ((int)($metadata['metadata_count'] ?? 0) !== 1) {
                QueryUtils::sqlStatementThrowException('ALTER TABLE module_treatment_planner_plans ADD generation_metadata text DEFAULT NULL');
            }
            self::assertReady();
        } finally {
            sqlQuery("SELECT RELEASE_LOCK(CONCAT('tp_schema:', SHA1(DATABASE())))");
        }
    }

    public static function assertReady(): void
    {
        $row = sqlQuery("SELECT COUNT(*) AS table_count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME IN ('module_treatment_planner_config', 'module_treatment_planner_plans',
            'module_treatment_planner_templates', 'module_treatment_planner_usage', 'module_treatment_planner_revisions', 'module_treatment_planner_requests', 'module_treatment_planner_usage_queue')");
        $column = sqlQuery("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'module_treatment_planner_usage' AND COLUMN_NAME = 'estimated_cost'");
        $metadata = sqlQuery("SELECT COUNT(*) AS metadata_count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'module_treatment_planner_plans' AND COLUMN_NAME = 'generation_metadata'");
        if ((int)($metadata['metadata_count'] ?? 0) !== 1 || (int)($row['table_count'] ?? 0) !== 7 || ($column['IS_NULLABLE'] ?? '') !== 'YES') {
            throw new UserFacingException('Treatment Planner database upgrade required. Ask a module administrator to run Upgrade or re-enable the module.');
        }
    }
}
