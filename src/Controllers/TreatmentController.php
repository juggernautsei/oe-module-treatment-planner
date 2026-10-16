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

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Modules\TreatmentPlanner\Services\TreatmentService;
use OpenEMR\Modules\TreatmentPlanner\Services\OpenAIService;
use OpenEMR\Modules\TreatmentPlanner\Services\UsageService;
use OpenEMR\Modules\TreatmentPlanner\Services\ConfigService;
use OpenEMR\Modules\TreatmentPlanner\Services\AssessmentService;
use OpenEMR\Modules\TreatmentPlanner\Services\PromptGenerationService;
use OpenEMR\Services\PatientService;
use OpenEMR\Services\EncounterService;
use Exception;

/**
 * Controller for handling treatment plan operations
 */
class TreatmentController
{
    private TreatmentService $treatmentService;
    private OpenAIService $openAIService;
    private ConfigService $configService;
    private TwigContainer $twig;

    /**
     * TreatmentController constructor
     */
    public function __construct()
    {
        // Initialize services
        $this->configService = new ConfigService();

        $patientService = new PatientService();
        $encounterService = new EncounterService();
        $usageService = new UsageService();

        // Create PromptGenerationService
        $promptGenerationService = new PromptGenerationService();

        // Set up OpenAI service with usage tracking and prompt generation
        $this->openAIService = new OpenAIService($this->configService, $usageService, $promptGenerationService);

        // Set up treatment service
        $this->treatmentService = new TreatmentService(
            $this->openAIService,
            $patientService,
            $encounterService
        );

        // Set up Twig for templating
        $this->twig = new TwigContainer(dirname(__DIR__, 2) . '/templates', $GLOBALS['kernel']);
    }

    /**
     * Handle create treatment plan request
     *
     * @return void Outputs HTML
     */
    public function createAction(): void
    {
        $pid = $_GET['pid'] ?? $_SESSION['pid'];
        $encounterId = $_GET['encounter'] ?? $_SESSION['encounter'];
        $formId = $_GET['formid'] ?? 0;

        $error = '';
        $plan = null;

        try {
            // Check if API key is configured
            if (!$this->configService->isApiKeyConfigured()) {
                $error = xl('OpenAI API key is not configured. Please configure it in the module settings.');
            }

            // Process form submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_observations'])) {
                // Verify CSRF
                if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'], 'treatment-planner')) {
                    $error = xl('Authentication Error');
                } else {
                    $observations = $_POST['observations'] ?? '';
                    $providerId = $_SESSION['authUserID'] ?? 0;

                    try {
                        // Generate the treatment plan
                        $plan = $this->treatmentService->createTreatmentPlan(
                            (int)$pid,
                            (int)$encounterId,
                            $observations,
                            [],
                            $providerId
                        );
                        
                        // Log successful generation
                        error_log("Treatment plan generated successfully for PID: $pid, Encounter: $encounterId");
                        
                    } catch (Exception $e) {
                        // Log the error
                        error_log("Treatment plan generation failed: " . $e->getMessage());
                        error_log("Stack trace: " . $e->getTraceAsString());
                        
                        // Set error message for user
                        $error = xl('Failed to generate treatment plan: ') . $e->getMessage();
                    }
                }
            }

