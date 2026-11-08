<?php
/** Retry usage metadata only; never invokes the AI service. */
require_once dirname(__FILE__, 4) . '/globals.php';
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\Modules\TreatmentPlanner\Services\SchemaService;
use OpenEMR\Modules\TreatmentPlanner\Services\UsageRetryService;
if (empty($_SESSION['authUser']) || !AclMain::aclCheckCore('admin', 'manage_modules')) {
    http_response_code(403); exit(xlt('Access denied'));
}
$message = ''; $error = ''; $pending = null;
try {
    SchemaService::assertReady();
    $service = new UsageRetryService();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = is_string($_POST['csrf_token_form'] ?? null) ? $_POST['csrf_token_form'] : '';
        if (!CsrfUtils::verifyCsrfToken($token, 'treatment-planner-usage')) { throw new \OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException('Invalid form token.'); }
        $result = $service->retryPending();
        $message = xl('Usage records processed: ') . $result['recorded'];
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405); header('Allow: GET, POST'); exit;
    }
    $pending = $service->pendingCount();
} catch (Throwable $e) {
    http_response_code(400);
    $error = \OpenEMR\Modules\TreatmentPlanner\Services\ErrorService::publicMessage($e, 'retry_usage');
}
?>
<!doctype html><html><head><meta charset="utf-8"><title><?php echo xlt('Retry Usage Recording'); ?></title>
<?php Header::setupHeader(['common']); ?></head><body><main class="container mt-3">
<h1><?php echo xlt('Retry Usage Recording'); ?></h1>
<p><?php echo xlt('Retries stored usage metadata only. No AI requests are sent and no plans are regenerated.'); ?></p>
<?php if ($message): ?><p class="alert alert-success"><?php echo text($message); ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert alert-danger"><?php echo text($error); ?></p><?php endif; ?>
<?php if ($pending !== null): ?><p><?php echo xlt('Pending usage records'); ?>: <?php echo text($pending); ?></p><?php endif; ?>
<form method="post" action=""><input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken('treatment-planner-usage')); ?>">
<button class="btn btn-primary" type="submit"><?php echo xlt('Retry Next 25 Records'); ?></button></form>
<a href="setup.php"><?php echo xlt('Back to Configuration'); ?></a></main></body></html>
