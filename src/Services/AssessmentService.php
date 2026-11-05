<?php

/**
 * AssessmentService.php
 * Service for gathering assessment data used in treatment planning.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2025 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\TreatmentPlanner\Services;

use Exception;

/**
 * Service for extracting and structuring assessment data from LBF_MHINTAKE form
 */
class AssessmentService
{
    private array $listValueCache = [];
    /**
     * Extract comprehensive assessment data for AI processing
     *
     * @param int $pid Patient ID
     * @param int $encounter Encounter ID
     * @return array Structured assessment data
     * @throws Exception
     */
    public function extractAssessmentData(int $pid, int $encounter): array
    {
        if ($pid <= 0 || $encounter <= 0) {
            throw new Exception("Invalid patient ID or encounter ID");
        }

        try {
            // Get the form data
            $formData = $this->getFormData($pid, $encounter);
            
            if (empty($formData)) {
                throw new Exception("No LBF_MHINTAKE assessment data found for this encounter. Please ensure the assessment form has been completed and saved.");
            }

            // Structure the data for AI processing
            $structuredData = [
                'patient_info' => $this->extractPatientInfo($pid),
                'diagnoses' => $this->extractDiagnoses($pid),
                'presenting_problem' => $this->extractPresentingProblem($formData),
                'mental_status_exam' => $this->parseMentalStatusExam($formData),
                'risk_assessment' => $this->extractRiskAssessment($formData),
                'clinical_history' => $this->extractClinicalHistory($formData),
                'functional_assessment' => $this->extractFunctionalAssessment($formData),
                'diagnostic_impressions' => $this->extractDiagnosticImpressions($formData),
                'treatment_history' => $this->extractTreatmentHistory($formData),
                'psychosocial_factors' => $this->extractPsychosocialFactors($formData),
                'strengths_resources' => $this->extractStrengthsResources($formData)
            ];

            return $structuredData;

        } catch (Exception $e) {
            error_log("AssessmentService Error: " . $e->getMessage());
            throw new Exception("Failed to extract assessment data: " . $e->getMessage());
        }
    }

    /**
     * Get form data from LBF_MHINTAKE
     *
     * @param int $pid Patient ID
     * @param int $encounter Encounter ID
     * @return array Form data
     */
    private function getFormData(int $pid, int $encounter): array
    {
        $sql = "SELECT ld.field_id, ld.field_value, lo.title, lo.data_type, lo.list_id
                FROM forms f
                JOIN lbf_data ld ON f.form_id = ld.form_id
                JOIN layout_options lo ON ld.field_id = lo.field_id 
                    AND lo.form_id = 'LBF_MHINTAKE'
                WHERE f.pid = ? 
                AND f.encounter = ? 
                AND f.formdir = 'LBF_MHINTAKE'
                AND f.deleted = 0
                ORDER BY lo.seq";

        $result = sqlStatement($sql, [$pid, $encounter]);
        $formData = [];

        while ($row = sqlFetchArray($result)) {
            $formData[$row['field_id']] = [
                'value' => $row['field_value'],
                'title' => $row['title'],
                'data_type' => $row['data_type'],
                'list_id' => $row['list_id']
            ];
        }

        return $formData;
    }

    /**
     * Extract basic patient information
     *
     * @param int $pid Patient ID
     * @return array Patient info
     */
    private function extractPatientInfo(int $pid): array
    {
        $sql = "SELECT fname, lname, DOB, sex, race, ethnicity FROM patient_data WHERE pid = ?";
        $result = sqlQuery($sql, [$pid]);
        
        $age = null;
        if (!empty($result['DOB'])) {
            $birthDate = new \DateTime($result['DOB']);
            $today = new \DateTime();
            $age = $today->diff($birthDate)->y;
        }

        return [
            'age' => $age,
            'gender' => $result['sex'] ?? '',
            'race' => $result['race'] ?? ''
        ];
    }

