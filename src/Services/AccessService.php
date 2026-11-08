<?php
namespace OpenEMR\Modules\TreatmentPlanner\Services;
use OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException;

use OpenEMR\Common\Acl\AclMain;
use RuntimeException;

/** Fail closed unless the caller has medical-record access in the selected chart. */
class AccessService
{
    public static function assertPatient(int $patientId, bool $write = false, int $encounterId = 0): void
    {
        if (empty($_SESSION['authUser']) || $patientId <= 0
            || $patientId !== (int)($_SESSION['pid'] ?? 0)
            || !AclMain::aclCheckCore('patients', 'med', '', $write ? 'write' : '')) {
            throw new UserFacingException('Access denied. Select the patient chart and check your medical-record permissions.');
        }
        if ($encounterId > 0) {
            $encounter = sqlQuery('SELECT sensitivity FROM form_encounter WHERE pid = ? AND encounter = ?', [$patientId, $encounterId]);
            if (!$encounter || (!empty($encounter['sensitivity'])
                && !AclMain::aclCheckCore('sensitivities', $encounter['sensitivity']))) {
                throw new UserFacingException('Access denied for this encounter.');
            }
        }
    }

    public static function assertReviewer(): void
    {
        if (!AclMain::aclCheckCore('patients', 'sign', '', 'write')) {
            throw new UserFacingException('Approval and finalization require signing permission.');
        }
    }
}
