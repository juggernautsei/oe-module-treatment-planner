<?php
namespace OpenEMR\Services {
    class PatientService {}
    class EncounterService {}
}
namespace {
    ob_start(); require __DIR__ . '/review4.php'; ob_end_clean();
    use OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException;
    use OpenEMR\Modules\TreatmentPlanner\Services\ErrorService;
    use OpenEMR\Modules\TreatmentPlanner\Services\GenerationMetadataService;
    use OpenEMR\Modules\TreatmentPlanner\Services\PlanSectionParser;
    use OpenEMR\Modules\TreatmentPlanner\Services\PlanStructureService;
    use OpenEMR\Modules\TreatmentPlanner\Services\PromptGenerationService;
    use OpenEMR\Modules\TreatmentPlanner\Services\TreatmentService;
    use OpenEMR\Modules\TreatmentPlanner\Services\AssessmentService;
    use OpenEMR\Modules\TreatmentPlanner\Services\GenerationRequestService;
    use OpenEMR\Modules\TreatmentPlanner\Controllers\TreatmentController;
    use OpenEMR\Common\Database\QueryUtils;
    $before = $count;
    $log = tempnam(sys_get_temp_dir(), 'tp-redaction-');
    $oldLog = ini_get('error_log'); ini_set('error_log', $log);
    $message = ErrorService::publicMessage(new RuntimeException('SELECT password FROM secrets; PATIENT_SENTINEL API_KEY_SENTINEL'), 'save_plan');
    $logged = file_get_contents($log);
    check(preg_match('/Reference: ([a-f0-9]{12})/', $message, $ref) === 1 && strpos($logged, $ref[1]) !== false, 'Unexpected errors must provide a matching log reference');
    foreach (['SELECT', 'password', 'PATIENT_SENTINEL', 'API_KEY_SENTINEL'] as $secret) {
        check(strpos($message . $logged, $secret) === false, 'Unexpected error messages and logs must not leak ' . $secret);
    }
    check(ErrorService::publicMessage(new UserFacingException('Reload before saving.')) === 'Reload before saving.', 'Explicit safe validation messages must stay actionable');
    $wrapped = ErrorService::publicMessage(new UserFacingException('Encounter linking failed.', 0, new RuntimeException('PRIVATE_WRAPPED_SENTINEL')));
    check(strpos($wrapped, 'Encounter linking failed. Reference:') === 0 && strpos(file_get_contents($log), 'PRIVATE_WRAPPED_SENTINEL') === false, 'Safe wrappers with unexpected causes must log a reference without exposing the cause message');
    ini_set('error_log', $oldLog); unlink($log);
    $parser = new PlanSectionParser(); $structure = new PlanStructureService($parser);
    $complete = "Diagnosis/Problem Statement:\nExample diagnosis\nGoals:\nExample goal\nObjectives:\nExample objective\nInterventions and Strategies:\nExample intervention\nTimeline for Treatment:\nExample timeline\nProgress Measurement Methods:\nExample measure";
    check($structure->missingSections($complete) === [], 'Canonical and supported template headings must satisfy structure');
    check(count($structure->missingSections('A short unstructured note')) === 6, 'Unstructured drafts must flag all six sections');
    check(count($structure->missingSections("Diagnosis:\nExample")) === 5, 'Partial drafts must identify missing sections');
    check(count($structure->missingSections("Diagnosis:\nTreatment Goals:\nObjectives:\nInterventions:\nTimeline:\nCriteria for Success:")) === 6, 'Empty headings must not count as completed sections');
    rejects(fn() => $structure->assertComplete('Partial draft'), 'complete these sections');
    resetState('pending_review');
    rejects(fn() => $service->changeStatus(1, 'approved', 7, 2), 'complete these sections');
    check(end(QueryUtils::$queries)[0] === 'ROLLBACK', 'Incomplete approval must roll back');
    resetState('approved');
    rejects(fn() => $service->changeStatus(1, 'finalized', 7, 2), 'complete these sections');
    resetState();
    check($service->updateTreatmentPlan(1, ['content' => 'Useful incomplete draft', 'expected_version' => 2], 7), 'Incomplete drafts must remain savable');
    $json = GenerationMetadataService::encode(['model' => 'resolved-model', 'requested_model' => 'configured-model', 'prompt_version' => 'version-1', 'generated_at' => '2026-11-08T00:00:00Z', 'prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15,
        'options' => ['notes' => 'PRIVATE_SENTINEL'], 'prompt' => 'PRIVATE_SENTINEL', 'api_key' => 'PRIVATE_SENTINEL']);
    $metadata = json_decode($json, true);
    check($metadata['model'] === 'resolved-model' && $metadata['total_tokens'] === 15, 'Provenance must preserve safe model and token fields');
    check(strpos($json, 'PRIVATE_SENTINEL') === false && !isset($metadata['options']), 'Provenance must exclude prompts, options and keys');
    check(GenerationMetadataService::encode([]) === null, 'Legacy missing metadata must remain explicitly unknown');
    class ProvenanceFixtureAI extends FixedResponseAI {
        protected function makeApiRequest(array $data): array {
            return ['model' => 'resolved-test-model', 'choices' => [['message' => ['content' => 'Draft content'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 8]];
        }
    }
    $fixtureAI = new ProvenanceFixtureAI(new UsageFailureConfig(), null, new PromptGenerationService(), $parser);
    $result = $fixtureAI->generateTreatmentPlan('Synthetic assessment');
    check($result['metadata']['model'] === 'resolved-test-model' && $result['metadata']['requested_model'] === 'synthetic-model', 'Resolved and requested models must be distinguishable');
    check($result['metadata']['prompt_version'] === PromptGenerationService::VERSION && preg_match('/^\d{4}-\d{2}-\d{2}T.*Z$/', $result['metadata']['generated_at']), 'Generation must record prompt version and UTC time');
    // No kernel, database, CryptoGen, or reflection is needed with explicit dependencies.
    unset($GLOBALS['kernel']); resetState();
    $injectedService = new TreatmentService($fixtureAI, new \OpenEMR\Services\PatientService(), new \OpenEMR\Services\EncounterService(), new AssessmentService(), new GenerationRequestService(), $structure);
    $injectedTwig = new \OpenEMR\Common\Twig\TwigContainer(); $injectedConfig = new FlowConfig();
    $injectedController = new TreatmentController($injectedService, $fixtureAI, $injectedConfig, $injectedTwig, $structure);
    $_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = ['pid' => 10, 'encounter' => 20]; $_POST = []; $injectedController->createAction();
    check(end($injectedTwig->renders)[0] === 'treatment/create.html.twig', 'Controller must support fully injected construction without OpenEMR kernel setup');
    class SQLFailureTreatment extends EditTreatment {
        public function updateTreatmentPlan(int $planId, array $data, int $providerId): bool { throw new RuntimeException('SELECT PRIVATE_DATABASE_SENTINEL'); }
    }
    $safeController = new TreatmentController(new SQLFailureTreatment(), $fixtureAI, $injectedConfig, $injectedTwig, $structure);
    $_SERVER['REQUEST_METHOD'] = 'POST'; $_GET = ['id' => 42]; $_POST = ['submit_edit' => 1, 'csrf_token_form' => 'valid', 'content' => 'Preserved draft', 'expected_version' => 3, 'status' => 'draft'];
    $safeController->editAction(); $safeView = end($injectedTwig->renders)[1];
    check(strpos($safeView['error'], 'Reference:') !== false && strpos($safeView['error'], 'PRIVATE_DATABASE_SENTINEL') === false && $safeView['editor_content'] === 'Preserved draft', 'Controller must redact persistence details without losing submitted edits');
    echo ($count - $before) . " fifth-review checks passed\n";
}
