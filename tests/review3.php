<?php
namespace OpenEMR\Common\Csrf {
    class CsrfUtils {
        public static function collectCsrfToken($scope = 'default'): string { return 'valid'; }
        public static function verifyCsrfToken($token, $scope = ''): bool { return $token === 'valid'; }
    }
}
namespace {
    require __DIR__ . '/followup.php';
    use OpenEMR\Common\Acl\AclMain;
    use OpenEMR\Common\Database\QueryUtils;
    use OpenEMR\Modules\TreatmentPlanner\Services\AssessmentService;
    use OpenEMR\Modules\TreatmentPlanner\Services\ConfigService;
    use OpenEMR\Modules\TreatmentPlanner\Services\GenerationRequestService;
    use OpenEMR\Modules\TreatmentPlanner\Services\TemplateInitializationService;
    use OpenEMR\Modules\TreatmentPlanner\Services\TemplateService;
    $before = $count;
    resetState();
    $assessment = new AssessmentService();
    $data = [
        'patient_info' => ['age' => 0, 'gender' => 'unspecified', 'name' => 'DO_NOT_SEND'],
        'clinical_history' => ['medications' => 'MEDICATION_SENTINEL', 'allergies' => 'ALLERGY_SENTINEL', 'medical_history' => 'MEDICAL_HISTORY_SENTINEL'],
        'risk_assessment' => ['suicide_risk' => ['current_ideation' => 'high', 'plan' => 'PLAN_SENTINEL', 'means' => 'MEANS_SENTINEL', 'history' => 'HISTORY_SENTINEL', 'protective_factors' => 'PROTECTION_SENTINEL'], 'violence_risk' => ['triggers' => 'TRIGGERS_SENTINEL']],
        'mental_status_exam' => ['cognitive_function' => ['attention' => 'ATTENTION_SENTINEL']],
        'psychosocial_factors' => ['support_system' => 'SUPPORT_SENTINEL'],
        'strengths_resources' => ['coping_skills' => 'COPING_SENTINEL'],
        'functional_assessment' => ['self_care' => false], 'unexpected_field' => 'DO_NOT_SEND'
    ];
    $formatted = $assessment->formatForAI($data);
    $promptText = $prompt->generatePrompt($formatted, 'OBSERVATION_SENTINEL');
    foreach (['MEDICATION', 'ALLERGY', 'MEDICAL_HISTORY', 'PLAN', 'MEANS', 'HISTORY', 'PROTECTION', 'TRIGGERS', 'ATTENTION', 'SUPPORT', 'COPING', 'OBSERVATION'] as $value) {
        check(strpos($promptText, $value . '_SENTINEL') !== false, 'Prompt must retain ' . $value);
    }
    check(strpos($formatted, 'Age: 0') !== false && strpos($formatted, 'Self Care: No') !== false, 'Zero and explicit negatives must be retained');
    check(strpos($formatted, 'DO_NOT_SEND') === false, 'Unapproved patient identifiers and top-level fields must be excluded');
    check($risk->invoke($prompt, strtolower($formatted)) === ['suicide_risk'], 'Structured explicit risk label must still route');
    check(ConfigService::validateTemperature('0') === 0.0 && ConfigService::validateTemperature('1') === 1.0, 'Temperature boundary values must be valid');
    foreach (['bad', '', 1.1, -0.1, INF, NAN, [], true] as $invalid) {
        rejects(fn() => ConfigService::validateTemperature($invalid), 'between 0 and 1');
    }
    $requests = new GenerationRequestService();
    $key = GenerationRequestService::newKey();
    check(strlen($key) === 64 && $key !== GenerationRequestService::newKey(), 'Submission keys must be fresh random values');
    rejects(fn() => $requests->begin('invalid', 10, 20, 7, 'notes'), 'Invalid generation request');
    QueryUtils::$singleHandler = fn($sql, $params) => null;
    check($requests->begin($key, 10, 20, 7, 'notes') === null, 'First submission must be claimed');
    $GLOBALS['testLockAcquired'] = 0;
    rejects(fn() => $requests->begin($key, 10, 20, 7, 'notes'), 'already being processed');
    unset($GLOBALS['testLockAcquired']);
    $requestRow = ['patient_id' => 10, 'encounter_id' => 20, 'provider_id' => 7, 'input_hash' => hash('sha256', 'notes'), 'state' => 'completed', 'plan_id' => 12];
    QueryUtils::$singleHandler = fn($sql, $params) => $requestRow;
    check($requests->begin($key, 10, 20, 7, 'notes') === 12, 'Completed submissions must reuse their existing plan');
    rejects(fn() => $requests->begin($key, 10, 20, 7, 'different notes'), 'does not match');
    $requestRow['state'] = 'failed';
    QueryUtils::$singleHandler = fn($sql, $params) => $requestRow;
    rejects(fn() => $requests->begin($key, 10, 20, 7, 'notes'), 'already submitted');
    QueryUtils::$singleHandler = null;
    class InitializationTemplates extends TemplateService {
        public array $existing = [];
        public array $created = [1, 2, 3];
        public int $actor = 0;
        public function getAllTemplates(): array { return $this->existing; }
        public function createDefaultTemplates(int $providerId = 0): array { $this->actor = $providerId; return $this->created; }
    }
    $initializer = new TemplateInitializationService();
    $templates = new InitializationTemplates();
    AclMain::$denied = ['admin/manage_modules/'];
    rejects(fn() => $initializer->initialize('POST', 'valid', $templates), 'Administrator');
    AclMain::$denied = [];
    rejects(fn() => $initializer->initialize('GET', 'valid', $templates), 'valid POST');
    rejects(fn() => $initializer->initialize('POST', '', $templates), 'CSRF');
    QueryUtils::$queries = [];
    check($initializer->initialize('POST', 'valid', $templates) === 3 && $templates->actor === 7, 'Admin POST must initialize with actor attribution');
    check(end(QueryUtils::$queries)[0] === 'COMMIT', 'Initialization must commit atomically');
    $templates->existing = [['id' => 1]];
    check($initializer->initialize('POST', 'valid', $templates) === 0, 'Existing templates must not duplicate');
    $templates->existing = [];
    $templates->created = [1, 2];
    rejects(fn() => $initializer->initialize('POST', 'valid', $templates), 'incomplete');
    check(end(QueryUtils::$queries)[0] === 'ROLLBACK', 'Incomplete initialization must roll back');
    echo ($count - $before) . " third-review checks passed\n";
}
