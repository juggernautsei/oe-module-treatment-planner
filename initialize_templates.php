<?php
/** Administrator-only template initialization. Never sends an AI request. */
require_once dirname(__FILE__, 4) . '/globals.php';

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Modules\TreatmentPlanner\Services\CsrfService;
use OpenEMR\Core\Header;
use OpenEMR\Modules\TreatmentPlanner\Services\TemplateInitializationService;
use OpenEMR\Modules\TreatmentPlanner\Services\TemplateService;

if (!\OpenEMR\Modules\TreatmentPlanner\Services\SessionContext::get('authUser') || !AclMain::aclCheckCore('admin', 'manage_modules')) {
    http_response_code(403);
    exit(xlt('Access denied'));
}
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = is_string($_POST['csrf_token_form'] ?? null) ? $_POST['csrf_token_form'] : '';
        $count = (new TemplateInitializationService())->initialize('POST', $token, new TemplateService());
        $message = $count > 0 ? xl('Default templates created successfully.') : xl('Templates already exist. No changes were made.');
    } catch (Throwable $e) {
        http_response_code(400);
        $error = \OpenEMR\Modules\TreatmentPlanner\Services\ErrorService::publicMessage($e, 'initialize_templates');
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET, POST');
    exit;
}
?>
<!doctype html>
<html><head><meta charset="utf-8"><title><?php echo xlt('Initialize Treatment Planner Templates'); ?></title>
<?php Header::setupHeader(['common']); ?></head>
<body><main class="container mt-3">
<h1><?php echo xlt('Initialize Treatment Planner Templates'); ?></h1>
<p><?php echo xlt('Creates the three default templates only when no templates exist. This action does not call the AI service.'); ?></p>
<?php if ($message): ?><div class="alert alert-success"><?php echo text($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo text($error); ?></div><?php endif; ?>
<form method="post" action="">
  <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfService::collectCsrfToken('treatment-planner-init')); ?>">
  <button class="btn btn-primary" type="submit"><?php echo xlt('Initialize Default Templates'); ?></button>
</form>
<a href="setup.php"><?php echo xlt('Back to Configuration'); ?></a>
</main></body></html>
