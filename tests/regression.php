<?php
/** Standalone regression tests; OpenEMR dependencies are isolated with test doubles. */
namespace OpenEMR\Common\Acl {
    class AclMain {
        public static array $denied = [];
        public static function aclCheckCore($section, $value, $user = '', $access = ''): bool {
            return !in_array("$section/$value/$access", self::$denied, true);
        }
    }
}
namespace OpenEMR\Common\Database {
    class QueryUtils {
        public static array $plan = [];
        public static array $queries = [];
        public static bool $failArchive = false;
        public static $singleHandler = null;
        public static $fetchHandler = null;
        public static function querySingleRow($sql, $params = []) {
            return self::$singleHandler ? (self::$singleHandler)($sql, $params) : self::$plan;
        }
        public static function fetchRecords($sql, $params = []) {
            return self::$fetchHandler ? (self::$fetchHandler)($sql, $params) : [];
        }
        public static function sqlStatementThrowException($sql, $params = []) {
            self::$queries[] = [$sql, $params];
            if (self::$failArchive && strpos($sql, 'INSERT INTO') === 0) {
                throw new \RuntimeException('archive failed');
            }
            return true;
        }
    }
}
namespace {
    use OpenEMR\Common\Acl\AclMain;
    use OpenEMR\Common\Database\QueryUtils;
    use OpenEMR\Modules\TreatmentPlanner\Services\AccessService;
    use OpenEMR\Modules\TreatmentPlanner\Services\TreatmentService;
    use OpenEMR\Modules\TreatmentPlanner\Services\OpenAIService;