    /**
     * Extract presenting problem information
     *
     * @param array $formData Form data
     * @return array Presenting problem data
     */
    private function extractPresentingProblem(array $formData): array
    {
        return [
            'chief_complaint' => $formData['reason_visit']['value'] ?? '',
            'onset' => $formData['problem_start']['value'] ?? '',
            'duration' => $formData['problem_duration']['value'] ?? '',
            'severity' => $formData['problem_severity']['value'] ?? '',
            'precipitating_factors' => $formData['precipitating_factors']['value'] ?? '',
            'current_stressors' => $formData['current_stressors']['value'] ?? ''
        ];
    }

    /**
     * Parse Mental Status Examination data (13 categories)
     *
     * @param array $formData Form data
     * @return array Structured MSE data
     */
    private function parseMentalStatusExam(array $formData): array
    {
        return [
            'general_appearance' => [
                'build' => $this->getListValue($formData['appearance_build']['value'] ?? '', $formData['appearance_build']['list_id'] ?? ''),
                'hygiene' => $this->getListValue($formData['appearance_hygiene']['value'] ?? '', $formData['appearance_hygiene']['list_id'] ?? ''),
                'dress' => $this->getListValue($formData['appearance_dress']['value'] ?? '', $formData['appearance_dress']['list_id'] ?? ''),
                'grooming' => $this->getListValue($formData['appearance_grooming']['value'] ?? '', $formData['appearance_grooming']['list_id'] ?? '')
            ],
            'sensorium' => [
                'orientation' => $this->getListValue($formData['orientation']['value'] ?? '', $formData['orientation']['list_id'] ?? ''),
                'consciousness' => $this->getListValue($formData['consciousness']['value'] ?? '', $formData['consciousness']['list_id'] ?? ''),
                'alertness' => $this->getListValue($formData['alertness']['value'] ?? '', $formData['alertness']['list_id'] ?? '')
            ],
            'cognitive_function' => [
                'concentration' => $this->getListValue($formData['concentration']['value'] ?? '', $formData['concentration']['list_id'] ?? ''),
                'attention' => $this->getListValue($formData['attention']['value'] ?? '', $formData['attention']['list_id'] ?? ''),
                'short_term_memory' => $this->getListValue($formData['memory_short']['value'] ?? '', $formData['memory_short']['list_id'] ?? ''),
                'long_term_memory' => $this->getListValue($formData['memory_long']['value'] ?? '', $formData['memory_long']['list_id'] ?? ''),
                'intelligence' => $this->getListValue($formData['intelligence']['value'] ?? '', $formData['intelligence']['list_id'] ?? '')
            ],
            'behavioral_observations' => [
                'eye_contact' => $this->getListValue($formData['eye_contact']['value'] ?? '', $formData['eye_contact']['list_id'] ?? ''),
                'gait' => $this->getListValue($formData['gait']['value'] ?? '', $formData['gait']['list_id'] ?? ''),
                'psychomotor' => $this->getListValue($formData['psychomotor']['value'] ?? '', $formData['psychomotor']['list_id'] ?? ''),
                'cooperation' => $this->getListValue($formData['cooperation']['value'] ?? '', $formData['cooperation']['list_id'] ?? '')
            ],
            'speech_language' => [
                'rate' => $this->getListValue($formData['speech_rate']['value'] ?? '', $formData['speech_rate']['list_id'] ?? ''),
                'volume' => $this->getListValue($formData['speech_volume']['value'] ?? '', $formData['speech_volume']['list_id'] ?? ''),
                'content' => $this->getListValue($formData['speech_content']['value'] ?? '', $formData['speech_content']['list_id'] ?? ''),
                'fluency' => $this->getListValue($formData['speech_fluency']['value'] ?? '', $formData['speech_fluency']['list_id'] ?? '')
            ],
            'mood_affect' => [
                'mood' => $this->getListValue($formData['mood']['value'] ?? '', $formData['mood']['list_id'] ?? ''),
                'affect_range' => $this->getListValue($formData['affect_range']['value'] ?? '', $formData['affect_range']['list_id'] ?? ''),
                'affect_amplitude' => $this->getListValue($formData['affect_amplitude']['value'] ?? '', $formData['affect_amplitude']['list_id'] ?? ''),
                'affect_appropriateness' => $this->getListValue($formData['affect_appropriate']['value'] ?? '', $formData['affect_appropriate']['list_id'] ?? '')
            ],
            'thought_process' => [
                'organization' => $this->getListValue($formData['thought_organization']['value'] ?? '', $formData['thought_organization']['list_id'] ?? ''),
                'coherence' => $this->getListValue($formData['thought_coherence']['value'] ?? '', $formData['thought_coherence']['list_id'] ?? ''),
                'goal_directed' => $this->getListValue($formData['thought_goal_directed']['value'] ?? '', $formData['thought_goal_directed']['list_id'] ?? '')
            ],
            'thought_content' => [
                'delusions' => $this->getListValue($formData['delusions']['value'] ?? '', $formData['delusions']['list_id'] ?? ''),
                'obsessions' => $this->getListValue($formData['obsessions']['value'] ?? '', $formData['obsessions']['list_id'] ?? ''),
                'phobias' => $this->getListValue($formData['phobias']['value'] ?? '', $formData['phobias']['list_id'] ?? ''),
                'suicidal_ideation' => $this->getListValue($formData['suicidal_ideation']['value'] ?? '', $formData['suicidal_ideation']['list_id'] ?? ''),
                'homicidal_ideation' => $this->getListValue($formData['homicidal_ideation']['value'] ?? '', $formData['homicidal_ideation']['list_id'] ?? '')
            ],
            'perceptual_disturbances' => [
                'hallucinations_auditory' => $this->getListValue($formData['hallucinations_auditory']['value'] ?? '', $formData['hallucinations_auditory']['list_id'] ?? ''),
                'hallucinations_visual' => $this->getListValue($formData['hallucinations_visual']['value'] ?? '', $formData['hallucinations_visual']['list_id'] ?? ''),
                'hallucinations_other' => $this->getListValue($formData['hallucinations_other']['value'] ?? '', $formData['hallucinations_other']['list_id'] ?? '')
            ],
            'insight_judgment' => [
                'insight' => $this->getListValue($formData['insight']['value'] ?? '', $formData['insight']['list_id'] ?? ''),
                'judgment' => $this->getListValue($formData['judgment']['value'] ?? '', $formData['judgment']['list_id'] ?? '')
            ]
        ];
    }

