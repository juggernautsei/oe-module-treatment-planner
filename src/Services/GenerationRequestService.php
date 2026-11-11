<?php
namespace OpenEMR\Modules\TreatmentPlanner\Services;
use OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException;

use OpenEMR\Common\Database\QueryUtils;
use RuntimeException;

/** Persist submission identity before external work; never automatically retry an uncertain charge. */
class GenerationRequestService
{
    public static function newKey(): string { return bin2hex(random_bytes(32)); }

    public function begin(string $key, int $patientId, int $encounterId, int $providerId, string $observations): ?int
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $key)) {
            throw new UserFacingException('Invalid generation request. Reload the create form.');
        }
        AccessService::assertPatient($patientId, true, $encounterId);
        if ($providerId <= 0 || $providerId !== (int)(SessionContext::get('authUserID', 0))) {
            throw new UserFacingException('Invalid generation author.');
        }
        $lockName = 'tp_gen:' . sha1($key);
        $lock = sqlQuery('SELECT GET_LOCK(?, 0) AS acquired', [$lockName]);
        if ((int)($lock['acquired'] ?? 0) !== 1) {
            throw new UserFacingException('This generation request is already being processed. Check the patient plan list before submitting again.');
        }
        try {
            $row = QueryUtils::querySingleRow('SELECT * FROM module_treatment_planner_requests WHERE request_key = ?', [$key]);
            $hash = hash('sha256', $observations);
            if ($row) {
                if ((int)$row['patient_id'] !== $patientId || (int)$row['encounter_id'] !== $encounterId
                    || (int)$row['provider_id'] !== $providerId || !hash_equals($row['input_hash'], $hash)) {
                    throw new UserFacingException('Generation request does not match this submission. Reload the create form.');
                }
                if ($row['state'] === 'completed' && (int)$row['plan_id'] > 0) { return (int)$row['plan_id']; }
                throw new UserFacingException('This request was already submitted and may have reached the AI service. Check the plan list; use a new form only if you intend to start another generation.');
            }
            QueryUtils::sqlStatementThrowException('INSERT INTO module_treatment_planner_requests
                (request_key, patient_id, encounter_id, provider_id, input_hash, state, date_created, date_updated)
                VALUES (?, ?, ?, ?, ?, \'processing\', NOW(), NOW())', [$key, $patientId, $encounterId, $providerId, $hash]);
            return null;
        } finally {
            sqlQuery('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }

    public function complete(string $key, int $planId): void
    {
        QueryUtils::sqlStatementThrowException("UPDATE module_treatment_planner_requests SET state = 'completed', plan_id = ?, date_updated = NOW() WHERE request_key = ? AND state = 'processing'", [$planId, $key]);
    }

    public function fail(string $key): void
    {
        QueryUtils::sqlStatementThrowException("UPDATE module_treatment_planner_requests SET state = 'failed', date_updated = NOW() WHERE request_key = ? AND state = 'processing'", [$key]);
    }
}
