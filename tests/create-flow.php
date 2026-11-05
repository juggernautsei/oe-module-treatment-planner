<?php
namespace OpenEMR\Common\Csrf {
    class CsrfUtils {
        public static function verifyCsrfToken($token, $scope = ''): bool { return $token === 'valid'; }
        public static function collectCsrfToken($scope = ''): string { return 'valid'; }
    }
}
namespace OpenEMR\Common\Twig {
    class TwigContainer {
        public array $renders = [];
        public function getTwig() { return $this; }
        public function render($name, $data) { $this->renders[] = [$name, $data]; return ''; }
    }
}
namespace {
    ob_start(); require __DIR__ . '/regression.php'; ob_end_clean();
    function xl($text) { return $text; }
    class FlowConfig extends \OpenEMR\Modules\TreatmentPlanner\Services\ConfigService {
        public bool $configured = true;
        public function __construct() {}
        public function isApiKeyConfigured(): bool { return $this->configured; }
    }
    class FlowTreatment extends \OpenEMR\Modules\TreatmentPlanner\Services\TreatmentService {
        public int $calls = 0;
        public string $receivedKey = '';
        public function __construct() {}
        public function createTreatmentPlan(int $patientId, int $encounterId, string $observations, array $options = [], int $providerId = 0, string $requestKey = ''): array {
            $this->calls++; $this->receivedKey = $requestKey; return ['id' => 42];
        }
    }
    $controller = (new ReflectionClass(\OpenEMR\Modules\TreatmentPlanner\Controllers\TreatmentController::class))->newInstanceWithoutConstructor();
    $config = new FlowConfig(); $treatment = new FlowTreatment(); $twig = new \OpenEMR\Common\Twig\TwigContainer();
    foreach (['configService' => $config, 'treatmentService' => $treatment, 'twig' => $twig] as $name => $value) {
        $p = new ReflectionProperty($controller, $name); if (PHP_VERSION_ID < 80100) { $p->setAccessible(true); } $p->setValue($controller, $value);
    }
    resetState(); $GLOBALS['webroot'] = '';
    $_SERVER['REQUEST_METHOD'] = 'POST'; $_GET = [];
    $_POST = ['pid' => 10, 'encounter' => 20, 'csrf_token_form' => 'valid', 'submit_observations' => 1, 'observations' => 'Notes to preserve', 'request_key' => str_repeat('a', 64)];
    $controller->createAction();
    check(http_response_code() === 303 && count($twig->renders) === 0, 'Successful creation must redirect to GET without rendering the POST');
    check($treatment->calls === 1 && $treatment->receivedKey === $_POST['request_key'], 'Controller must forward the request key');
    http_response_code(200); $_POST['csrf_token_form'] = 'invalid';
    $controller->createAction();
    check($treatment->calls === 1, 'Invalid CSRF must not invoke generation');
    $rendered = end($twig->renders)[1];
    check($rendered['observations'] === 'Notes to preserve' && strlen($rendered['request_key']) === 64, 'Validation error must preserve notes and issue a new request key');
    $_POST['csrf_token_form'] = 'valid'; $config->configured = false;
    $controller->createAction();
    check($treatment->calls === 1, 'Missing API configuration must stop generation');
    $_SERVER['REQUEST_METHOD'] = 'GET'; $_POST = []; $_GET = ['pid' => 10, 'encounter' => 20];
    $controller->createAction();
    check($treatment->calls === 1, 'Opening the create form must not generate a plan');
    echo "6 create-flow checks passed\n";
}
