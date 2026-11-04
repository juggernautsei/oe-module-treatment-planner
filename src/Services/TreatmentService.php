<?php

/**
 * TreatmentService.php
 * Service for creating and managing treatment plans.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2025 Sherwin Gaddis <sherwingaddis@gmail.com>
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
        AccessService::assertPatient($patientId, true, $encounterId);
        if ($encounterId <= 0) {
            throw new Exception('Select an encounter before generating a plan.');
        }
        if (mb_strlen($observations) > 20000) {
            throw new Exception('Clinical observations must be 20000 characters or fewer.');
        }
        SchemaService::assertReady();
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
        SchemaService::assertReady();
        // Lock the row so concurrent edits cannot silently overwrite one another.
        QueryUtils::sqlStatementThrowException('START TRANSACTION');
        try {
            $current = QueryUtils::querySingleRow(
                'SELECT * FROM module_treatment_planner_plans WHERE id = ? FOR UPDATE', [$planId]
            );
            if (!$current) {
                throw new Exception('Treatment plan not found');
            }
            AccessService::assertPatient((int)$current['patient_id'], true, (int)$current['encounter_id']);
            if ($providerId !== (int)($_SESSION['authUserID'] ?? 0) || $providerId <= 0) {
                throw new Exception('Invalid author');
            }
            if ((int)($data['expected_version'] ?? 0) !== (int)$current['version']) {
                throw new Exception('This plan changed. Reload it before saving.');
            }
            if ($current['status'] === self::STATUS_FINALIZED) {
                throw new Exception('Finalized plans are read-only.');
            }
            $content = $data['content'] ?? $current['content'];
            $status = $data['status'] ?? $current['status'];
            $changed = $content !== $current['content'];
            $transitions = [
                self::STATUS_DRAFT => [self::STATUS_PENDING_REVIEW],
                self::STATUS_PENDING_REVIEW => [self::STATUS_DRAFT, self::STATUS_APPROVED],
                self::STATUS_APPROVED => [self::STATUS_DRAFT, self::STATUS_PENDING_REVIEW, self::STATUS_FINALIZED]
            ];
            if ($status !== $current['status'] && !in_array($status, $transitions[$current['status']] ?? [], true)) {
                throw new Exception('Invalid treatment plan status transition.');
            }
            if (in_array($status, [self::STATUS_APPROVED, self::STATUS_FINALIZED], true)) {
                AccessService::assertReviewer();
                if ($changed) {
                    throw new Exception('Save content changes as a draft before approval.');
                }
            }
            if ($changed && $status !== self::STATUS_DRAFT) {
                throw new Exception('Content changes must return the plan to draft.');
            }
            if (trim(strip_tags($content)) === '') {
                throw new Exception('Treatment plan content cannot be empty.');
            }
            // Save the previous state, including original attribution, before overwriting.
            QueryUtils::sqlStatementThrowException(
                'INSERT INTO module_treatment_planner_revisions
                 (plan_id, version, content, sections, status, provider_id, date_updated, changed_by, date_archived)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [$planId, $current['version'], $current['content'], $current['sections'],
                 $current['status'], $current['provider_id'], $current['date_updated'], $providerId]
            );
            $sections = $changed ? json_encode($this->openAIService->extractSections($content)) : $current['sections'];
            QueryUtils::sqlStatementThrowException(
                'UPDATE module_treatment_planner_plans SET content = ?, sections = ?, status = ?,
                 version = version + 1, date_updated = NOW() WHERE id = ?',
                [$content, $sections, $status, $planId]
            );
            QueryUtils::sqlStatementThrowException('COMMIT');
            return true;
        } catch (\Throwable $e) {
            QueryUtils::sqlStatementThrowException('ROLLBACK');
            throw $e;
        }
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
                       pat.fname, pat.lname, pat.DOB, pat.sex,
                       prov.fname AS provider_fname, prov.lname AS provider_lname,
                       enc.date AS encounter_date
                FROM `module_treatment_planner_plans` p
                JOIN `patient_data` pat ON p.patient_id = pat.pid
                LEFT JOIN `users` prov ON p.provider_id = prov.id
                LEFT JOIN `form_encounter` enc ON p.encounter_id = enc.encounter
                WHERE p.id = ?";
        
        $result = QueryUtils::querySingleRow($sql, [$planId]);
        
        if ($result) {
            AccessService::assertPatient((int)$result['patient_id'], false, (int)$result['encounter_id']);
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
        AccessService::assertPatient($patientId, false, $encounterId);
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
        
        $plans = QueryUtils::fetchRecords($sql, $params);
        return array_values(array_filter($plans, function ($plan) use ($patientId) {
            try {
                AccessService::assertPatient($patientId, false, (int)$plan['encounter_id']);
                return true;
            } catch (\RuntimeException $e) {
                return false;
            }
        }));
    }
    
    /** Patient-scoped snapshots, paginated without exposing other charts. */
    public function getRevisionHistory(int $planId, int $page = 1): array
    {
        $plan = $this->getTreatmentPlan($planId);
        if (!$plan) { throw new Exception('Treatment plan not found'); }
        SchemaService::assertReady();
        $page = max(1, $page);
        $count = QueryUtils::querySingleRow('SELECT COUNT(*) AS total FROM module_treatment_planner_revisions WHERE plan_id = ?', [$planId]);
        $total = (int)($count['total'] ?? 0);
        $pages = max(1, (int)ceil($total / 25));
        $page = min($page, $pages);
        $offset = ($page - 1) * 25;
        $rows = QueryUtils::fetchRecords('SELECT r.*, CONCAT(u.fname, \' \', u.lname) AS archived_by_name
            FROM module_treatment_planner_revisions r LEFT JOIN users u ON r.changed_by = u.id
            WHERE r.plan_id = ? ORDER BY r.version DESC LIMIT 25 OFFSET ' . $offset, [$planId]);
        return ['plan' => $plan, 'revisions' => $rows, 'page' => $page, 'pages' => $pages, 'total' => $total];
    }

    public function getRevision(int $planId, int $version): array
    {
        $plan = $this->getTreatmentPlan($planId);
        if (!$plan) { throw new Exception('Treatment plan not found'); }
        if ($version === (int)$plan['version']) { return $plan; }
        $revision = QueryUtils::querySingleRow('SELECT * FROM module_treatment_planner_revisions WHERE plan_id = ? AND version = ?', [$planId, $version]);
        if (!$revision) { throw new Exception('Revision not found for this treatment plan'); }
        return $revision;
    }

    /**
     * Change the status of a treatment plan
     * 
     * @param int $planId Plan ID
     * @param string $status New status
     * @param int $providerId Provider ID making the change
     * @return bool Whether the status change was successful
     */
    public function changeStatus(int $planId, string $status, int $providerId, int $expectedVersion): bool
    {
        return $this->updateTreatmentPlan($planId, [
            'status' => $status, 'expected_version' => $expectedVersion
        ], $providerId);
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
