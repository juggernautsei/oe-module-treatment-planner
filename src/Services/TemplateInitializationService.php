<?php
namespace OpenEMR\Modules\TreatmentPlanner\Services;
use OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException;

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Modules\TreatmentPlanner\Services\CsrfService;
use OpenEMR\Common\Database\QueryUtils;
use RuntimeException;

class TemplateInitializationService
{
    public function initialize(string $method, string $csrf, TemplateService $templates): int
    {
        if (!SessionContext::get('authUser') || (int)(SessionContext::get('authUserID', 0)) <= 0 || !AclMain::aclCheckCore('admin', 'manage_modules')) {
            throw new UserFacingException('Administrator permission required.');
        }
        if ($method !== 'POST' || !CsrfService::verifyCsrfToken($csrf, 'treatment-planner-init')) {
            throw new UserFacingException('A valid POST and CSRF token are required.');
        }
        SchemaService::assertReady();
        $lock = sqlQuery("SELECT GET_LOCK(CONCAT('tp_templates:', SHA1(DATABASE())), 0) AS acquired");
        if ((int)($lock['acquired'] ?? 0) !== 1) { throw new UserFacingException('Template initialization is already running.'); }
        try {
            if (count($templates->getAllTemplates()) > 0) { return 0; }
            QueryUtils::sqlStatementThrowException('START TRANSACTION');
            try {
                $ids = $templates->createDefaultTemplates((int)(SessionContext::get('authUserID', 0)));
                if (count($ids) !== 3) { throw new UserFacingException('Default template initialization was incomplete.'); }
                QueryUtils::sqlStatementThrowException('COMMIT');
                return count($ids);
            } catch (\Throwable $e) {
                QueryUtils::sqlStatementThrowException('ROLLBACK');
                throw $e;
            }
        } finally {
            sqlQuery("SELECT RELEASE_LOCK(CONCAT('tp_templates:', SHA1(DATABASE())))");
        }
    }
}
