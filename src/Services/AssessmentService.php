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
                'build' => $this->getListValue($formData['appearance_build']['value'] ?? ''),
                'hygiene' => $this->getListValue($formData['appearance_hygiene']['value'] ?? ''),
                'dress' => $this->getListValue($formData['appearance_dress']['value'] ?? ''),
                'grooming' => $this->getListValue($formData['appearance_grooming']['value'] ?? '')
            ],
            'sensorium' => [
                'orientation' => $this->getListValue($formData['orientation']['value'] ?? ''),
                'consciousness' => $this->getListValue($formData['consciousness']['value'] ?? ''),
                'alertness' => $this->getListValue($formData['alertness']['value'] ?? '')
            ],
            'cognitive_function' => [
                'concentration' => $this->getListValue($formData['concentration']['value'] ?? ''),
                'attention' => $this->getListValue($formData['attention']['value'] ?? ''),
                'short_term_memory' => $this->getListValue($formData['memory_short']['value'] ?? ''),
                'long_term_memory' => $this->getListValue($formData['memory_long']['value'] ?? ''),
                'intelligence' => $this->getListValue($formData['intelligence']['value'] ?? '')
            ],
            'behavioral_observations' => [
                'eye_contact' => $this->getListValue($formData['eye_contact']['value'] ?? ''),
                'gait' => $this->getListValue($formData['gait']['value'] ?? ''),
                'psychomotor' => $this->getListValue($formData['psychomotor']['value'] ?? ''),
                'cooperation' => $this->getListValue($formData['cooperation']['value'] ?? '')
            ],
            'speech_language' => [
                'rate' => $this->getListValue($formData['speech_rate']['value'] ?? ''),
                'volume' => $this->getListValue($formData['speech_volume']['value'] ?? ''),
                'content' => $this->getListValue($formData['speech_content']['value'] ?? ''),
                'fluency' => $this->getListValue($formData['speech_fluency']['value'] ?? '')
            ],
            'mood_affect' => [
                'mood' => $this->getListValue($formData['mood']['value'] ?? ''),
                'affect_range' => $this->getListValue($formData['affect_range']['value'] ?? ''),
                'affect_amplitude' => $this->getListValue($formData['affect_amplitude']['value'] ?? ''),
                'affect_appropriateness' => $this->getListValue($formData['affect_appropriate']['value'] ?? '')
            ],
            'thought_process' => [
                'organization' => $this->getListValue($formData['thought_organization']['value'] ?? ''),
                'coherence' => $this->getListValue($formData['thought_coherence']['value'] ?? ''),
                'goal_directed' => $this->getListValue($formData['thought_goal_directed']['value'] ?? '')
            ],
            'thought_content' => [
                'delusions' => $this->getListValue($formData['delusions']['value'] ?? ''),
                'obsessions' => $this->getListValue($formData['obsessions']['value'] ?? ''),
                'phobias' => $this->getListValue($formData['phobias']['value'] ?? ''),
                'suicidal_ideation' => $this->getListValue($formData['suicidal_ideation']['value'] ?? ''),
                'homicidal_ideation' => $this->getListValue($formData['homicidal_ideation']['value'] ?? '')
            ],
            'perceptual_disturbances' => [
                'hallucinations_auditory' => $this->getListValue($formData['hallucinations_auditory']['value'] ?? ''),
                'hallucinations_visual' => $this->getListValue($formData['hallucinations_visual']['value'] ?? ''),
                'hallucinations_other' => $this->getListValue($formData['hallucinations_other']['value'] ?? '')
            ],
            'insight_judgment' => [
                'insight' => $this->getListValue($formData['insight']['value'] ?? ''),
                'judgment' => $this->getListValue($formData['judgment']['value'] ?? '')
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
                'current_ideation' => $this->getListValue($formData['suicide_current']['value'] ?? ''),
                'plan' => $formData['suicide_plan']['value'] ?? '',
                'means' => $formData['suicide_means']['value'] ?? '',
                'history' => $this->getListValue($formData['suicide_history']['value'] ?? ''),
                'protective_factors' => $formData['suicide_protective']['value'] ?? ''
            ],
            'violence_risk' => [
                'current_ideation' => $this->getListValue($formData['violence_current']['value'] ?? ''),
                'history' => $this->getListValue($formData['violence_history']['value'] ?? ''),
                'triggers' => $formData['violence_triggers']['value'] ?? ''
            ],
            'substance_use' => [
                'current_use' => $this->getListValue($formData['substance_current']['value'] ?? ''),
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
            'work_functioning' => $this->getListValue($formData['work_functioning']['value'] ?? ''),
            'social_functioning' => $this->getListValue($formData['social_functioning']['value'] ?? ''),
            'family_functioning' => $this->getListValue($formData['family_functioning']['value'] ?? ''),
            'self_care' => $this->getListValue($formData['self_care']['value'] ?? ''),
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
            'severity' => $this->getListValue($formData['diagnosis_severity']['value'] ?? '')
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
            'motivation_change' => $this->getListValue($formData['motivation_change']['value'] ?? ''),
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
    private function getListValue(string $value): string
    {
        if (empty($value)) {
            return '';
        }

        // For now, return the value as-is
        // In a full implementation, you would query the list_options table
        // to get the human-readable title for the option
        return $value;
    }

    /**
     * Format extracted data for AI consumption
     *
     * @param array $assessmentData Structured assessment data
     * @return string Formatted text for AI processing
     */
    public function formatForAI(array $assessmentData): string
    {
        $formatted = "MENTAL HEALTH ASSESSMENT DATA:\n\n";

        // Patient Information
        if (!empty($assessmentData['patient_info'])) {
            $patient = $assessmentData['patient_info'];
            $formatted .= "PATIENT: Age: {$patient['age']}, Gender: {$patient['gender']}, Race: {$patient['race']}\n\n";
        }

        // Current Diagnoses
        if (!empty($assessmentData['diagnoses'])) {
            $formatted .= "CURRENT DIAGNOSES:\n";
            foreach ($assessmentData['diagnoses'] as $diagnosis) {
                $formatted .= "- {$diagnosis['title']}";
                if (!empty($diagnosis['diagnosis_code'])) {
                    $formatted .= " ({$diagnosis['diagnosis_code']})";
                }
                $formatted .= "\n";
            }
            $formatted .= "\n";
        }

        // Presenting Problem
        if (!empty($assessmentData['presenting_problem']['chief_complaint'])) {
            $formatted .= "PRESENTING PROBLEM:\n";
            $formatted .= "Chief Complaint: {$assessmentData['presenting_problem']['chief_complaint']}\n";
            if (!empty($assessmentData['presenting_problem']['onset'])) {
                $formatted .= "Onset: {$assessmentData['presenting_problem']['onset']}\n";
            }
            if (!empty($assessmentData['presenting_problem']['current_stressors'])) {
                $formatted .= "Current Stressors: {$assessmentData['presenting_problem']['current_stressors']}\n";
            }
            $formatted .= "\n";
        }

        // Mental Status Exam Summary
        $formatted .= "MENTAL STATUS EXAMINATION:\n";
        $mse = $assessmentData['mental_status_exam'] ?? [];
        
        if (!empty($mse['mood_affect']['mood'])) {
            $formatted .= "Mood: {$mse['mood_affect']['mood']}\n";
        }
        if (!empty($mse['cognitive_function']['concentration'])) {
            $formatted .= "Concentration: {$mse['cognitive_function']['concentration']}\n";
        }
        if (!empty($mse['insight_judgment']['insight'])) {
            $formatted .= "Insight: {$mse['insight_judgment']['insight']}\n";
        }
        $formatted .= "\n";

        // Risk Assessment
        $risk = $assessmentData['risk_assessment'] ?? [];
        if (!empty($risk['suicide_risk']['current_ideation']) || !empty($risk['violence_risk']['current_ideation'])) {
            $formatted .= "RISK ASSESSMENT:\n";
            if (!empty($risk['suicide_risk']['current_ideation'])) {
                $formatted .= "Suicide Risk: {$risk['suicide_risk']['current_ideation']}\n";
            }
            if (!empty($risk['violence_risk']['current_ideation'])) {
                $formatted .= "Violence Risk: {$risk['violence_risk']['current_ideation']}\n";
            }
            $formatted .= "\n";
        }

        // Diagnostic Impressions
        if (!empty($assessmentData['diagnostic_impressions']['primary_diagnosis'])) {
            $formatted .= "DIAGNOSTIC IMPRESSIONS:\n";
            $formatted .= "Primary: {$assessmentData['diagnostic_impressions']['primary_diagnosis']}\n";
            if (!empty($assessmentData['diagnostic_impressions']['secondary_diagnoses'])) {
                $formatted .= "Secondary: {$assessmentData['diagnostic_impressions']['secondary_diagnoses']}\n";
            }
            $formatted .= "\n";
        }

        // Functional Assessment
        $functional = $assessmentData['functional_assessment'] ?? [];
        if (!empty($functional['work_functioning']) || !empty($functional['social_functioning'])) {
            $formatted .= "FUNCTIONAL ASSESSMENT:\n";
            if (!empty($functional['work_functioning'])) {
                $formatted .= "Work Functioning: {$functional['work_functioning']}\n";
            }
            if (!empty($functional['social_functioning'])) {
                $formatted .= "Social Functioning: {$functional['social_functioning']}\n";
            }
            $formatted .= "\n";
        }

        // Treatment History
        if (!empty($assessmentData['treatment_history']['previous_therapy'])) {
            $formatted .= "TREATMENT HISTORY:\n";
            $formatted .= "Previous Therapy: {$assessmentData['treatment_history']['previous_therapy']}\n";
            if (!empty($assessmentData['treatment_history']['treatment_response'])) {
                $formatted .= "Treatment Response: {$assessmentData['treatment_history']['treatment_response']}\n";
            }
            $formatted .= "\n";
        }

        // Strengths and Goals
        if (!empty($assessmentData['strengths_resources']['treatment_goals'])) {
            $formatted .= "TREATMENT GOALS:\n";
            $formatted .= $assessmentData['strengths_resources']['treatment_goals'] . "\n\n";
        }

        return $formatted;
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