    /**
     * Extract risk assessment data
     *
     * @param array $formData Form data
     * @return array Risk assessment data
     */
    private function extractRiskAssessment(array $formData): array
    {
        return [
            'suicide_risk' => [
                'current_ideation' => $this->getListValue($formData['suicide_current']['value'] ?? '', $formData['suicide_current']['list_id'] ?? ''),
                'plan' => $formData['suicide_plan']['value'] ?? '',
                'means' => $formData['suicide_means']['value'] ?? '',
                'history' => $this->getListValue($formData['suicide_history']['value'] ?? '', $formData['suicide_history']['list_id'] ?? ''),
                'protective_factors' => $formData['suicide_protective']['value'] ?? ''
            ],
            'violence_risk' => [
                'current_ideation' => $this->getListValue($formData['violence_current']['value'] ?? '', $formData['violence_current']['list_id'] ?? ''),
                'history' => $this->getListValue($formData['violence_history']['value'] ?? '', $formData['violence_history']['list_id'] ?? ''),
                'triggers' => $formData['violence_triggers']['value'] ?? ''
            ],
            'substance_use' => [
                'current_use' => $this->getListValue($formData['substance_current']['value'] ?? '', $formData['substance_current']['list_id'] ?? ''),
                'substances' => $formData['substances_used']['value'] ?? '',
                'frequency' => $formData['substance_frequency']['value'] ?? '',
                'impact' => $formData['substance_impact']['value'] ?? ''
            ]
        ];
    }

