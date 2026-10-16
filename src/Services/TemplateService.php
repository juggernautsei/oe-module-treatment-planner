<?php

/**
 * TemplateService.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    [Author Name]
 * @copyright Copyright (c) 2025
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\TreatmentPlanner\Services;

use OpenEMR\Common\Database\QueryUtils;
use Exception;

/**
 * Service for managing treatment plan templates
 */
class TemplateService
{
    /**
     * Get all available templates
     * 
     * @return array List of templates
     */
    public function getAllTemplates(): array
    {
        $sql = "SELECT * FROM `module_treatment_planner_templates` ORDER BY `name` ASC";
        return QueryUtils::fetchRecords($sql);
    }
    
    /**
     * Get a template by ID
     * 
     * @param int $templateId Template ID
     * @return array|null Template data or null if not found
     */
    public function getTemplate(int $templateId): ?array
    {
        $sql = "SELECT * FROM `module_treatment_planner_templates` WHERE `id` = ?";
        $result = QueryUtils::querySingleRow($sql, [$templateId]);
        return $result ?: null;
    }
    
    /**
     * Create a new template
     * 
     * @param string $name Template name
     * @param string $description Template description
     * @param string $promptTemplate The prompt template text
     * @param array $conditions Associated conditions
     * @param bool $isActive Whether the template is active
     * @param int $providerId Provider creating the template
     * 
     * @return int The ID of the created template
     * @throws Exception If creation fails
     */
    public function createTemplate(
        string $name,
        string $description,
        string $promptTemplate,
        array $conditions = [],
        bool $isActive = true,
        int $providerId = 0
    ): int {
        // Convert conditions array to JSON
        $conditionsJson = json_encode($conditions);
        
        $sql = "INSERT INTO `module_treatment_planner_templates` 
                (`name`, `description`, `prompt_template`, `conditions`, `is_active`, 
                 `created_by`, `date_created`, `date_updated`) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $params = [
            $name,
            $description,
            $promptTemplate,
            $conditionsJson,
            $isActive ? 1 : 0,
            $providerId
        ];
        
        $templateId = QueryUtils::sqlInsert($sql, $params);
        
        if (!$templateId) {
            throw new Exception(xlt('Failed to create template'));
        }
        
        return $templateId;
    }
    
    /**
     * Update an existing template
     * 
     * @param int $templateId Template ID
     * @param array $data Update data
     * @param int $providerId Provider ID making the update
     * 
     * @return bool Whether the update was successful
     * @throws Exception If update fails
     */
    public function updateTemplate(int $templateId, array $data, int $providerId): bool
    {
        $sql = "UPDATE `module_treatment_planner_templates` SET ";
        $updates = [];
        $params = [];
        
        // Check and add each field that can be updated
        if (isset($data['name'])) {
            $updates[] = "`name` = ?";
            $params[] = $data['name'];
        }
        
        if (isset($data['description'])) {
            $updates[] = "`description` = ?";
            $params[] = $data['description'];
        }
        
        if (isset($data['prompt_template'])) {
            $updates[] = "`prompt_template` = ?";
            $params[] = $data['prompt_template'];
        }
        
        if (isset($data['conditions'])) {
            $updates[] = "`conditions` = ?";
            $params[] = json_encode($data['conditions']);
        }
        
        if (isset($data['is_active'])) {
            $updates[] = "`is_active` = ?";
            $params[] = $data['is_active'] ? 1 : 0;
        }
        
        // Always update the timestamp and user
        $updates[] = "`date_updated` = NOW()";
        
        if ($providerId > 0) {
            $updates[] = "`created_by` = ?";
            $params[] = $providerId;
        }
        
        // Only proceed if we have updates
        if (empty($updates)) {
            return false;
        }
        
        $sql .= implode(', ', $updates);
        $sql .= " WHERE `id` = ?";
        $params[] = $templateId;
        
        return QueryUtils::sqlStatementThrowException($sql, $params) > 0;
    }
    
    /**
     * Delete a template
     * 
     * @param int $templateId Template ID
     * @return bool Whether the deletion was successful
     */
    public function deleteTemplate(int $templateId): bool
    {
        $sql = "DELETE FROM `module_treatment_planner_templates` WHERE `id` = ?";
        return QueryUtils::sqlStatementThrowException($sql, [$templateId]) > 0;
    }
    
