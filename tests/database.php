<?php
/** Database integration: real MySQL, isolated OpenEMR adapters, synthetic data only. */
namespace OpenEMR\Common\Acl {
    class AclMain {
        public static function aclCheckCore($section, $value, $user = '', $access = ''): bool { return !empty($_SESSION['authUser']); }
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
    verify((int)$db->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn() === 5, 'Fresh install and repeated upgrades must create five module tables');
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
    $db->exec("INSERT INTO module_treatment_planner_plans (patient_id, encounter_id, provider_id, content, sections) VALUES (10, 20, 7, 'Original', '{}')");
    $id = (int)$db->lastInsertId();
    $_SESSION = ['authUser' => 'test-clinician', 'authUserID' => 7, 'pid' => 10];
    $service = (new ReflectionClass(TreatmentService::class))->newInstanceWithoutConstructor();
    $property = new ReflectionProperty($service, 'openAIService');
    $property->setValue($service, (new ReflectionClass(OpenAIService::class))->newInstanceWithoutConstructor());
    $service->updateTreatmentPlan($id, ['expected_version' => 1, 'content' => 'Changed'], 7);
    verify($service->getRevision($id, 1)['content'] === 'Original', 'Database snapshot must retain prior content');
    verify($service->getTreatmentPlan($id)['version'] == 2, 'Database update must advance the version');
    $history = $service->getRevisionHistory($id);
    verify($history['total'] === 1 && $history['revisions'][0]['archived_by_name'] === 'Test Clinician', 'History query must return actor attribution');
    try { $service->updateTreatmentPlan($id, ['expected_version' => 1, 'content' => 'Stale'], 7); throw new LogicException('Stale update accepted'); }
    catch (Exception $e) { verify(strpos($e->getMessage(), 'Reload') !== false, 'Stale update must be rejected'); }
    verify($service->getTreatmentPlan($id)['content'] === 'Changed', 'Rejected edit must preserve saved content');
    // Force a real unique-key failure during archiving; no plan update may commit.
    $db->exec("INSERT INTO module_treatment_planner_revisions (plan_id, version, content, status, provider_id, date_updated, changed_by, date_archived) VALUES ($id, 2, 'Collision', 'draft', 7, NOW(), 7, NOW())");
    try { $service->updateTreatmentPlan($id, ['expected_version' => 2, 'content' => 'Must roll back'], 7); throw new LogicException('Archive collision accepted'); }
    catch (PDOException $e) { verify($service->getTreatmentPlan($id)['content'] === 'Changed', 'Archive failure must roll back atomically'); }
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
    unlink($fixture . '/library/forms.inc.php'); rmdir($fixture . '/library'); rmdir($fixture);
    echo $GLOBALS['checks'] . " database integration checks passed\n";
}
