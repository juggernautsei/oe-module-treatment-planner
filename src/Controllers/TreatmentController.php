<?php

/**
 * TreatmentController.php
 * Controller for treatment plan create/edit/view actions.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2025 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\TreatmentPlanner\Controllers;
use OpenEMR\Modules\TreatmentPlanner\Services\AccessService;
use OpenEMR\Modules\TreatmentPlanner\Services\ErrorService;
use OpenEMR\Modules\TreatmentPlanner\Services\PlanStructureService;
use OpenEMR\Modules\TreatmentPlanner\Services\ModuleServiceFactory;
use OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException;

use OpenEMR\Modules\TreatmentPlanner\Services\CsrfService;
use OpenEMR\Modules\TreatmentPlanner\Services\SessionContext;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Modules\TreatmentPlanner\Services\TreatmentService;
use OpenEMR\Modules\TreatmentPlanner\Services\OpenAIService;
use OpenEMR\Modules\TreatmentPlanner\Services\ConfigService;

/**
 * Controller for handling treatment plan operations
 */
class TreatmentController
{
    private ?PlanStructureService $structureService = null;
    private TreatmentService $treatmentService;
    private OpenAIService $openAIService;
    private ConfigService $configService;
    private TwigContainer $twig;

    /**
     * TreatmentController constructor
     */
    public function __construct(?TreatmentService $treatmentService = null, ?OpenAIService $openAIService = null,
        ?ConfigService $configService = null, ?TwigContainer $twig = null, ?PlanStructureService $structureService = null)
    {
        $this->configService = $configService ?? ModuleServiceFactory::config();
        $this->openAIService = $openAIService ?? ModuleServiceFactory::openAI($this->configService);
        $this->treatmentService = $treatmentService ?? ModuleServiceFactory::treatment($this->openAIService);
        $this->twig = $twig ?? ModuleServiceFactory::twig();
        $this->structureService = $structureService ?? ModuleServiceFactory::structure();
    }

