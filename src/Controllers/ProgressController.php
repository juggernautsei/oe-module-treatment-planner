<?php
namespace OpenEMR\Modules\TreatmentPlanner\Controllers;

use OpenEMR\Modules\TreatmentPlanner\Services\{ModuleServiceFactory, ProgressService, SessionContext, CsrfService, ErrorService, GenerationRequestService};
use OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException;

class ProgressController
{
    public function indexAction(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $input = ['objective' => '', 'status' => 'in_progress', 'measure' => '', 'note' => '', 'request_key' => GenerationRequestService::newKey()];
        $error = '';
        try {
            $plan = ModuleServiceFactory::treatment(ModuleServiceFactory::openAI(ModuleServiceFactory::config()))->getTreatmentPlan($id);
            if (!$plan) { throw new UserFacingException('Treatment plan not found.'); }
            $progress = new ProgressService();
            $formVersion = (int)$plan['version'];
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $formVersion = (int)($_POST['expected_version'] ?? 0);
                foreach ($input as $key => $value) { if (is_string($_POST[$key] ?? null)) { $input[$key] = $_POST[$key]; } }
                try {
                    if (!CsrfService::verifyCsrfToken($_POST['csrf_token_form'] ?? '', 'treatment-planner-progress')) { throw new UserFacingException('Authentication Error'); }
                    $progress->append($id, $formVersion, (int)SessionContext::get('encounter', 0), $input);
                    header('Location: ' . $GLOBALS['webroot'] . '/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php/treatment/progress?id=' . $id . '&saved=1', true, 303);
                    return;
                } catch (\Throwable $e) { $error = ErrorService::publicMessage($e, 'record_progress'); }
            }
            echo ModuleServiceFactory::twig()->getTwig()->render('treatment/progress.html.twig', [
                'plan' => $plan, 'form_version' => $formVersion, 'can_record' => \OpenEMR\Common\Acl\AclMain::aclCheckCore('patients', 'med', '', 'write'), 'entries' => $progress->listForPlan($plan), 'input' => $input, 'error' => $error,
                'saved' => isset($_GET['saved']), 'statuses' => ProgressService::STATUSES,
                'followup_encounter' => (int)SessionContext::get('encounter', 0),
                'csrf_token_form' => CsrfService::collectCsrfToken('treatment-planner-progress'), 'webroot' => $GLOBALS['webroot']]);
        } catch (\Throwable $e) {
            echo ModuleServiceFactory::twig()->getTwig()->render('error.html.twig', ['error' => ErrorService::publicMessage($e, 'progress_page')]);
        }
    }
}