    /**
     * Get templates for specific conditions
     * 
     * @param array $conditions List of condition codes or names
     * @return array List of matching templates
     */
    public function getTemplatesForConditions(array $conditions): array
    {
        if (empty($conditions)) {
            return [];
        }
        
        $results = [];
        $templates = $this->getAllTemplates();
        
        foreach ($templates as $template) {
            $templateConditions = json_decode($template['conditions'], true) ?: [];
            
            // Check if any condition matches
            foreach ($conditions as $condition) {
                if (in_array($condition, $templateConditions)) {
                    $results[] = $template;
                    break;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Create some default templates
     * 
     * @return array IDs of created templates
     */
    public function createDefaultTemplates(): array
    {
        $createdIds = [];
        
        // Depression template
        $depressionTemplate = <<<EOT
Create a comprehensive behavioral health treatment plan based on the following information.

Patient Information:
Age: {{age}}
Gender: {{gender}}

Clinical Observations:
{{observations}}

This patient appears to be experiencing symptoms of depression. Please structure the treatment plan with the following components:
1. Diagnosis/Problem Statement (e.g., Major Depressive Disorder, Persistent Depressive Disorder)
2. Long-term Goals (3-5) focused on mood improvement, daily functioning, and social engagement
3. Short-term Objectives (measurable) related to symptom reduction
4. Interventions and Strategies (including cognitive-behavioral techniques, behavioral activation, and potentially medication management)
5. Timeline for Treatment (typical course and milestones)
6. Progress Measurement Methods (specific assessments like PHQ-9, mood tracking)

Format the plan in a professional clinical manner appropriate for medical documentation.
EOT;

        try {
            $createdIds[] = $this->createTemplate(
                'Depression Treatment Plan',
                'Template for patients presenting with depressive symptoms',
                $depressionTemplate,
                ['depression', 'F32', 'F33'],
                true
            );
        } catch (Exception $e) {
            // Log error but continue with other templates
        }
        
        // Anxiety template
        $anxietyTemplate = <<<EOT
Create a comprehensive behavioral health treatment plan based on the following information.

Patient Information:
Age: {{age}}
Gender: {{gender}}

Clinical Observations:
{{observations}}

This patient appears to be experiencing symptoms of anxiety. Please structure the treatment plan with the following components:
1. Diagnosis/Problem Statement (e.g., Generalized Anxiety Disorder, Social Anxiety Disorder, Panic Disorder)
2. Long-term Goals (3-5) focused on anxiety reduction, coping strategies, and daily functioning
3. Short-term Objectives (measurable) related to specific anxiety symptoms
4. Interventions and Strategies (including cognitive-behavioral techniques, exposure therapy, relaxation training, and potentially medication management)
5. Timeline for Treatment (typical course and milestones)
6. Progress Measurement Methods (specific assessments like GAD-7, anxiety tracking)

Format the plan in a professional clinical manner appropriate for medical documentation.
EOT;

        try {
            $createdIds[] = $this->createTemplate(
                'Anxiety Treatment Plan',
                'Template for patients presenting with anxiety symptoms',
                $anxietyTemplate,
                ['anxiety', 'F41.1', 'F40'],
                true
            );
        } catch (Exception $e) {
            // Log error but continue
        }
        
        // ADHD template
        $adhdTemplate = <<<EOT
Create a comprehensive behavioral health treatment plan based on the following information.

Patient Information:
Age: {{age}}
Gender: {{gender}}

Clinical Observations:
{{observations}}

This patient appears to be experiencing symptoms of ADHD. Please structure the treatment plan with the following components:
1. Diagnosis/Problem Statement (e.g., ADHD, predominantly inattentive or hyperactive presentation)
2. Long-term Goals (3-5) focused on attention improvement, impulse control, and functional outcomes
3. Short-term Objectives (measurable) related to specific ADHD symptoms
4. Interventions and Strategies (including behavioral strategies, organizational skills, environmental modifications, and potentially medication management)
5. Timeline for Treatment (typical course and milestones)
6. Progress Measurement Methods (specific assessments, behavior tracking)

Format the plan in a professional clinical manner appropriate for medical documentation.
EOT;

        try {
            $createdIds[] = $this->createTemplate(
                'ADHD Treatment Plan',
                'Template for patients presenting with attention deficit symptoms',
                $adhdTemplate,
                ['ADHD', 'F90'],
                true
            );
        } catch (Exception $e) {
            // Log error but continue
        }
        
        return $createdIds;
    }
}