    /**
     * Handle create treatment plan request
     *
     * @return void Outputs HTML
     */
    public function createAction(): void
    {
        $pid = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['pid'] ?? 0) : ($_GET['pid'] ?? SessionContext::get('pid', 0));
        $encounterId = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['encounter'] ?? 0) : ($_GET['encounter'] ?? SessionContext::get('encounter', 0));
        $formId = $_GET['formid'] ?? 0;

        $error = '';
        $plan = null;
        $observations = is_string($_POST['observations'] ?? '') ? ($_POST['observations'] ?? '') : '';

        try {
            AccessService::assertPatient((int)$pid, true, (int)$encounterId);
            // Check if API key is configured
            if (!$this->configService->isApiKeyConfigured()) {
                $error = xl('OpenAI API key is not configured. Please configure it in the module settings.');
            }

            // Process form submission
            if ($error === '' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_observations'])) {
                // Verify CSRF
                if (!CsrfService::verifyCsrfToken(($_POST['csrf_token_form'] ?? ''), 'treatment-planner')) {
                    $error = xl('Authentication Error');
                } else {
                    $providerId = SessionContext::get('authUserID', 0);

                    try {
                        // Generate the treatment plan
                        $plan = $this->treatmentService->createTreatmentPlan(
                            (int)$pid,
                            (int)$encounterId,
                            $observations,
                            [],
                            $providerId,
                            is_string($_POST['request_key'] ?? null) ? $_POST['request_key'] : ''
                        );
                        
                        // Redirect to a GET after success. Refreshing cannot resubmit the form.
                        header('Location: ' . $GLOBALS['webroot'] . '/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php/treatment/view?id=' . (int)$plan['id'], true, 303);
                        return;
                    } catch (\Throwable $e) {
                        // Log the error
                        
                        // Set error message for user
                        $error = xl('Failed to generate treatment plan: ') . ErrorService::publicMessage($e, 'treatment_plan');
                    }
                }
            }

            // Render the create form
            echo $this->twig->getTwig()->render('treatment/create.html.twig', [
                'csrf_token_form' => CsrfService::collectCsrfToken('treatment-planner'),
                'pid' => $pid,
                'encounter' => $encounterId,
                'formid' => $formId,
                'error' => $error,
                'plan' => $plan,
                'observations' => $observations,
                'request_key' => \OpenEMR\Modules\TreatmentPlanner\Services\GenerationRequestService::newKey(),
                'webroot' => $GLOBALS['webroot'],
                'module_name' => 'oe-module-treatment-planner'
            ]);

        } catch (\Throwable $e) {
            echo $this->twig->getTwig()->render('error.html.twig', [
                'error' => ErrorService::publicMessage($e, 'treatment_plan')
            ]);
        }
    }

    /** Revise observations using server-managed model and credentials. */
    public function reviseAction(): void
    {
        header('Content-Type: application/json');
        try {
            if (!CsrfService::verifyCsrfToken($_POST['csrf_token_form'] ?? '', 'treatment-planner')) {
                http_response_code(403);
                throw new UserFacingException('Authentication Error');
            }
            AccessService::assertPatient(
                (int)($_POST['pid'] ?? 0), true, (int)($_POST['encounter'] ?? 0)
            );
            $text = trim(strip_tags($_POST['text'] ?? ''));
            if ($text === '' || mb_strlen($text) > 20000) {
                throw new UserFacingException('Enter between 1 and 20000 characters.');
            }
            echo json_encode(['content' => $this->openAIService->reviseObservations($text, (int)SessionContext::get('authUserID'))]);
        } catch (\Throwable $e) {
            if (http_response_code() !== 403) { http_response_code(400); }
            echo json_encode(['error' => ErrorService::publicMessage($e, 'treatment_plan')]);
        }
    }

    /**
     * Handle edit treatment plan request
     *
     * @return void Outputs HTML
     */
    public function editAction(): void
    {
        $planId = (int)($_GET['id'] ?? 0);
        $message = ''; $error = '';
        $submitted = $_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['submit_edit']) || isset($_POST['submit_finalize']));
        try {
            $plan = $this->treatmentService->getTreatmentPlan($planId);
            if (!$plan) { throw new UserFacingException('Treatment plan not found'); }
            AccessService::assertPatient((int)$plan['patient_id'], true, (int)$plan['encounter_id']);
            $editorContent = $plan['content'];
            $formVersion = (int)$plan['version'];
            $selectedStatus = $plan['status'];
            if ($submitted) {
                $editorContent = is_string($_POST['content'] ?? null) ? $_POST['content'] : '';
                // Keep the submitted version even on conflict; never silently rebase the edit.
                $formVersion = (int)($_POST['expected_version'] ?? 0);
                $selectedStatus = is_string($_POST['status'] ?? null) ? $_POST['status'] : $plan['status'];
                $saved = false;
                try {
                    $token = is_string($_POST['csrf_token_form'] ?? null) ? $_POST['csrf_token_form'] : '';
                    if (!CsrfService::verifyCsrfToken($token, 'treatment-planner')) { throw new UserFacingException('Authentication Error'); }
                    $status = isset($_POST['submit_finalize']) ? TreatmentService::STATUS_FINALIZED : $selectedStatus;
                    if (!$this->treatmentService->updateTreatmentPlan($planId, [
                        'content' => $editorContent, 'status' => $status, 'expected_version' => $formVersion
                    ], (int)(SessionContext::get('authUserID', 0)))) { throw new UserFacingException('Unable to save treatment plan'); }
                    $saved = true;
                    $refreshedPlan = $this->treatmentService->getTreatmentPlan($planId);
                    if (!$refreshedPlan) { throw new UserFacingException('Saved plan is temporarily unavailable.'); }
                    $plan = $refreshedPlan;
                    $editorContent = $plan['content']; $formVersion = (int)$plan['version']; $selectedStatus = $plan['status'];
                    $message = xl('Treatment plan saved successfully');
                } catch (\Throwable $e) {
                    $error = ($saved
                        ? xl('The save completed, but the saved plan could not be refreshed. Your submitted text is preserved below. ')
                        : xl('Your changes were not saved. Your submitted text is preserved below. ')) . ErrorService::publicMessage($e, 'treatment_plan');
                }
            }
            $readOnly = $plan['status'] === TreatmentService::STATUS_FINALIZED;
            if ($readOnly && !$submitted) { throw new UserFacingException('Finalized plans are read-only.'); }
            echo $this->twig->getTwig()->render('treatment/edit.html.twig', [
                'csrf_token_form' => CsrfService::collectCsrfToken('treatment-planner'),
                'plan' => $plan, 'message' => $message, 'error' => $error,
                'missing_sections' => ($this->structureService ?? ModuleServiceFactory::structure())->missingSections($editorContent),
                'editor_content' => $editorContent, 'form_version' => $formVersion,
                'selected_status' => $selectedStatus, 'read_only' => $readOnly,
                'statuses' => [TreatmentService::STATUS_DRAFT => xl('Draft'), TreatmentService::STATUS_PENDING_REVIEW => xl('Pending Review'),
                    TreatmentService::STATUS_APPROVED => xl('Approved'), TreatmentService::STATUS_FINALIZED => xl('Finalized')],
                'webroot' => $GLOBALS['webroot'], 'module_name' => 'oe-module-treatment-planner'
            ]);
        } catch (\Throwable $e) {
            echo $this->twig->getTwig()->render('error.html.twig', ['error' => ErrorService::publicMessage($e, 'treatment_plan')]);
        }
    }

    /**
     * Handle view treatment plan request
     *
     * @return void Outputs HTML
     */
    public function viewAction(): void
    {
        $planId = (int)($_GET['id'] ?? 0);
        $error = '';

        try {
            // Get the treatment plan
            $plan = $this->treatmentService->getTreatmentPlan($planId);

            if (!$plan) {
                $error = xl('Treatment plan not found');
            }

            // Render the view template
            echo $this->twig->getTwig()->render('treatment/view.html.twig', [
                'missing_sections' => $plan ? ($this->structureService ?? ModuleServiceFactory::structure())->missingSections($plan['content']) : [],
                'plan_content_html' => $plan ? (new \OpenEMR\Modules\TreatmentPlanner\Services\PlanContentRenderer())->render($plan['content']) : '',
                'csrf_token_form' => CsrfService::collectCsrfToken('treatment-planner'),
                'plan' => $plan,
                'error' => $error,
                'statuses' => [
                    TreatmentService::STATUS_DRAFT => xl('Draft'),
                    TreatmentService::STATUS_PENDING_REVIEW => xl('Pending Review'),
                    TreatmentService::STATUS_APPROVED => xl('Approved'),
                    TreatmentService::STATUS_FINALIZED => xl('Finalized')
                ],
                'webroot' => $GLOBALS['webroot'],
                'module_name' => 'oe-module-treatment-planner'
            ]);

        } catch (\Throwable $e) {
            echo $this->twig->getTwig()->render('error.html.twig', [
                'error' => ErrorService::publicMessage($e, 'treatment_plan')
            ]);
        }
    }

    /** Read-only history and side-by-side comparison, including finalized plans. */
    public function historyAction(): void
    {
        try {
            $id = (int)($_GET['id'] ?? 0);
            $history = $this->treatmentService->getRevisionHistory($id, (int)($_GET['page'] ?? 1));
            $currentVersion = (int)$history['plan']['version'];
            $left = (int)($_GET['left'] ?? ($history['revisions'][0]['version'] ?? $currentVersion));
            $right = (int)($_GET['right'] ?? $currentVersion);
            $history['left'] = $this->treatmentService->getRevision($id, $left);
            $history['right'] = $this->treatmentService->getRevision($id, $right);
            $history['webroot'] = $GLOBALS['webroot'];
            $history['module_name'] = 'oe-module-treatment-planner';
            echo $this->twig->getTwig()->render('treatment/history.html.twig', $history);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo $this->twig->getTwig()->render('error.html.twig', ['error' => ErrorService::publicMessage($e, 'treatment_plan')]);
        }
    }

    /**
     * Handle listing of patient treatment plans
     *
     * @return void Outputs HTML
     */
    public function listAction(): void
    {
        $pid = $_GET['pid'] ?? SessionContext::get('pid', 0);
        $encounterId = $_GET['encounter'] ?? SessionContext::get('encounter', 0);
        $error = '';

        // Validate that we have a valid patient ID
        if (empty($pid) || $pid <= 0) {
            echo $this->twig->getTwig()->render('error.html.twig', [
                'error' => xl('Invalid or missing Patient ID. Please select a patient first.')
            ]);
            return;
        }

        try {
            // Get patient treatment plans
            $plans = $this->treatmentService->getPatientTreatmentPlans((int)$pid, (int)$encounterId);

            // Render the list template
            echo $this->twig->getTwig()->render('treatment/list.html.twig', [
                'pid' => $pid,
                'encounter' => $encounterId,
                'plans' => $plans,
                'error' => $error,
                'statuses' => [
                    TreatmentService::STATUS_DRAFT => xl('Draft'),
                    TreatmentService::STATUS_PENDING_REVIEW => xl('Pending Review'),
                    TreatmentService::STATUS_APPROVED => xl('Approved'),
                    TreatmentService::STATUS_FINALIZED => xl('Finalized')
                ],
                'webroot' => $GLOBALS['webroot'],
                'module_name' => 'oe-module-treatment-planner'
            ]);

        } catch (\Throwable $e) {
            echo $this->twig->getTwig()->render('error.html.twig', [
                'error' => ErrorService::publicMessage($e, 'treatment_plan')
            ]);
        }
    }

    /**
     * Handle changing the status of a treatment plan
     *
     * @return void Outputs JSON response
     */
    public function changeStatusAction(): void
    {
        header('Content-Type: application/json');

        $response = ['success' => false, 'message' => ''];

        try {
            // Verify CSRF
            if (!CsrfService::verifyCsrfToken(($_POST['csrf_token_form'] ?? ''), 'treatment-planner')) {
                $response['message'] = xl('Authentication Error');
                echo json_encode($response);
                return;
            }

            $planId = (int)($_POST['plan_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $providerId = SessionContext::get('authUserID', 0);

            if (empty($planId) || empty($status)) {
                $response['message'] = xl('Missing required parameters');
                echo json_encode($response);
                return;
            }

            // Change the status
            if ($this->treatmentService->changeStatus($planId, $status, $providerId, (int)($_POST['expected_version'] ?? 0))) {
                $response['success'] = true;
                $response['message'] = xl('Status updated successfully');
            } else {
                $response['message'] = xl('Failed to update status');
            }

        } catch (\Throwable $e) {
            $response['message'] = ErrorService::publicMessage($e, 'treatment_plan');
        }

        echo json_encode($response);
    }

    /**
     * Handle finalizing a treatment plan
     *
     * @return void Redirects to appropriate page
     */
    public function finalizeAction(): void
    {
        try {
            // Verify CSRF
            if (!CsrfService::verifyCsrfToken(($_POST['csrf_token_form'] ?? ''), 'treatment-planner')) {
                $error = xl('Authentication Error');
                header('Location: ' . $GLOBALS['webroot'] . '/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php/dashboard?error=' . urlencode($error));
                return;
            }

            $planId = (int)($_POST['plan_id'] ?? 0);
            $providerId = SessionContext::get('authUserID', 0);

            if (!$planId) {
                $error = xl('Invalid treatment plan ID');
                header('Location: ' . $GLOBALS['webroot'] . '/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php/dashboard?error=' . urlencode($error));
                return;
            }

            // Get the current plan
            $plan = $this->treatmentService->getTreatmentPlan($planId);
            if (!$plan) {
                $error = xl('Treatment plan not found');
                header('Location: ' . $GLOBALS['webroot'] . '/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php/dashboard?error=' . urlencode($error));
                return;
            }

            // Update to finalized status
            $updateData = ['status' => 'finalized', 'expected_version' => (int)($_POST['expected_version'] ?? 0)];
            
            // Ensure we have encounter and patient data from session
            if (SessionContext::get('encounter') !== null && SessionContext::get('pid') !== null) {
                $updateData['encounter_id'] = SessionContext::get('encounter');
                $updateData['patient_id'] = SessionContext::get('pid');
            }

            if ($this->treatmentService->updateTreatmentPlan($planId, $updateData, $providerId)) {
                $message = xl('Treatment plan finalized successfully! It will now appear in the patient summary.');
                header('Location: ' . $GLOBALS['webroot'] . '/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php/treatment/list?pid=' . $plan['patient_id'] . '&message=' . urlencode($message));
            } else {
                $error = xl('Failed to finalize treatment plan');
                header('Location: ' . $GLOBALS['webroot'] . '/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php/dashboard?error=' . urlencode($error));
            }

        } catch (\Throwable $e) {
            $error = xl('Error finalizing treatment plan: ') . ErrorService::publicMessage($e, 'treatment_plan');
            header('Location: ' . $GLOBALS['webroot'] . '/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php/dashboard?error=' . urlencode($error));
        }
    }
}
