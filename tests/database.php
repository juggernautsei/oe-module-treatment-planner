<?php
/** Database integration: real MySQL, isolated OpenEMR adapters, synthetic data only. */
namespace OpenEMR\Common\Acl {
    class AclMain {
        public static function aclCheckCore($section, $value, $user = '', $access = ''): bool { return !empty($_SESSION['authUser']); }
    }
}
namespace OpenEMR\Common\Csrf {
    class CsrfUtils {
        public static function verifyCsrfToken($token, $scope = ''): bool { return $token === 'valid'; }
    }
}
namespace OpenEMR\Common\Database {
    class QueryUtils {
        public static function sqlStatementThrowException($sql, $params = []) { $q = $GLOBALS['testDb']->prepare($sql); $q->execute($params); return $q; }
        public static function querySingleRow($sql, $params = []) { return self::sqlStatementThrowException($sql, $params)->fetch(\PDO::FETCH_ASSOC) ?: null; }
        public static function fetchRecords($sql, $params = []) { return self::sqlStatementThrowException($sql, $params)->fetchAll(\PDO::FETCH_ASSOC); }
        public static function sqlInsert($sql, $params = []) { self::sqlStatementThrowException($sql, $params); return (int)$GLOBALS['testDb']->lastInsertId(); }
    }
}
namespace {
    use OpenEMR\Common\Database\QueryUtils;
    use OpenEMR\Modules\TreatmentPlanner\Services\SchemaService;
    use OpenEMR\Modules\TreatmentPlanner\Services\TreatmentService;
    use OpenEMR\Modules\TreatmentPlanner\Services\OpenAIService;
    use OpenEMR\Modules\TreatmentPlanner\Services\UsageService;
    function sqlQuery($sql, $params = []) { return QueryUtils::querySingleRow($sql, $params); }
    function xlt($value) { return $value; }
    function addForm(...$args) {
        if (($GLOBALS['failFormMode'] ?? '') === 'exception') { throw new RuntimeException('Synthetic encounter-link failure'); }
        if (($GLOBALS['failFormMode'] ?? '') === 'false') { return false; }
        return QueryUtils::sqlInsert('INSERT INTO forms (encounter, pid, form_id, formdir) VALUES (?, ?, ?, ?)', [$args[0], $args[4], $args[2], $args[3]]);
    }
    function verify($condition, $message) { if (!$condition) { throw new RuntimeException($message); } $GLOBALS['checks']++; }
    $dsn = getenv('TP_TEST_DSN');
    if (!$dsn) { fwrite(STDERR, "Set TP_TEST_DSN to an empty disposable MySQL database ending in _test.\n"); exit(2); }
    $db = new PDO($dsn, getenv('TP_TEST_USER') ?: 'root', getenv('TP_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $GLOBALS['testDb'] = $db;
    $dbName = $db->query('SELECT DATABASE()')->fetchColumn();
    if (substr($dbName, -5) !== '_test' || $db->query('SHOW TABLES')->fetchColumn() !== false) {
        throw new RuntimeException('Refusing to run outside an empty disposable database ending in _test.');
    }
    $fixture = sys_get_temp_dir() . '/tp-db-fixture-' . getmypid();
    mkdir($fixture . '/library', 0777, true);
    file_put_contents($fixture . '/library/forms.inc.php', '<?php');
    $GLOBALS['fileroot'] = $fixture;
    spl_autoload_register(function ($class) {
        $prefix = 'OpenEMR\\Modules\\TreatmentPlanner\\';
        if (strpos($class, $prefix) === 0) { require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php'; }
    });
    $GLOBALS['checks'] = 0;
    SchemaService::migrate();
    SchemaService::migrate();
    SchemaService::assertReady();
    verify((int)$db->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn() === 7, 'Fresh install and repeated upgrades must create seven module tables');
    // Exercise the previous schema, then upgrade without losing historical estimates.
    $db->exec('ALTER TABLE module_treatment_planner_usage MODIFY estimated_cost decimal(10,6) NOT NULL DEFAULT 0');
    $db->exec("INSERT INTO module_treatment_planner_usage (provider_id, usage_datetime, model, estimated_cost) VALUES (7, NOW(), 'historical', 1.25)");
    SchemaService::migrate();
    verify((float)$db->query('SELECT estimated_cost FROM module_treatment_planner_usage LIMIT 1')->fetchColumn() === 1.25, 'Upgrade must preserve existing usage records');
    $db->exec('CREATE TABLE users (id int PRIMARY KEY, fname varchar(50), lname varchar(50))');
    $db->exec("INSERT INTO users VALUES (7, 'Test', 'Clinician')");
    $db->exec('CREATE TABLE patient_data (pid bigint PRIMARY KEY, fname varchar(50), lname varchar(50), DOB date, sex varchar(20))');
    $db->exec("INSERT INTO patient_data VALUES (10, 'Synthetic', 'Patient', '1990-01-01', 'unspecified')");
    $db->exec('CREATE TABLE form_encounter (encounter bigint PRIMARY KEY, pid bigint, date datetime, sensitivity varchar(20))');
    $db->exec("INSERT INTO form_encounter VALUES (20, 10, NOW(), 'normal')");
    $db->exec('CREATE TABLE forms (id int AUTO_INCREMENT PRIMARY KEY, encounter bigint, pid bigint, form_id int, formdir varchar(100))');
    $db->exec("INSERT INTO module_treatment_planner_plans (patient_id, encounter_id, provider_id, content, sections) VALUES (10, 20, 7, 'Original', '{}')");
    $id = (int)$db->lastInsertId();
    $db->exec('ALTER TABLE module_treatment_planner_plans DROP COLUMN generation_metadata');
    try { SchemaService::assertReady(); throw new LogicException('Missing provenance column accepted'); }
    catch (\OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException $e) { verify(strpos($e->getMessage(), 'upgrade required') !== false, 'Readiness must detect the missing provenance column'); }
    SchemaService::migrate(); SchemaService::migrate();
    verify($db->query('SELECT content FROM module_treatment_planner_plans WHERE id = ' . $id)->fetchColumn() === 'Original', 'Repeated provenance upgrades must preserve existing records');
    $_SESSION = ['authUser' => 'test-clinician', 'authUserID' => 7, 'pid' => 10];
    $service = (new ReflectionClass(TreatmentService::class))->newInstanceWithoutConstructor();
    $property = new ReflectionProperty($service, 'openAIService');
    $property->setValue($service, (new ReflectionClass(OpenAIService::class))->newInstanceWithoutConstructor());
    $completePlan = "Diagnosis:\nExample diagnosis\nTreatment Goals:\nExample goal\nObjectives:\nExample objective\nInterventions:\nExample intervention\nTimeline:\nExample timeline\nCriteria for Success:\nExample measure";
    $service->updateTreatmentPlan($id, ['expected_version' => 1, 'content' => $completePlan], 7);
    verify($service->getRevision($id, 1)['content'] === 'Original', 'Database snapshot must retain prior content');
    verify($service->getTreatmentPlan($id)['version'] == 2, 'Database update must advance the version');
    $history = $service->getRevisionHistory($id);
    verify($history['total'] === 1 && $history['revisions'][0]['archived_by_name'] === 'Test Clinician', 'History query must return actor attribution');
    try { $service->updateTreatmentPlan($id, ['expected_version' => 1, 'content' => 'Stale'], 7); throw new LogicException('Stale update accepted'); }
    catch (Exception $e) { verify(strpos($e->getMessage(), 'Reload') !== false, 'Stale update must be rejected'); }
    verify($service->getTreatmentPlan($id)['content'] === $completePlan, 'Rejected edit must preserve saved content');
    // Force a real unique-key failure during archiving; no plan update may commit.
    $db->exec("INSERT INTO module_treatment_planner_revisions (plan_id, version, content, status, provider_id, date_updated, changed_by, date_archived) VALUES ($id, 2, 'Collision', 'draft', 7, NOW(), 7, NOW())");
    try { $service->updateTreatmentPlan($id, ['expected_version' => 2, 'content' => 'Must roll back'], 7); throw new LogicException('Archive collision accepted'); }
    catch (PDOException $e) { verify($service->getTreatmentPlan($id)['content'] === $completePlan, 'Archive failure must roll back atomically'); }
    $db->exec("DELETE FROM module_treatment_planner_revisions WHERE plan_id = $id AND version = 2");
    $service->changeStatus($id, 'pending_review', 7, 2);
    $service->changeStatus($id, 'approved', 7, 3);
    $service->changeStatus($id, 'finalized', 7, 4);
    verify($service->getTreatmentPlan($id)['status'] === 'finalized', 'Complete workflow must persist');
    try { $service->updateTreatmentPlan($id, ['expected_version' => 5, 'content' => 'Forbidden'], 7); throw new LogicException('Finalized edit accepted'); }
    catch (Exception $e) { verify(strpos($e->getMessage(), 'read-only') !== false, 'Finalized edit must be rejected'); }
    $_SESSION['pid'] = 11;
    try { $service->getRevision($id, 1); throw new LogicException('Cross-patient history accepted'); }
    catch (RuntimeException $e) { verify(strpos($e->getMessage(), 'Access denied') !== false, 'Revision reads must be patient-scoped'); }
    $_SESSION['pid'] = 10;
    $usage = new UsageService(['priced-model' => ['prompt' => 2, 'completion' => 4]]);
    $usage->trackUsage(7, 'priced-model', 1000000, 1000000, 1);
    $usage->trackUsage(7, 'unpriced-model', 100, 100, 1);
    $stats = $usage->getUsageStats(null, null, 7);
    verify($stats['total']['total_cost'] === null && $stats['total']['unpriced_calls'] == 1, 'Unknown pricing must keep totals explicitly unavailable');
    verify($stats['by_provider'][0]['provider_id'] == 7, 'Provider aggregation must execute on the actual users schema');
    $priced = array_values(array_filter($stats['by_model'], fn($row) => $row['model'] === 'priced-model'))[0];
    verify((float)$priced['cost'] === 6.0, 'Database reporting must preserve configured rates');
    // Exercise the real generation coordinator with a non-network AI adapter.
    class TestAssessment extends \OpenEMR\Modules\TreatmentPlanner\Services\AssessmentService {
        public function getComprehensiveAssessmentData(int $pid, int $encounter): string { return 'Synthetic assessment'; }
    }
    class TestAI extends OpenAIService {
        public int $calls = 0;
        public bool $fail = false;
        public function __construct() {}
        public function generateTreatmentPlan(string $assessmentData, string $observations = '', array $options = [], int $providerId = 0): array {
            $this->calls++;
            if ($this->fail) { throw new RuntimeException('Synthetic uncertain API failure'); }
            return ['content' => 'Generated synthetic plan', 'sections' => [], 'metadata' => []];
        }
    }
    $testAI = new TestAI();
    (new ReflectionProperty($service, 'openAIService'))->setValue($service, $testAI);
    (new ReflectionProperty($service, 'assessmentService'))->setValue($service, new TestAssessment());
    $key = \OpenEMR\Modules\TreatmentPlanner\Services\GenerationRequestService::newKey();
    $generated = $service->createTreatmentPlan(10, 20, 'Synthetic notes', [], 7, $key);
    $replayed = $service->createTreatmentPlan(10, 20, 'Synthetic notes', [], 7, $key);
    verify($generated['id'] == $replayed['id'] && $testAI->calls === 1, 'Duplicate submissions must return the same plan without a second AI call');
    verify($db->query("SELECT state FROM module_treatment_planner_requests WHERE request_key = '$key'")->fetchColumn() === 'completed', 'Plan and completed request must commit together');
    try { $service->createTreatmentPlan(10, 20, 'Different notes', [], 7, $key); throw new LogicException('Mismatched submission accepted'); }
    catch (RuntimeException $e) { verify(strpos($e->getMessage(), 'does not match') !== false && $testAI->calls === 1, 'Changed input cannot reuse a request key'); }
    $requests = new \OpenEMR\Modules\TreatmentPlanner\Services\GenerationRequestService();
    $pendingKey = $requests::newKey();
    $requests->begin($pendingKey, 10, 20, 7, 'Pending notes');
    try { $service->createTreatmentPlan(10, 20, 'Pending notes', [], 7, $pendingKey); throw new LogicException('Pending request repeated'); }
    catch (RuntimeException $e) { verify(strpos($e->getMessage(), 'already submitted') !== false && $testAI->calls === 1, 'Pending submissions must not start another AI call'); }
    // Hold the request lock on a separate connection to model a racing worker.
    $secondDb = new PDO($dsn, getenv('TP_TEST_USER') ?: 'root', getenv('TP_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $racingKey = $requests::newKey();
    $name = 'tp_gen:' . sha1($racingKey);
    $lock = $secondDb->prepare('SELECT GET_LOCK(?, 0)'); $lock->execute([$name]);
    try { $requests->begin($racingKey, 10, 20, 7, 'Racing notes'); throw new LogicException('Concurrent claim accepted'); }
    catch (RuntimeException $e) { verify(strpos($e->getMessage(), 'already being processed') !== false, 'Only one racing worker may claim a request'); }
    $unlock = $secondDb->prepare('SELECT RELEASE_LOCK(?)'); $unlock->execute([$name]);
    $failedKey = $requests::newKey(); $testAI->fail = true;
    try { $service->createTreatmentPlan(10, 20, 'Failure notes', [], 7, $failedKey); throw new LogicException('API failure hidden'); }
    catch (RuntimeException $e) { verify(strpos($e->getMessage(), 'uncertain API failure') !== false, 'AI failure must propagate'); }
    try { $service->createTreatmentPlan(10, 20, 'Failure notes', [], 7, $failedKey); throw new LogicException('Uncertain charge retried'); }
    catch (RuntimeException $e) { verify(strpos($e->getMessage(), 'already submitted') !== false && $testAI->calls === 2, 'Uncertain failures must not be automatically retried'); }
    $_SESSION['authUserID'] = 8;
    try { $requests->begin($key, 10, 20, 8, 'Synthetic notes'); throw new LogicException('Another author reused request'); }
    catch (RuntimeException $e) { verify(strpos($e->getMessage(), 'does not match') !== false, 'A completed request must remain scoped to its original author'); }
    $_SESSION['authUserID'] = 7;
    $testAI->fail = false;
    $completionFailureKey = $requests::newKey();
    $planCountBefore = (int)$db->query('SELECT COUNT(*) FROM module_treatment_planner_plans')->fetchColumn();
    $db->exec("CREATE TRIGGER tp_completion_guard BEFORE UPDATE ON module_treatment_planner_requests FOR EACH ROW BEGIN IF NEW.state = 'completed' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Synthetic completion failure'; END IF; END");
    try { $service->createTreatmentPlan(10, 20, 'Completion failure notes', [], 7, $completionFailureKey); throw new LogicException('Completion failure hidden'); }
    catch (PDOException $e) { verify((int)$db->query('SELECT COUNT(*) FROM module_treatment_planner_plans')->fetchColumn() === $planCountBefore, 'Plan must roll back if request completion fails'); }
    $db->exec('DROP TRIGGER tp_completion_guard');
    $initializer = new \OpenEMR\Modules\TreatmentPlanner\Services\TemplateInitializationService();
    $templates = new \OpenEMR\Modules\TreatmentPlanner\Services\TemplateService();
    $db->exec("CREATE TRIGGER tp_template_guard BEFORE INSERT ON module_treatment_planner_templates FOR EACH ROW BEGIN IF NEW.name = 'Anxiety Treatment Plan' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Synthetic template failure'; END IF; END");
    try { $initializer->initialize('POST', 'valid', $templates); throw new LogicException('Partial initialization accepted'); }
    catch (RuntimeException $e) { verify((int)$db->query('SELECT COUNT(*) FROM module_treatment_planner_templates')->fetchColumn() === 0, 'Partial initialization must roll back every default template'); }
    $db->exec('DROP TRIGGER tp_template_guard');
    verify($initializer->initialize('POST', 'valid', $templates) === 3, 'Secure initializer must create all defaults in the database');
    verify((int)$db->query('SELECT COUNT(*) FROM module_treatment_planner_templates WHERE created_by = 7')->fetchColumn() === 3, 'Default templates must retain administrator attribution');
    verify($initializer->initialize('POST', 'valid', $templates) === 0, 'Repeated initialization must not duplicate defaults');
    $testAI->fail = false;
    foreach (['false', 'exception'] as $mode) {
        $GLOBALS['failFormMode'] = $mode;
        $key = $requests::newKey();
        $beforePlans = (int)$db->query('SELECT COUNT(*) FROM module_treatment_planner_plans')->fetchColumn();
        try { $service->createTreatmentPlan(10, 20, 'Link failure notes', [], 7, $key); throw new LogicException('Detached plan accepted'); }
        catch (Exception $e) { verify(strpos($e->getMessage(), 'Unable to link') !== false, 'Link failure must be reported clearly'); }
        verify((int)$db->query('SELECT COUNT(*) FROM module_treatment_planner_plans')->fetchColumn() === $beforePlans, 'A failed encounter link must roll back plan creation');
        verify($db->query("SELECT state FROM module_treatment_planner_requests WHERE request_key = '$key'")->fetchColumn() === 'failed', 'A failed link must not mark the request complete');
    }
    unset($GLOBALS['failFormMode']);
    // Use the actual AI coordinator with a fixture response; fail only the usage INSERT.
    class DatabaseAIConfig extends \OpenEMR\Modules\TreatmentPlanner\Services\ConfigService {
        public function __construct() {}
        public function getDecryptedApiKey(): ?string { return 'synthetic-key'; }
        public function getModel(): string { return 'priced-model'; }
        public function getTemperature(): float { return 0.7; }
    }
    class DatabaseFixtureAI extends OpenAIService {
        protected function makeApiRequest(array $data): array { return ['choices' => [['message' => ['content' => 'Output retained despite usage failure'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50]]; }
    }
    $realUsage = new UsageService(['priced-model' => ['prompt' => 2, 'completion' => 4]]);
    $realCoordinator = new DatabaseFixtureAI(new DatabaseAIConfig(), $realUsage, new \OpenEMR\Modules\TreatmentPlanner\Services\PromptGenerationService());
    (new ReflectionProperty($service, 'openAIService'))->setValue($service, $realCoordinator);
    $db->exec("CREATE TRIGGER tp_usage_guard BEFORE INSERT ON module_treatment_planner_usage FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Synthetic usage failure'");
    $saved = $service->createTreatmentPlan(10, 20, 'Usage failure notes', [], 7, $requests::newKey());
    verify($service->getTreatmentPlan($saved['id'])['content'] === 'Output retained despite usage failure', 'Usage INSERT failure must not prevent saving generated content');
    verify((int)$db->query('SELECT COUNT(*) FROM forms WHERE form_id = ' . (int)$saved['id'])->fetchColumn() === 1, 'Successful creation must include its encounter form link');
    $retry = new \OpenEMR\Modules\TreatmentPlanner\Services\UsageRetryService();
    verify($retry->pendingCount() === 1 && $saved['metadata']['usage_recorded'] === false, 'Failed usage must remain available for retry');
    $queued = $db->query('SELECT * FROM module_treatment_planner_usage_queue LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $db->exec('DROP TRIGGER tp_usage_guard');
    $usageCount = (int)$db->query('SELECT COUNT(*) FROM module_treatment_planner_usage')->fetchColumn();
    $retried = $retry->retryPending();
    verify($retried['remaining'] === 0 && $retried['recorded'] === 1, 'Retry must consume pending metadata');
    verify((int)$db->query('SELECT COUNT(*) FROM module_treatment_planner_usage')->fetchColumn() === $usageCount + 1, 'Retry must insert exactly one usage record');
    $retry->process($queued['event_key']); $retry->retryPending();
    verify((int)$db->query('SELECT COUNT(*) FROM module_treatment_planner_usage')->fetchColumn() === $usageCount + 1, 'Repeated retry must not duplicate usage');
    $last = $db->query('SELECT * FROM module_treatment_planner_usage ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    verify($last['usage_datetime'] === $queued['usage_datetime'] && $last['estimated_cost'] === $queued['estimated_cost'], 'Retry must preserve original timestamp and price');
    $db->exec("CREATE TRIGGER tp_queue_guard BEFORE INSERT ON module_treatment_planner_usage_queue FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Synthetic queue failure'");
    $queueFailurePlan = $service->createTreatmentPlan(10, 20, 'Queue unavailable notes', [], 7, $requests::newKey());
    verify($service->getTreatmentPlan($queueFailurePlan['id'])['content'] === 'Output retained despite usage failure', 'Queue unavailability must not discard successful output');
    verify($queueFailurePlan['metadata']['usage_recorded'] === false && $retry->pendingCount() === 0, 'Unavailable queue must report unrecorded usage without pretending it was queued');
    $db->exec('DROP TRIGGER tp_queue_guard');
    $provenance = $service->getTreatmentPlan($queueFailurePlan['id'])['generation_metadata'];
    verify($provenance['model'] === 'priced-model' && $provenance['prompt_version'] === \OpenEMR\Modules\TreatmentPlanner\Services\PromptGenerationService::VERSION, 'Generated model and prompt version must survive database storage');
    verify($provenance['prompt_tokens'] === 100 && !isset($provenance['options']), 'Stored provenance must retain token counts without options or clinical payloads');
    verify($service->getTreatmentPlan($id)['generation_metadata'] === null, 'Older records must not receive fabricated generation metadata');
    $service->updateTreatmentPlan($queueFailurePlan['id'], ['content' => $completePlan, 'expected_version' => 1], 7);
    verify($service->getTreatmentPlan($queueFailurePlan['id'])['generation_metadata'] === $provenance, 'Clinician edits must preserve original generation provenance');
    $db->exec("INSERT INTO module_treatment_planner_plans (patient_id, encounter_id, provider_id, content, sections) VALUES (10, 20, 7, 'Incomplete draft', '{}')");
    $incompleteId = (int)$db->lastInsertId();
    $service->changeStatus($incompleteId, 'pending_review', 7, 1);
    try { $service->changeStatus($incompleteId, 'approved', 7, 2); throw new LogicException('Incomplete approval accepted'); }
    catch (\OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException $e) { verify(strpos($e->getMessage(), 'complete these sections') !== false, 'Database workflow must block incomplete approval'); }
    verify($service->getTreatmentPlan($incompleteId)['version'] == 2 && $service->getTreatmentPlan($incompleteId)['status'] === 'pending_review', 'Rejected structural approval must not alter status or version');
    $service->updateTreatmentPlan($incompleteId, ['content' => $completePlan, 'status' => 'draft', 'expected_version' => 2], 7);
    $service->changeStatus($incompleteId, 'pending_review', 7, 3);
    $service->changeStatus($incompleteId, 'approved', 7, 4);
    $service->changeStatus($incompleteId, 'finalized', 7, 5);
    verify($service->getTreatmentPlan($incompleteId)['status'] === 'finalized', 'Completing the required sections must allow the review workflow');
    unlink($fixture . '/library/forms.inc.php'); rmdir($fixture . '/library'); rmdir($fixture);
    echo $GLOBALS['checks'] . " database integration checks passed\n";
}
