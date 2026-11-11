<?php
namespace OpenEMR\Modules\TreatmentPlanner\Services;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException;

class ReviewChecklistService
{
    public const ITEMS = ['review_sources' => 'I checked the assessment inputs and missing information.',
        'review_suggestions' => 'I checked AI suggestions and removed unsupported claims.',
        'review_clinical' => 'I verified diagnoses, interventions and medication references for this patient.'];

    public static function validate(array $input): string
    {
        foreach (self::ITEMS as $key => $label) {
            if (($input[$key] ?? null) !== '1') { throw new UserFacingException('Complete every clinician review acknowledgement before approval or finalization.'); }
        }
        $note = $input['review_note'] ?? '';
        if (!is_string($note) || mb_strlen($note) > 4000) { throw new UserFacingException('Review notes must be 4000 characters or fewer.'); }
        return $note;
    }

    public function record(int $planId, int $version, int $actor, string $status, array $input): void
    {
        $note = self::validate($input);
        QueryUtils::sqlStatementThrowException('INSERT INTO module_treatment_planner_reviews
            (plan_id, plan_version, reviewer_id, status, note, date_created) VALUES (?, ?, ?, ?, ?, NOW())', [$planId, $version, $actor, $status, $note]);
    }
}
