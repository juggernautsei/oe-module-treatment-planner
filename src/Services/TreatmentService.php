<?php

/**
 * TreatmentService.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    [Author Name]
 * @copyright Copyright (c) 2025
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\TreatmentPlanner\Services;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Services\PatientService;
use OpenEMR\Services\EncounterService;
use OpenEMR\Modules\TreatmentPlanner\Services\AssessmentService;
use OpenEMR\Modules\TreatmentPlanner\Services\PromptGenerationService;
use Exception;
use stdClass;

// Include forms library for addForm function
require_once($GLOBALS['fileroot'] . '/library/forms.inc.php');

/**
 * Service for managing treatment plans
 */
class TreatmentService
{
    private OpenAIService $openAIService;
    private PatientService $patientService;
    private EncounterService $encounterService;
    private AssessmentService $assessmentService;
    
    // Status constants for treatment plans
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_FINALIZED = 'finalized';
    
    /**
     * TreatmentService constructor
     * 
     * @param OpenAIService $openAIService Service for generating treatment plans via OpenAI
     * @param PatientService $patientService OpenEMR patient service
     * @param EncounterService $encounterService OpenEMR encounter service
     */
    public function __construct(
        OpenAIService $openAIService,
        PatientService $patientService,
        EncounterService $encounterService
    ) {
        $this->openAIService = $openAIService;
        $this->patientService = $patientService;
        $this->encounterService = $encounterService;
        $this->assessmentService = new AssessmentService();
    }
    
    /**
     * Create a new treatment plan for a patient encounter
     * 
     * @param int $patientId Patient ID
     * @param int $encounterId Encounter ID
     * @param string $observations Clinical observations
     * @param array $options Additional options
     * @param int $providerId Provider ID
     * 
     * @return array The created treatment plan data
     * @throws Exception If plan creation fails
     */
    public function createTreatmentPlan(
        int $patientId,
        int $encounterId,
        string $observations,
        array $options = [],
        int $providerId = 0
    ): array {
        // Get comprehensive assessment data for the AI prompt
        $assessmentData = $this->assessmentService->getComprehensiveAssessmentData($patientId, $encounterId);
        
        // Generate treatment plan using OpenAI
        $planData = $this->openAIService->generateTreatmentPlan(
            $assessmentData,
            $observations,
            $options,
            $providerId
        );
        
        // Store the treatment plan in the database
        $planId = $this->storeTreatmentPlan(
            $patientId,
            $encounterId,
            $planData['content'],
            $planData['sections'],
            $providerId
        );
        
        // Add the plan ID to the data
        $planData['id'] = $planId;
        
        return $planData;
    }
    
