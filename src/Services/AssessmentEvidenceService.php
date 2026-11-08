<?php
namespace OpenEMR\Modules\TreatmentPlanner\Services;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException;

class AssessmentEvidenceService
{
    public const IMPORTANT = ['reason_visit' => 'Presenting concern', 'treatment_goals' => 'Patient goals',
        'suicide_current' => 'Current suicide-risk assessment', 'violence_current' => 'Current violence-risk assessment',
        'current_medications' => 'Current medications', 'allergies' => 'Allergies'];

    public static function isDocumented(string $value): bool
    {
        return trim($value) !== '' && !preg_match('/\b(not assessed|not specified|unknown|unrecognized option|requires clinician|not established)\b/i', $value);
    }

    public static function snapshot(array $fields, array $source): array
    {
        $sources = []; $missing = [];
        foreach ($fields as $key => $field) {
            $value = (string)($field['display'] ?? $field['value'] ?? '');
            if ($value === '') { continue; }
            $sources[] = ['reference' => 'S' . (count($sources) + 1), 'field' => $key,
                'source_field' => $field['source_field'] ?? $key, 'label' => $field['title'] ?? $key,
                'value' => $value, 'documented' => self::isDocumented($value)];
        }
        foreach (self::IMPORTANT as $key => $label) {
            $field = $fields[$key] ?? [];
            if (!self::isDocumented((string)($field['display'] ?? $field['value'] ?? ''))) { $missing[$key] = $label; }
        }
        return ['source' => $source, 'captured_at' => gmdate('Y-m-d\TH:i:s\Z'), 'sources' => $sources, 'missing' => $missing];
    }

    public static function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode([$snapshot['source'], $snapshot['sources'], $snapshot['missing']], JSON_THROW_ON_ERROR));
    }

    public static function assertReady(array $snapshot, bool $acknowledged): void
    {
        if (!$snapshot['sources'] || isset($snapshot['missing']['reason_visit'])) {
            throw new UserFacingException('A documented presenting concern is required before generation. Complete and save the assessment first.');
        }
        if ($snapshot['missing'] && !$acknowledged) {
            throw new UserFacingException('Review the missing assessment information and acknowledge the gaps before generating a draft.');
        }
    }

    public static function promptSources(array $snapshot): string
    {
        $text = "\nASSESSMENT SOURCE REFERENCES (recorded inputs, not independently verified facts):\n";
        foreach ($snapshot['sources'] as $source) {
            $text .= '[' . $source['reference'] . '] ' . $source['field'] . ': ' . $source['value'] . "\n";
        }
        return $text . "Missing or uncertain input: " . implode(', ', $snapshot['missing']) . "\n";
    }

    public function save(int $planId, array $snapshot, string $observations, bool $acknowledged): void
    {
        // Store the actual observations locally for review; never log this snapshot.
        $snapshot['observations'] = $observations;
        $snapshot['gaps_acknowledged'] = $acknowledged;
        QueryUtils::sqlStatementThrowException('INSERT INTO module_treatment_planner_evidence (plan_id, snapshot) VALUES (?, ?)',
            [$planId, json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
    }

    public function get(int $planId): ?array
    {
        $row = QueryUtils::querySingleRow('SELECT snapshot FROM module_treatment_planner_evidence WHERE plan_id = ?', [$planId]);
        return empty($row['snapshot']) ? null : json_decode($row['snapshot'], true);
    }
}
