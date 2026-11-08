<?php
namespace OpenEMR\Modules\TreatmentPlanner\Services;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException;

/** Append-only follow-up observations; never rewrites a finalized plan. */
class ProgressService
{
    public const STATUSES = ['not_started', 'in_progress', 'improving', 'unchanged', 'worsening', 'completed'];

    public static function validate(array $input): array
    {
        $result = [];
        foreach (['objective' => 255, 'status' => 32, 'measure' => 255, 'note' => 4000, 'request_key' => 64] as $key => $limit) {
            if (!is_string($input[$key] ?? null) || mb_strlen($input[$key]) > $limit) { throw new UserFacingException('Invalid or oversized progress field.'); }
            $result[$key] = trim($input[$key]);
        }
        if ($result['objective'] === '' || $result['note'] === '' || !in_array($result['status'], self::STATUSES, true)
            || !preg_match('/^[a-f0-9]{64}$/D', $result['request_key'])) { throw new UserFacingException('Enter an objective, a valid progress status and a follow-up note.'); }
        return $result;
    }

    public function append(int $planId, int $expectedVersion, int $encounterId, array $input): void
    {
        $data = self::validate($input);
        $actor = (int)SessionContext::get('authUserID', 0);
        if ($actor <= 0 || $encounterId <= 0) { throw new UserFacingException('Select a follow-up encounter and sign in before recording progress.'); }
        QueryUtils::sqlStatementThrowException('START TRANSACTION');
        try {
            $plan = QueryUtils::querySingleRow('SELECT * FROM module_treatment_planner_plans WHERE id = ? FOR UPDATE', [$planId]);
            if (!$plan) { throw new UserFacingException('Treatment plan not found.'); }
            AccessService::assertPatient((int)$plan['patient_id'], true, (int)$plan['encounter_id']);
            AccessService::assertPatient((int)$plan['patient_id'], true, $encounterId);
            $previous = QueryUtils::querySingleRow('SELECT * FROM module_treatment_planner_progress WHERE request_key = ?', [$data['request_key']]);
            if ($previous) {
                foreach ($data as $key => $value) { if (($previous[$key] ?? null) !== $value) { throw new UserFacingException('This follow-up submission was already used. Reload the page.'); } }
                if ((int)$previous['plan_id'] !== $planId || (int)$previous['actor_id'] !== $actor || (int)$previous['encounter_id'] !== $encounterId
                    || (int)$previous['plan_version'] !== $expectedVersion) { throw new UserFacingException('This follow-up submission belongs to another record.'); }
            } else {
                if ((int)$plan['version'] !== $expectedVersion) { throw new UserFacingException('The plan changed. Reload it before recording progress.'); }
                QueryUtils::sqlStatementThrowException('INSERT INTO module_treatment_planner_progress
                    (plan_id, plan_version, encounter_id, actor_id, request_key, objective, status, measure, note, date_created)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())', [$planId, $expectedVersion, $encounterId, $actor,
                    $data['request_key'], $data['objective'], $data['status'], $data['measure'], $data['note']]);
            }
            QueryUtils::sqlStatementThrowException('COMMIT');
        } catch (\Throwable $e) { QueryUtils::sqlStatementThrowException('ROLLBACK'); throw $e; }
    }

    public function listForPlan(array $plan): array
    {
        AccessService::assertPatient((int)$plan['patient_id'], false, (int)$plan['encounter_id']);
        $rows = QueryUtils::fetchRecords('SELECT p.*, u.fname, u.lname FROM module_treatment_planner_progress p
            LEFT JOIN users u ON u.id = p.actor_id WHERE p.plan_id = ? ORDER BY p.id DESC LIMIT 100', [(int)$plan['id']]);
        return array_values(array_filter($rows, function ($row) use ($plan) {
            try { AccessService::assertPatient((int)$plan['patient_id'], false, (int)$row['encounter_id']); return true; }
            catch (UserFacingException $e) { return false; }
        }));
    }
}