    function sqlQuery($sql, $params = []) {
        if (strpos($sql, 'GET_LOCK') !== false) { return ['acquired' => $GLOBALS['testLockAcquired'] ?? 1]; }
        if (strpos($sql, 'information_schema.TABLES') !== false) { return ['table_count' => $GLOBALS['testTableCount'] ?? 6]; }
        if (strpos($sql, 'information_schema.COLUMNS') !== false) { return ['IS_NULLABLE' => 'YES']; }
        if (strpos($sql, 'FROM list_options') !== false) { return $GLOBALS['testLists'][$params[0]][$params[1]] ?? []; }
        return ['sensitivity' => $GLOBALS['testSensitivity'] ?? 'normal'];
    }
    function xlt($text) { return $text; }
    $fixture = sys_get_temp_dir() . '/treatment-planner-test-' . getmypid();
    mkdir($fixture . '/library', 0777, true);
    file_put_contents($fixture . '/library/forms.inc.php', '<?php');
    $GLOBALS['fileroot'] = $fixture;
    spl_autoload_register(function ($class) {
        $prefix = 'OpenEMR\\Modules\\TreatmentPlanner\\';
        if (strpos($class, $prefix) === 0) {
            require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        }
    });
    $service = (new ReflectionClass(TreatmentService::class))->newInstanceWithoutConstructor();
    $ai = (new ReflectionClass(OpenAIService::class))->newInstanceWithoutConstructor();
    $property = new ReflectionProperty($service, 'openAIService');
    if (PHP_VERSION_ID < 80100) { $property->setAccessible(true); }
    $property->setValue($service, $ai);
    $count = 0;
    function check($condition, $message) {
        global $count;
        if (!$condition) { throw new RuntimeException($message); }
        $count++;
    }
    function resetState($status = 'draft') {
        $_SESSION = ['authUser' => 'doctor', 'authUserID' => 7, 'pid' => 10];
        AclMain::$denied = [];
        QueryUtils::$queries = [];
        QueryUtils::$failArchive = false;
        $GLOBALS['testSensitivity'] = 'normal';
        QueryUtils::$plan = ['id' => 1, 'patient_id' => 10, 'encounter_id' => 20,
            'provider_id' => 4, 'version' => 2, 'content' => 'Original plan',
            'sections' => '{}', 'status' => $status, 'date_updated' => '2026-01-01 12:00:00'];
    }
    function rejects($call, $message) {
        try { $call(); } catch (RuntimeException $e) {
            check(strpos($e->getMessage(), $message) !== false, 'Unexpected rejection: ' . $e->getMessage());
            return;
        } catch (Exception $e) {
            check(strpos($e->getMessage(), $message) !== false, 'Unexpected rejection: ' . $e->getMessage());
            return;
        }
        throw new RuntimeException('Expected rejection: ' . $message);
    }
    resetState();
    rejects(fn() => AccessService::assertPatient(11), 'Access denied');
    unset($_SESSION['authUser']);
    rejects(fn() => AccessService::assertPatient(10), 'Access denied');
    resetState();
    AclMain::$denied = ['patients/med/write'];
    rejects(fn() => AccessService::assertPatient(10, true), 'Access denied');
    resetState();
    AclMain::$denied = ['sensitivities/high/'];
    $GLOBALS['testSensitivity'] = 'high';
    rejects(fn() => AccessService::assertPatient(10, false, 20), 'Access denied');
    resetState('finalized');
    rejects(fn() => $service->updateTreatmentPlan(1, ['expected_version' => 2, 'content' => 'Changed'], 7), 'read-only');
    check(end(QueryUtils::$queries)[0] === 'ROLLBACK', 'Rejected writes must roll back');
    resetState();
    rejects(fn() => $service->updateTreatmentPlan(1, ['expected_version' => 1], 7), 'Reload');
    rejects(fn() => $service->changeStatus(1, 'finalized', 7, 2), 'Invalid treatment');
    resetState('pending_review');
    AclMain::$denied = ['patients/sign/write'];
    rejects(fn() => $service->changeStatus(1, 'approved', 7, 2), 'signing permission');
    resetState('approved');
    rejects(fn() => $service->updateTreatmentPlan(1, ['expected_version' => 2, 'content' => 'Changed'], 7), 'draft');
    resetState();
    $service->updateTreatmentPlan(1, ['expected_version' => 2, 'content' => 'New plan'], 7);
    $archive = QueryUtils::$queries[1];
    check($archive[1][2] === 'Original plan' && $archive[1][5] === 4 && $archive[1][7] === 7, 'History must retain content and authorship');
    check(end(QueryUtils::$queries)[0] === 'COMMIT', 'Valid update must commit');
    resetState();
    QueryUtils::$failArchive = true;
    rejects(fn() => $service->updateTreatmentPlan(1, ['expected_version' => 2, 'content' => 'New plan'], 7), 'archive failed');
    check(end(QueryUtils::$queries)[0] === 'ROLLBACK' && count(QueryUtils::$queries) === 3, 'Archive failure must prevent update');
    resetState('approved');
    check($service->changeStatus(1, 'finalized', 7, 2), 'Approved plans may finalize');
    $parse = new ReflectionMethod($ai, 'parseResponse');
    if (PHP_VERSION_ID < 80100) { $parse->setAccessible(true); }
    rejects(fn() => $parse->invoke($ai, ['choices' => [['message' => ['content' => 'Partial'], 'finish_reason' => 'length']]]), 'incomplete');
    check($parse->invoke($ai, ['choices' => [['message' => ['content' => 'Complete'], 'finish_reason' => 'stop']]])['content'] === 'Complete', 'Complete output accepted');
    $prompt = new OpenEMR\Modules\TreatmentPlanner\Services\PromptGenerationService();
    $risk = new ReflectionMethod($prompt, 'identifyRiskFactors');
    if (PHP_VERSION_ID < 80100) { $risk->setAccessible(true); }
    check($risk->invoke($prompt, 'denies suicidal thoughts; treatment plan reviewed') === [], 'Negated history must not flag risk');
    check($risk->invoke($prompt, "suicide risk: high\n") === ['suicide_risk'], 'Explicit risk label must route correctly');
    check($risk->invoke($prompt, "suicide risk: not high\n") === [], 'Negated label must not flag risk');
    unlink($fixture . '/library/forms.inc.php');
    rmdir($fixture . '/library');
    rmdir($fixture);
    echo "$count regression checks passed\n";
}