    /**
     * Extract clinical history
     *
     * @param array $formData Form data
     * @return array Clinical history data
     */
    private function extractClinicalHistory(array $formData): array
    {
        return [
            'psychiatric_history' => $formData['psychiatric_history']['value'] ?? '',
            'medical_history' => $formData['medical_history']['value'] ?? '',
            'family_psychiatric_history' => $formData['family_psych_history']['value'] ?? '',
            'medications' => $formData['current_medications']['value'] ?? '',
            'allergies' => $formData['allergies']['value'] ?? ''
        ];
    }

    /**
     * Extract functional assessment
     *
     * @param array $formData Form data
     * @return array Functional assessment data
     */
    private function extractFunctionalAssessment(array $formData): array
    {
        return [
            'work_functioning' => $this->getListValue($formData['work_functioning']['value'] ?? '', $formData['work_functioning']['list_id'] ?? ''),
            'social_functioning' => $this->getListValue($formData['social_functioning']['value'] ?? '', $formData['social_functioning']['list_id'] ?? ''),
            'family_functioning' => $this->getListValue($formData['family_functioning']['value'] ?? '', $formData['family_functioning']['list_id'] ?? ''),
            'self_care' => $this->getListValue($formData['self_care']['value'] ?? '', $formData['self_care']['list_id'] ?? ''),
            'activities_daily_living' => $formData['adl_description']['value'] ?? ''
        ];
    }

    /**
     * Extract diagnostic impressions
     *
     * @param array $formData Form data
     * @return array Diagnostic data
     */
    private function extractDiagnosticImpressions(array $formData): array
    {
        return [
            'primary_diagnosis' => $formData['primary_diagnosis']['value'] ?? '',
            'secondary_diagnoses' => $formData['secondary_diagnoses']['value'] ?? '',
            'rule_out_diagnoses' => $formData['rule_out_diagnoses']['value'] ?? '',
            'severity' => $this->getListValue($formData['diagnosis_severity']['value'] ?? '', $formData['diagnosis_severity']['list_id'] ?? '')
        ];
    }

    /**
     * Extract treatment history
     *
     * @param array $formData Form data
     * @return array Treatment history data
     */
    private function extractTreatmentHistory(array $formData): array
    {
        return [
            'previous_therapy' => $formData['previous_therapy']['value'] ?? '',
            'previous_medications' => $formData['previous_medications']['value'] ?? '',
            'hospitalizations' => $formData['psychiatric_hospitalizations']['value'] ?? '',
            'treatment_response' => $formData['treatment_response']['value'] ?? ''
        ];
    }

    /**
     * Extract psychosocial factors
     *
     * @param array $formData Form data
     * @return array Psychosocial data
     */
    private function extractPsychosocialFactors(array $formData): array
    {
        return [
            'living_situation' => $formData['living_situation']['value'] ?? '',
            'support_system' => $formData['support_system']['value'] ?? '',
            'education' => $formData['education_level']['value'] ?? '',
            'employment' => $formData['employment_status']['value'] ?? '',
            'financial_stressors' => $formData['financial_stressors']['value'] ?? '',
            'legal_issues' => $formData['legal_issues']['value'] ?? '',
            'cultural_factors' => $formData['cultural_factors']['value'] ?? ''
        ];
    }

    /**
     * Extract strengths and resources
     *
     * @param array $formData Form data
     * @return array Strengths and resources data
     */
    private function extractStrengthsResources(array $formData): array
    {
        return [
            'personal_strengths' => $formData['personal_strengths']['value'] ?? '',
            'coping_skills' => $formData['coping_skills']['value'] ?? '',
            'motivation_change' => $this->getListValue($formData['motivation_change']['value'] ?? '', $formData['motivation_change']['list_id'] ?? ''),
            'treatment_goals' => $formData['treatment_goals']['value'] ?? '',
            'available_resources' => $formData['available_resources']['value'] ?? ''
        ];
    }