            // Render the create form
            echo $this->twig->getTwig()->render('treatment/create.html.twig', [
                'csrf_token_form' => CsrfUtils::collectCsrfToken('treatment-planner'),
                'pid' => $pid,
                'encounter' => $encounterId,
                'formid' => $formId,
                'error' => $error,
                'plan' => $plan,
                'webroot' => $GLOBALS['webroot'],
                'module_name' => 'oe-module-treatment-planner',
                'openai_api_key' => $this->configService->getDecryptedApiKey()
            ]);

        } catch (Exception $e) {
            echo $this->twig->getTwig()->render('error.html.twig', [
                'error' => $e->getMessage()
            ]);
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
        $message = '';
        $error = '';

        try {
            // Get the treatment plan
            $plan = $this->treatmentService->getTreatmentPlan($planId);

            if (!$plan) {
                $error = xl('Treatment plan not found');
            } else {
                // Process form submission for editing
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['submit_edit']) || isset($_POST['submit_finalize']))) {
                    // Verify CSRF
                    if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'], 'treatment-planner')) {
                        $error = xl('Authentication Error');
                    } else {
                        $content = $_POST['content'] ?? '';
                        $providerId = $_SESSION['authUserID'] ?? 0;

                        // Update the treatment plan
                        $updateData = ['content' => $content];

                        // Handle finalization
                        if (isset($_POST['submit_finalize'])) {
                            $updateData['status'] = 'finalized';
                            
                            // Ensure we have encounter and patient data from session
                            if (isset($_SESSION['encounter']) && isset($_SESSION['pid'])) {
                                $updateData['encounter_id'] = $_SESSION['encounter'];
                                $updateData['patient_id'] = $_SESSION['pid'];
                            }
                            
                            if ($this->treatmentService->updateTreatmentPlan($planId, $updateData, $providerId)) {
                                $message = xl('Treatment plan finalized successfully! It will now appear in the patient summary.');
                                // Refresh the plan data
                                $plan = $this->treatmentService->getTreatmentPlan($planId);
                            } else {
                                $error = xl('Failed to finalize treatment plan');
                            }
                        } else {
                            // Regular save (draft)
                            if (!empty($_POST['status']) && $_POST['status'] !== $plan['status']) {
                                $updateData['status'] = $_POST['status'];
                            }

                            if ($this->treatmentService->updateTreatmentPlan($planId, $updateData, $providerId)) {
                                $message = xl('Treatment plan updated successfully');
                                // Refresh the plan data
                                $plan = $this->treatmentService->getTreatmentPlan($planId);
                            } else {
                                $error = xl('Failed to update treatment plan');
                            }
                        }
                    }
                }
            }

            // Render the edit form
            echo $this->twig->getTwig()->render('treatment/edit.html.twig', [
                'csrf_token_form' => CsrfUtils::collectCsrfToken('treatment-planner'),
                'plan' => $plan,
                'message' => $message,
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

        } catch (Exception $e) {
            echo $this->twig->getTwig()->render('error.html.twig', [
                'error' => $e->getMessage()
            ]);
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

        } catch (Exception $e) {
            echo $this->twig->getTwig()->render('error.html.twig', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle listing of patient treatment plans
     *
     * @return void Outputs HTML
     */
    public function listAction(): void
    {
        $pid = $_GET['pid'] ?? $_SESSION['pid'];
        $encounterId = $_GET['encounter'] ?? $_SESSION['encounter'];
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

        } catch (Exception $e) {
            echo $this->twig->getTwig()->render('error.html.twig', [
                'error' => $e->getMessage()
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
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'], 'treatment-planner')) {
                $response['message'] = xl('Authentication Error');
                echo json_encode($response);
                return;
            }

            $planId = (int)($_POST['plan_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $providerId = $_SESSION['authUserID'] ?? 0;

            if (empty($planId) || empty($status)) {
                $response['message'] = xl('Missing required parameters');
                echo json_encode($response);
                return;
            }

            // Change the status
            if ($this->treatmentService->changeStatus($planId, $status, $providerId)) {
                $response['success'] = true;
                $response['message'] = xl('Status updated successfully');
            } else {
                $response['message'] = xl('Failed to update status');
            }

        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
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
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'], 'treatment-planner')) {
                $error = xl('Authentication Error');
                header('Location: ' . $GLOBALS['webroot'] . '/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php/dashboard?error=' . urlencode($error));
                return;
            }

            $planId = (int)($_POST['plan_id'] ?? 0);
            $providerId = $_SESSION['authUserID'] ?? 0;

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
            $updateData = ['status' => 'finalized'];
            
            // Ensure we have encounter and patient data from session
            if (isset($_SESSION['encounter']) && isset($_SESSION['pid'])) {
                $updateData['encounter_id'] = $_SESSION['encounter'];
                $updateData['patient_id'] = $_SESSION['pid'];
            }

            if ($this->treatmentService->updateTreatmentPlan($planId, $updateData, $providerId)) {
                $message = xl('Treatment plan finalized successfully! It will now appear in the patient summary.');
                header('Location: ' . $GLOBALS['webroot'] . '/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php/treatment/list?pid=' . $plan['patient_id'] . '&message=' . urlencode($message));
            } else {
                $error = xl('Failed to finalize treatment plan');
                header('Location: ' . $GLOBALS['webroot'] . '/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php/dashboard?error=' . urlencode($error));
            }

        } catch (Exception $e) {
            $error = xl('Error finalizing treatment plan: ') . $e->getMessage();
            header('Location: ' . $GLOBALS['webroot'] . '/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php/dashboard?error=' . urlencode($error));
        }
    }
}
