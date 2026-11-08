<?php
/** Run separately in native and wrapper modes so legacy fallback is exercised. */
namespace OpenEMR\Common\Acl {
    class AclMain {
        public static array $denied = [];
        public static function aclCheckCore($section, $value, $user = '', $access = ''): bool {
            return !in_array("$section/$value/$access", self::$denied, true);
        }
    }
}
namespace OpenEMR\Common\Session {
    if (($argv[1] ?? '') === 'wrapper') {
        class TestSession {
            public array $values = [];
            public bool $fail = false;
            public function get($key, $default = null) {
                if ($this->fail) { throw new \RuntimeException('session unavailable'); }
                return $this->values[$key] ?? $default;
            }
        }
        class SessionWrapperFactory {
            public static $instance;
            public TestSession $session;
            public function __construct() { $this->session = new TestSession(); }
            public static function getInstance() { return self::$instance ?? (self::$instance = new self()); }
            public function getActiveSession() { return $this->session; }
        }
    }
}
namespace OpenEMR\Common\Csrf {
    if (($argv[1] ?? '') === 'wrapper') {
        class CsrfUtils {
            public static function collectCsrfToken(\OpenEMR\Common\Session\TestSession $session, string $subject = 'default'): string {
                return hash_hmac('sha256', $subject, $session->get('csrf_private_key'));
            }
            public static function verifyCsrfToken($token, \OpenEMR\Common\Session\TestSession $session, string $subject = 'default'): bool {
                return hash_equals(self::collectCsrfToken($session, $subject), $token);
            }
        }
    } else {
        class CsrfUtils {
            public static function collectCsrfToken(string $subject = 'default'): string { return hash_hmac('sha256', $subject, $_SESSION['csrf_private_key']); }
            public static function verifyCsrfToken($token, string $subject = 'default'): bool { return hash_equals(self::collectCsrfToken($subject), $token); }
        }
    }
}
namespace {
    use OpenEMR\Common\Session\SessionWrapperFactory;
    use OpenEMR\Common\Acl\AclMain;
    use OpenEMR\Modules\TreatmentPlanner\Services\SessionContext;
    use OpenEMR\Modules\TreatmentPlanner\Services\CsrfService;
    use OpenEMR\Modules\TreatmentPlanner\Services\AccessService;
    spl_autoload_register(function ($class) {
        $prefix = 'OpenEMR\\Modules\\TreatmentPlanner\\';
        if (strpos($class, $prefix) === 0) { require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php'; }
    });
    function sqlQuery($sql, $params = []) { return ['sensitivity' => 'normal']; }
    function check($condition, $message) {
        if (!$condition) { throw new \RuntimeException($message); }
        $GLOBALS['count']++;
    }
    function rejects($call, $expected) {
        try { $call(); } catch (\RuntimeException $e) { check(strpos($e->getMessage(), $expected) !== false, 'Expected rejection'); return; }
        throw new \RuntimeException('Expected rejection');
    }
    $count = 0;
    $wrapper = ($argv[1] ?? '') === 'wrapper';
    $values = ['authUser' => 'doctor', 'authUserID' => 7, 'pid' => 10, 'encounter' => 20, 'csrf_private_key' => 'test-only'];
    if ($wrapper) { SessionWrapperFactory::getInstance()->session->values = $values; $_SESSION = []; }
    else { $_SESSION = $values; }
    AclMain::$denied = [];
    check(SessionContext::isAuthenticated(), 'Session identity is available');
    check(SessionContext::get('encounter') === 20, 'Encounter context is preserved');
    AccessService::assertPatient(10, true, 20);
    rejects(function () { AccessService::assertPatient(11); }, 'Access denied');
    AclMain::$denied = ['patients/med/write'];
    rejects(function () { AccessService::assertPatient(10, true); }, 'Access denied');
    AclMain::$denied = ['patients/sign/write'];
    rejects(function () { AccessService::assertReviewer(); }, 'signing permission');
    AclMain::$denied = ['patients/med/'];
    try { AccessService::assertModuleAccess(); throw new \RuntimeException('Missing denial'); }
    catch (\OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException $e) { check($e->getCode() === 403, 'ACL denial is 403'); }
    AclMain::$denied = [];
    $token = CsrfService::collectCsrfToken('treatment-planner');
    check(CsrfService::verifyCsrfToken($token, 'treatment-planner'), 'Valid token accepted');
    check(!CsrfService::verifyCsrfToken($token, 'other-subject'), 'Wrong token scope rejected');
    check(!CsrfService::verifyCsrfToken(['invalid']), 'Array token rejected');
    check(!CsrfService::verifyCsrfToken(''), 'Empty token rejected');
    if ($wrapper) {
        $_SESSION = $values;
        SessionWrapperFactory::getInstance()->session->values = [];
        check(!SessionContext::isAuthenticated(), 'Stale native identity is ignored');
        SessionWrapperFactory::getInstance()->session->fail = true;
        rejects(function () { AccessService::assertModuleAccess(); }, 'session unavailable');
        SessionWrapperFactory::getInstance()->session->fail = false;
    } else { $_SESSION = []; }
    try { AccessService::assertModuleAccess(); throw new \RuntimeException('Missing denial'); }
    catch (\OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException $e) { check($e->getCode() === 401, 'Missing login is 401'); }
    echo "$count session checks passed (" . ($wrapper ? 'wrapper' : 'native') . ")\n";
}