    /**
     * Extract patient diagnoses from lists table
     *
     * @param int $pid Patient ID
     * @return array Diagnoses data
     */
    private function extractDiagnoses(int $pid): array
    {
        $sql = "SELECT diagnosis, title
                FROM lists 
                WHERE type = 'medical_problem' 
                AND pid = ?";
        
        $result = sqlStatement($sql, [$pid]);
        $diagnoses = [];
        
        while ($row = sqlFetchArray($result)) {
            $diagnoses[] = [
                'diagnosis_code' => $row['diagnosis'] ?? '',
                'title' => $row['title'] ?? ''
            ];
        }
        
        return $diagnoses;
    }

    /**
     * Get human-readable value from list options
     *
     * @param string $value List option value
     * @return string Human-readable value
     */
    private function getListValue(string $value, string $listId = ''): string
    {
        if ($value === '' || $listId === '') { return $value; }
        $labels = [];
        foreach (explode('|', $value) as $option) {
            $option = trim($option);
            if ($option === '') { continue; }
            $key = $listId . ':' . $option;
            if (!array_key_exists($key, $this->listValueCache)) {
                $row = sqlQuery('SELECT title FROM list_options WHERE list_id = ? AND option_id = ? LIMIT 1', [$listId, $option]);
                $this->listValueCache[$key] = isset($row['title']) && $row['title'] !== ''
                    ? $row['title'] : 'Unrecognized option (' . $listId . '/' . $option . ')';
            }
            $labels[] = $this->listValueCache[$key];
        }
        return implode('; ', $labels);
    }

    /**
     * Format extracted data for AI consumption
     *
     * @param array $assessmentData Structured assessment data
     * @return string Formatted text for AI processing
     */
    public function formatForAI(array $assessmentData): string
    {
        // Only the fields explicitly extracted by this module are included. Keep
        // nested risk details, medication/allergy history and documented negatives.
        $sections = [
            'patient_info' => 'PATIENT', 'diagnoses' => 'CURRENT DIAGNOSES',
            'presenting_problem' => 'PRESENTING PROBLEM', 'mental_status_exam' => 'MENTAL STATUS EXAMINATION',
            'risk_assessment' => 'RISK ASSESSMENT', 'clinical_history' => 'CLINICAL HISTORY',
            'functional_assessment' => 'FUNCTIONAL ASSESSMENT', 'diagnostic_impressions' => 'DIAGNOSTIC IMPRESSIONS',
            'treatment_history' => 'TREATMENT HISTORY', 'psychosocial_factors' => 'PSYCHOSOCIAL FACTORS',
            'strengths_resources' => 'STRENGTHS AND RESOURCES'
        ];
        $formatted = "MENTAL HEALTH ASSESSMENT DATA:\n\n";
        foreach ($sections as $key => $heading) {
            $data = $assessmentData[$key] ?? [];
            if ($key === 'patient_info') {
                $data = array_intersect_key((array)$data, array_flip(['age', 'gender', 'race']));
            }
            $lines = $this->formatAssessmentFields((array)$data);
            if ($lines !== '') { $formatted .= $heading . ":\n" . $lines . "\n"; }
        }
        return $formatted;
    }

    private function formatAssessmentFields(array $data, string $prefix = ''): string
    {
        $text = '';
        foreach ($data as $key => $value) {
            $label = is_int($key) ? 'Item ' . ($key + 1) : ucwords(str_replace('_', ' ', $key));
            $path = $prefix === '' ? $label : $prefix . ' / ' . $label;
            if (is_array($value)) {
                $text .= $this->formatAssessmentFields($value, $path);
            } elseif ($value !== null && $value !== '') {
                $display = is_bool($value) ? ($value ? 'Yes' : 'No') : (string)$value;
                $text .= $path . ': ' . $display . "\n";
            }
        }
        return $text;
    }

    /**
     * Get comprehensive assessment data formatted for AI processing
     *
     * @param int $pid Patient ID
     * @param int $encounter Encounter ID
     * @return string Formatted assessment data for AI
     * @throws Exception
     */
    public function getComprehensiveAssessmentData(int $pid, int $encounter): string
    {
        $assessmentData = $this->extractAssessmentData($pid, $encounter);
        return $this->formatForAI($assessmentData);
    }
}
