<?php
ob_start(); require __DIR__ . '/create-flow.php'; ob_end_clean();
use OpenEMR\Modules\TreatmentPlanner\Services\PatientAgeService;
use OpenEMR\Modules\TreatmentPlanner\Services\UsageService;
use OpenEMR\Modules\TreatmentPlanner\Services\OpenAIService;
use OpenEMR\Modules\TreatmentPlanner\Services\PromptGenerationService;
use OpenEMR\Modules\TreatmentPlanner\Services\ConfigService;
$before = $count;
$asOf = new DateTimeImmutable('2026-11-08');
check(PatientAgeService::calculate('1990-12-01', $asOf) === 35, 'Birthday not yet reached must not add a year');
check(PatientAgeService::calculate('1990-11-08', $asOf) === 36, 'Birthday today must advance the age');
check(PatientAgeService::calculate('2026-11-08', $asOf) === 0, 'Newborn age must be zero');
check(PatientAgeService::calculate('2000-02-29', new DateTimeImmutable('2025-02-28')) === 24, 'Leap-year DOB must use full date arithmetic');
foreach (['2026-11-09', '0000-00-00', '2026-02-31', '', null] as $dob) {
    check(PatientAgeService::calculate($dob, $asOf) === null, 'Missing, invalid or future DOB must not display a fabricated age');
}
class EditTreatment extends FlowTreatment {
    public array $plan = ['id' => 42, 'patient_id' => 10, 'encounter_id' => 20, 'version' => 5, 'status' => 'draft', 'content' => 'Latest saved content', 'age' => 35];
    public int $updates = 0;
    public bool $typeFailure = false;
    public bool $saveSucceeds = false;
    public bool $savedOnce = false;
    public function getTreatmentPlan(int $planId): ?array { return $this->savedOnce ? null : $this->plan; }
    public function updateTreatmentPlan(int $planId, array $data, int $providerId): bool {
        $this->updates++;
        if ($this->saveSucceeds) { $this->savedOnce = true; return true; }
        if ($this->typeFailure) { throw new TypeError('Synthetic persistence error'); }
        throw new RuntimeException('This plan changed. Reload it before saving.');
    }
}
resetState(); $_GET = ['id' => 42]; $_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['submit_edit' => 1, 'csrf_token_form' => 'valid', 'content' => 'Unsaved clinician changes', 'expected_version' => 3, 'status' => 'draft'];
$edit = new EditTreatment();
$p = new ReflectionProperty($controller, 'treatmentService'); if (PHP_VERSION_ID < 80100) { $p->setAccessible(true); } $p->setValue($controller, $edit);
$controller->editAction(); $render = end($twig->renders);
check($render[0] === 'treatment/edit.html.twig' && $render[1]['editor_content'] === $_POST['content'], 'Conflict must return submitted text to the editor');
check($render[1]['form_version'] === 3 && $render[1]['plan']['version'] === 5, 'Conflict must not silently replace the submitted version with the latest version');
check(strpos($render[1]['error'], 'not saved') !== false, 'Failed save must explicitly report unsaved changes');
$_POST['csrf_token_form'] = 'bad'; $calls = $edit->updates; $controller->editAction();
check($edit->updates === $calls && end($twig->renders)[1]['editor_content'] === $_POST['content'], 'CSRF failure must preserve text without invoking an update');
$_POST['csrf_token_form'] = 'valid'; $edit->typeFailure = true; $controller->editAction();
check(end($twig->renders)[1]['editor_content'] === $_POST['content'], 'Unexpected persistence errors must also preserve text');
$edit->typeFailure = false; $edit->plan['status'] = 'finalized'; $controller->editAction();
check(end($twig->renders)[1]['read_only'] === true && end($twig->renders)[1]['editor_content'] === $_POST['content'], 'Concurrent finalization must offer read-only text recovery');
$_SESSION['pid'] = 11; $controller->editAction();
check(end($twig->renders)[0] === 'error.html.twig', 'Unauthorized charts must not render the edit recovery screen');
resetState(); $edit->plan['status'] = 'draft'; $edit->saveSucceeds = true; $controller->editAction();
check(end($twig->renders)[1]['editor_content'] === $_POST['content'] && strpos(end($twig->renders)[1]['error'], 'save completed') !== false, 'A refresh failure after saving must preserve text and distinguish it from a failed save');
class UsageFailureConfig extends ConfigService {
    public function __construct() {}
    public function getDecryptedApiKey(): ?string { return 'synthetic-test-key'; }
    public function getModel(): string { return 'synthetic-model'; }
    public function getTemperature(): float { return 0.7; }
}
class ThrowingUsage extends UsageService {
    public function recordUsageSafely(int $providerId, string $model, int $promptTokens = 0, int $completionTokens = 0, float $duration = 0): bool { throw new RuntimeException('Synthetic usage failure'); }
}
class FixedResponseAI extends OpenAIService {
    protected function makeApiRequest(array $data): array { return ['choices' => [['message' => ['content' => 'Preserved generated output'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5]]; }
}
$api = new FixedResponseAI(new UsageFailureConfig(), new ThrowingUsage(), new PromptGenerationService());
$result = $api->generateTreatmentPlan('Synthetic assessment', 'Synthetic observation', [], 7);
check($result['content'] === 'Preserved generated output' && $result['metadata']['usage_recorded'] === false, 'Usage errors must not discard generated plans');
check($api->reviseObservations('Synthetic notes', 7) === 'Preserved generated output', 'Usage errors must not discard revised notes');
echo ($count - $before) . " fourth-review checks passed\n";