    /**
     * Store a treatment plan in the database
     * 
     * @param int $patientId Patient ID
     * @param int $encounterId Encounter ID
     * @param string $content Full treatment plan content
     * @param array $sections Extracted sections from the plan
     * @param int $providerId Provider ID
     * 
     * @return int The ID of the created plan
     * @throws Exception If storage fails
     */
    private function storeTreatmentPlan(
        int $patientId,
        int $encounterId,
        string $content,
        array $sections,
        int $providerId
    ): int {
        // Prepare JSON for sections
        $sectionsJson = json_encode($sections);
        
        // Set initial version and status
        $version = 1;
        $status = self::STATUS_DRAFT;
        
        // Store the plan
        $sql = "INSERT INTO `module_treatment_planner_plans` 
                (`patient_id`, `encounter_id`, `provider_id`, `content`, `sections`,
                 `version`, `status`, `date_created`, `date_updated`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $params = [
            $patientId,
            $encounterId,
            $providerId,
            $content,
            $sectionsJson,
            $version,
            $status
        ];
        
        $planId = QueryUtils::sqlInsert($sql, $params);
        
        if (!$planId) {
            throw new Exception(xlt('Failed to store treatment plan'));
        }
        
        // Add the treatment plan as a form in the encounter
        try {
            addForm(
                $encounterId,           // encounter
                'Treatment Plan',       // form_name
                $planId,               // form_id (the treatment plan ID)
                'treatment_planner',   // formdir (directory/module identifier)
                $patientId,            // pid
                '1',                   // authorized (1 = authorized since created by provider)
                'NOW()',               // date (use current timestamp)
                $_SESSION['authUser'] ?? '', // user (current user)
                $_SESSION['authProvider'] ?? '' // group (current provider)
            );
        } catch (Exception $e) {
            // Log the error but don't fail the treatment plan creation
            error_log("Failed to add treatment plan to forms table: " . $e->getMessage());
        }
        
        return $planId;
    }
    
    /**
     * Update an existing treatment plan
     * 
     * @param int $planId Plan ID
     * @param array $data Update data (content, status, etc.)
     * @param int $providerId Provider ID making the update
     * 
     * @return bool Whether the update was successful
     * @throws Exception If update fails
     */
    public function updateTreatmentPlan(int $planId, array $data, int $providerId): bool
    {
        // First get the current plan to check permissions and create a new version if needed
        $currentPlan = $this->getTreatmentPlan($planId);
        
        if (!$currentPlan) {
            throw new Exception(xlt('Treatment plan not found'));
        }
        
        $updateContent = isset($data['content']) && $data['content'] !== $currentPlan['content'];
        $updateStatus = isset($data['status']) && $data['status'] !== $currentPlan['status'];
        
        // If we're updating content, create a new version
        if ($updateContent) {
            $version = $currentPlan['version'] + 1;
        } else {
            $version = $currentPlan['version'];
        }
        
        // Build the SQL update statement
        $sql = "UPDATE `module_treatment_planner_plans` SET ";
        $updates = [];
        $params = [];
        
        if ($updateContent) {
            $updates[] = "`content` = ?";
            $params[] = $data['content'];
            
            // Re-extract sections if content is updated
            if (!empty($data['content'])) {
                // Note: In a real implementation, we'd need to extract sections from the updated content
                // For now, we'll just store an empty object
                $sections = [];
                $updates[] = "`sections` = ?";
                $params[] = json_encode($sections);
            }
            
            $updates[] = "`version` = ?";
            $params[] = $version;
        }
        
        if ($updateStatus) {
            $updates[] = "`status` = ?";
            $params[] = $data['status'];
        }
        
        // Always update the provider and timestamp
        $updates[] = "`provider_id` = ?";
        $params[] = $providerId;
        
        $updates[] = "`date_updated` = NOW()";
        
        $sql .= implode(', ', $updates);
        $sql .= " WHERE `id` = ?";
        $params[] = $planId;
        
        $result = QueryUtils::sqlStatementThrowException($sql, $params);
        return $result !== false;
    }
    
    /**
     * Get a treatment plan by ID
     * 
     * @param int $planId Plan ID
     * @return array|null The treatment plan data or null if not found
     */
    public function getTreatmentPlan(int $planId): ?array
    {
        $sql = "SELECT p.*, 
                       pat.fname, pat.lname, pat.DOB,
                       prov.fname AS provider_fname, prov.lname AS provider_lname,
                       enc.date AS encounter_date
                FROM `module_treatment_planner_plans` p
                JOIN `patient_data` pat ON p.patient_id = pat.pid
                LEFT JOIN `users` prov ON p.provider_id = prov.id
                LEFT JOIN `form_encounter` enc ON p.encounter_id = enc.encounter
                WHERE p.id = ?";
        
        $result = QueryUtils::querySingleRow($sql, [$planId]);
        
        if ($result) {
            // Decode the sections JSON
            $result['sections'] = json_decode($result['sections'], true);
        }
        
        return $result ?: null;
    }
    
    /**
     * Get treatment plans for a patient
     * 
     * @param int $patientId Patient ID
     * @param int $encounterId Optional encounter ID to filter by
     * @return array List of treatment plans
     */
    public function getPatientTreatmentPlans(int $patientId, int $encounterId = 0): array
    {
        $sql = "SELECT p.id, p.encounter_id, p.version, p.status, p.date_created, p.date_updated,
                       prov.fname AS provider_fname, prov.lname AS provider_lname,
                       enc.date AS encounter_date
                FROM `module_treatment_planner_plans` p
                LEFT JOIN `users` prov ON p.provider_id = prov.id
                LEFT JOIN `form_encounter` enc ON p.encounter_id = enc.encounter
                WHERE p.patient_id = ?";
        
        $params = [$patientId];
        
        if ($encounterId > 0) {
            $sql .= " AND p.encounter_id = ?";
            $params[] = $encounterId;
        }
        
        $sql .= " ORDER BY p.date_created DESC";
        
        return QueryUtils::fetchRecords($sql, $params);
    }
    
    /**
     * Change the status of a treatment plan
     * 
     * @param int $planId Plan ID
     * @param string $status New status
     * @param int $providerId Provider ID making the change
     * @return bool Whether the status change was successful
     */
    public function changeStatus(int $planId, string $status, int $providerId): bool
    {
        // Validate the status
        $validStatuses = [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_REVIEW,
            self::STATUS_APPROVED,
            self::STATUS_FINALIZED
        ];
        
        if (!in_array($status, $validStatuses)) {
            return false;
        }
        
        $sql = "UPDATE `module_treatment_planner_plans` 
                SET `status` = ?, `provider_id` = ?, `date_updated` = NOW()
                WHERE `id` = ?";
        
        $params = [$status, $providerId, $planId];
        
        $result = QueryUtils::sqlStatementThrowException($sql, $params);
        return $result !== false;
    }
    
    /**
     * Get non-identifiable patient data for the AI prompt
     * 
     * @param int $patientId Patient ID
     * @return array Basic patient data (age, gender)
     */
    private function getPatientDataForPrompt(int $patientId): array
    {
        $patientData = $this->patientService->getOne($patientId);
        
        if (empty($patientData)) {
            return [];
        }
        
        // Calculate age from DOB
        $age = null;
        if (!empty($patientData['DOB'])) {
            $dob = new \DateTime($patientData['DOB']);
            $now = new \DateTime();
            $age = $now->diff($dob)->y;
        }
        
        return [
            'age' => $age,
            'gender' => $patientData['sex'] ?? null
        ];
    }
}
