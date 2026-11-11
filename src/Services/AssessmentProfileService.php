<?php
namespace OpenEMR\Modules\TreatmentPlanner\Services;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException;

/** Administrator-defined aliases, never SQL fragments or automatic form installation. */
class AssessmentProfileService
{
    public static function validate(string $form, array $map): array
    {
        if (!preg_match('/^LBF[A-Za-z0-9_]{1,28}$/D', $form)) {
            throw new UserFacingException('Select a valid layout-based assessment form ID.');
        }
        if (count($map) > 100) { throw new UserFacingException('At most 100 assessment field mappings are allowed.'); }
        foreach ($map as $canonical => $source) {
            if (!is_string($canonical) || !is_string($source) || !in_array($canonical, AssessmentService::FIELD_IDS, true)
                || !preg_match('/^[A-Za-z0-9_]{1,31}$/D', $canonical) || !preg_match('/^[A-Za-z0-9_]{1,31}$/D', $source)) {
                throw new UserFacingException('Field mappings must use valid field IDs.');
            }
        }
        if (count(array_unique(array_values($map))) !== count($map)) {
            throw new UserFacingException('Each source field must map to only one assessment field.');
        }
        return ['form' => $form, 'map' => $map];
    }

    public function current(): array
    {
        $row = QueryUtils::querySingleRow('SELECT setting_value FROM module_treatment_planner_config WHERE setting_key = ?', ['assessment_profile']);
        if (empty($row['setting_value'])) { return ['form' => 'LBF_MHINTAKE', 'map' => []]; }
        $profile = json_decode($row['setting_value'], true);
        if (!is_array($profile) || !is_string($profile['form'] ?? null) || !is_array($profile['map'] ?? null)) {
            throw new UserFacingException('The assessment profile is invalid. Ask a module administrator to correct it.');
        }
        return self::validate($profile['form'], $profile['map']);
    }

    public function save(string $form, string $mappingJson): void
    {
        $map = json_decode($mappingJson, true);
        if (!is_array($map)) { throw new UserFacingException('Assessment field mapping must be a JSON object.'); }
        $profile = self::validate($form, $map);
        $layout = sqlQuery('SELECT COUNT(*) AS total FROM layout_options WHERE form_id = ? AND uor > 0', [$form]);
        $active = sqlQuery("SELECT grp_activity FROM layout_group_properties WHERE grp_form_id = ? AND grp_group_id = ''", [$form]);
        if (empty($layout['total']) || empty($active['grp_activity'])) { throw new UserFacingException('Install and activate the selected layout before configuring it.'); }
        foreach ($profile['map'] as $source) {
            $field = sqlQuery('SELECT COUNT(*) AS total FROM layout_options WHERE form_id = ? AND field_id = ? AND uor > 0', [$form, $source]);
            if (empty($field['total'])) { throw new UserFacingException('A mapped source field is missing or inactive in the selected assessment form.'); }
        }
        QueryUtils::sqlStatementThrowException('INSERT INTO module_treatment_planner_config (setting_key, setting_value, encrypted)
            VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), encrypted = 0', ['assessment_profile', json_encode($profile)]);
    }
}
