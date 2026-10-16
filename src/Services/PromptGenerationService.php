<?php

/**
 * PromptGenerationService.php
 * Service for building AI prompts for treatment plan generation.
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
 * Service for generating dynamic AI prompts based on assessment data and diagnoses
 */
class PromptGenerationService
{
    /**
     * Generate a dynamic prompt based on assessment data and diagnoses
     *
     * @param string $assessmentData Formatted assessment data
     * @param string $observations Additional clinical observations
     * @param array $options Additional options
     * @return string Generated prompt
     */
    public function generatePrompt(string $assessmentData, string $observations = '', array $options = []): string
    {
        // Analyze the assessment data to identify key themes and conditions
        $analysis = $this->analyzeAssessmentData($assessmentData);

        // Generate condition-specific prompt based on analysis
        $prompt = $this->buildConditionSpecificPrompt($analysis, $assessmentData, $observations, $options);

        return $prompt;
    }

    /**
     * Analyze assessment data to identify key clinical themes and conditions
     *
     * @param string $assessmentData Formatted assessment data
     * @return array Analysis results with identified themes and conditions
     */
    private function analyzeAssessmentData(string $assessmentData): array
    {
        $analysis = [
            'primary_conditions' => [],
            'risk_factors' => [],
            'treatment_priorities' => [],
            'special_considerations' => [],
            'prompt_type' => 'general'
        ];

        // Convert to lowercase for case-insensitive matching
        $dataLower = strtolower($assessmentData);

        // Identify primary mental health conditions
        $conditions = $this->identifyPrimaryConditions($dataLower);
        $analysis['primary_conditions'] = $conditions;

        // Identify risk factors
        $analysis['risk_factors'] = $this->identifyRiskFactors($dataLower);

        // Determine treatment priorities
        $analysis['treatment_priorities'] = $this->determineTreatmentPriorities($dataLower, $conditions);

        // Identify special considerations
        $analysis['special_considerations'] = $this->identifySpecialConsiderations($dataLower);

        // Determine the most appropriate prompt type
        $analysis['prompt_type'] = $this->determinePromptType($conditions, $analysis['risk_factors']);

        return $analysis;
    }

    /**
     * Identify primary mental health conditions from assessment data
     *
     * @param string $dataLower Lowercase assessment data
     * @return array List of identified conditions
     */
    private function identifyPrimaryConditions(string $dataLower): array
    {
        $conditions = [];

        // Define condition patterns and their identifiers - more specific patterns
        $conditionPatterns = [
            'depression' => ['major depressive disorder', 'depressive disorder', 'depression', 'f33.', 'f32.'],
            'anxiety' => ['generalized anxiety disorder', 'anxiety disorder', 'panic disorder', 'social anxiety', 'f41.', 'f40.'],
            'bipolar' => ['bipolar disorder', 'manic episode', 'f31.'],
            'ptsd' => ['post-traumatic stress disorder', 'ptsd', 'f43.10', 'trauma', 'flashbacks'],
            'adhd' => ['attention deficit', 'adhd', 'hyperactivity disorder', 'f90.'],
            'substance_abuse' => ['alcohol use disorder', 'substance use disorder', 'drug use disorder', 'f10.', 'f11.', 'f12.', 'f13.', 'f14.', 'f15.', 'f16.', 'f17.', 'f18.', 'f19.'],
            'psychosis' => ['schizophrenia', 'psychotic disorder', 'f20.', 'f25.'],
            'eating_disorder' => ['anorexia nervosa', 'bulimia nervosa', 'eating disorder', 'f50.'],
            'personality_disorder' => ['borderline personality', 'personality disorder', 'f60.'],
            'ocd' => ['obsessive-compulsive disorder', 'ocd', 'f42.']
        ];

        foreach ($conditionPatterns as $condition => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($dataLower, $pattern) !== false) {
                    $conditions[] = $condition;
                    break; // Found this condition, move to next
                }
            }
        }

        return array_unique($conditions);
    }

    /**
     * Identify risk factors from assessment data
     *
     * @param string $dataLower Lowercase assessment data
     * @return array List of identified risk factors
     */
    private function identifyRiskFactors(string $dataLower): array
    {
        $riskFactors = [];

        // More specific risk patterns to avoid false positives
        $riskPatterns = [
            'suicide_risk' => ['suicidal ideation with plan', 'suicide plan', 'high suicide risk', 'immediate suicide risk'],
            'violence_risk' => ['high violence risk', 'immediate violence risk', 'violent threats', 'harm others plan'],
            'substance_risk' => ['active substance abuse', 'current drug use', 'intoxicated', 'withdrawal'],
            'self_harm' => ['active self-harm', 'cutting behavior', 'self-injury plan'],
            'crisis' => ['crisis intervention needed', 'emergency situation', 'immediate danger', 'urgent psychiatric']
        ];

        foreach ($riskPatterns as $risk => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($dataLower, $pattern) !== false) {
                    $riskFactors[] = $risk;
                    break;
                }
            }
        }

        // Additional specific checks for high-risk indicators
        if (strpos($dataLower, 'suicide risk: high') !== false ||
            strpos($dataLower, 'suicidal thoughts') !== false && strpos($dataLower, 'plan') !== false) {
            $riskFactors[] = 'suicide_risk';
        }

        if (strpos($dataLower, 'violence risk: high') !== false) {
            $riskFactors[] = 'violence_risk';
        }

        return array_unique($riskFactors);
    }

    /**
     * Determine treatment priorities based on conditions and data
     *
     * @param string $dataLower Lowercase assessment data
     * @param array $conditions Identified conditions
     * @return array Treatment priorities
     */
    private function determineTreatmentPriorities(string $dataLower, array $conditions): array
    {
        $priorities = [];

        // Safety first - if risk factors present
        if (strpos($dataLower, 'suicidal') !== false || strpos($dataLower, 'violence') !== false) {
            $priorities[] = 'safety_stabilization';
        }

        // Condition-specific priorities
        if (in_array('substance_abuse', $conditions)) {
            $priorities[] = 'substance_treatment';
        }

        if (in_array('depression', $conditions) || in_array('anxiety', $conditions)) {
            $priorities[] = 'mood_stabilization';
        }

        if (in_array('ptsd', $conditions)) {
            $priorities[] = 'trauma_processing';
        }

        // Functional priorities
        if (strpos($dataLower, 'functional') !== false || strpos($dataLower, 'daily living') !== false) {
            $priorities[] = 'functional_improvement';
        }

        return $priorities;
    }

    /**
     * Identify special considerations for treatment planning
     *
     * @param string $dataLower Lowercase assessment data
     * @return array Special considerations
     */
    private function identifySpecialConsiderations(string $dataLower): array
    {
        $considerations = [];

        // Age-related considerations
        if (strpos($dataLower, 'age: 1') !== false || strpos($dataLower, 'adolescent') !== false) {
            $considerations[] = 'adolescent_treatment';
        }

        if (strpos($dataLower, 'age: 6') !== false || strpos($dataLower, 'age: 7') !== false || strpos($dataLower, 'elderly') !== false) {
            $considerations[] = 'geriatric_treatment';
        }

        // Cultural considerations
        if (strpos($dataLower, 'cultural') !== false || strpos($dataLower, 'ethnic') !== false) {
            $considerations[] = 'cultural_sensitivity';
        }

        // Family considerations
        if (strpos($dataLower, 'family') !== false || strpos($dataLower, 'spouse') !== false) {
            $considerations[] = 'family_involvement';
        }

        // Medication considerations
        if (strpos($dataLower, 'medication') !== false || strpos($dataLower, 'psychiatric') !== false) {
            $considerations[] = 'medication_management';
        }

        return $considerations;
    }

    /**
     * Determine the most appropriate prompt type based on analysis
     *
     * @param array $conditions Identified conditions
     * @param array $riskFactors Identified risk factors
     * @return string Prompt type
     */
    private function determinePromptType(array $conditions, array $riskFactors): string
    {
        // Crisis/high-risk prompt - highest priority
        if (!empty($riskFactors)) {
            return 'crisis_intervention';
        }

        // Single condition priorities
        if (count($conditions) == 1) {
            $condition = $conditions[0];

            if ($condition === 'substance_abuse') {
                return 'substance_abuse';
            }
            if ($condition === 'ptsd') {
                return 'trauma_focused';
            }
            if ($condition === 'depression' || $condition === 'bipolar') {
                return 'mood_disorder';
            }
            if ($condition === 'anxiety') {
                return 'anxiety_disorder';
            }
        }

        // Multiple conditions - prioritize by clinical severity
        if (count($conditions) > 1) {
            // Substance abuse takes priority when present with other conditions
            if (in_array('substance_abuse', $conditions)) {
                return 'substance_abuse';
            }

            // PTSD takes priority over mood/anxiety disorders
            if (in_array('ptsd', $conditions)) {
                return 'trauma_focused';
            }

            // Mood disorders (depression/bipolar) take priority over anxiety
            if (in_array('depression', $conditions) || in_array('bipolar', $conditions)) {
                return 'mood_disorder';
            }

            // Anxiety if present
            if (in_array('anxiety', $conditions)) {
                return 'anxiety_disorder';
            }
        }

        // Default general prompt
        return 'general';
    }

    /**
     * Build condition-specific prompt based on analysis
     *
     * @param array $analysis Assessment analysis results
     * @param string $assessmentData Original assessment data
     * @param string $observations Additional observations
     * @param array $options Additional options
     * @return string Generated prompt
     */
    private function buildConditionSpecificPrompt(array $analysis, string $assessmentData, string $observations, array $options): string
    {
        $promptType = $analysis['prompt_type'];

        // Get the base prompt template for this condition type
        $basePrompt = $this->getPromptTemplate($promptType);

        // Add condition-specific instructions
        $conditionInstructions = $this->getConditionSpecificInstructions($analysis);

        // Build the complete prompt
        $prompt = $basePrompt . "\n\n" . $conditionInstructions;

        // Add assessment data section
        $prompt .= "\n\nDOCUMENTATION:\n" . $assessmentData;

        // Add additional observations if provided
        if (!empty($observations)) {
            $prompt .= "\n\nADDITIONAL CLINICAL OBSERVATIONS:\n" . $observations;
        }

        // Add specific formatting requirements
        $prompt .= $this->getFormattingInstructions($analysis);

        return $prompt;
    }

    /**
     * Get base prompt template for specific condition type
     *
     * @param string $promptType Type of prompt needed
     * @return string Base prompt template
     */
    private function getPromptTemplate(string $promptType): string
    {
        $baseTherapistRole = "You are a licensed mental health therapist with extensive clinical experience. Based on the following comprehensive assessment data and clinical observations, create a professional treatment plan that follows evidence-based practices and current clinical standards.";

        $templates = [
            'crisis_intervention' => $baseTherapistRole . " Focus on immediate crisis intervention and safety-focused treatment planning. Prioritize safety stabilization, risk mitigation, and immediate support resources while maintaining therapeutic rapport and hope.",

            'substance_abuse' => $baseTherapistRole . " Develop a comprehensive substance abuse treatment plan that addresses addiction across all stages of recovery, incorporates motivational interviewing principles, addresses co-occurring mental health conditions, and includes relapse prevention strategies.",

            'trauma_focused' => $baseTherapistRole . " Create a trauma-informed treatment plan that addresses PTSD symptoms and trauma processing using evidence-based approaches. Consider the patient's readiness for trauma work, safety and stabilization needs, and gradual exposure techniques while maintaining a client-centered approach.",

            'mood_disorder' => $baseTherapistRole . " Develop a treatment plan focused on mood stabilization and management of depression/bipolar symptoms. Incorporate cognitive-behavioral interventions, behavioral activation techniques, and consider medication evaluation when clinically appropriate.",

            'anxiety_disorder' => $baseTherapistRole . " Create an anxiety-focused treatment plan that addresses specific anxiety symptoms using evidence-based interventions such as cognitive-behavioral therapy, exposure techniques, and mindfulness-based approaches to improve functional capacity.",

            'general' => $baseTherapistRole . " Create a comprehensive behavioral health treatment plan based on the patient's unique needs, clinical presentation, and assessment findings using a client-centered, strengths-based approach."
        ];

        return $templates[$promptType] ?? $templates['general'];
    }

    /**
     * Get condition-specific instructions based on analysis
     *
     * @param array $analysis Assessment analysis results
     * @return string Condition-specific instructions
     */
    private function getConditionSpecificInstructions(array $analysis): string
    {
        $instructions = [];

        // Risk factor instructions
        if (in_array('suicide_risk', $analysis['risk_factors'])) {
            $instructions[] = "- Include immediate safety planning and suicide risk assessment";
            $instructions[] = "- Specify crisis intervention resources and emergency contacts";
        }

        if (in_array('violence_risk', $analysis['risk_factors'])) {
            $instructions[] = "- Address violence risk management and safety protocols";
        }

        // Condition-specific instructions
        if (in_array('substance_abuse', $analysis['primary_conditions'])) {
            $instructions[] = "- Include stages of change assessment and motivational interviewing techniques";
            $instructions[] = "- Address withdrawal management and relapse prevention strategies";
        }

        if (in_array('ptsd', $analysis['primary_conditions'])) {
            $instructions[] = "- Use trauma-informed care principles";
            $instructions[] = "- Consider EMDR, CPT, or PE therapy modalities";
        }

        if (in_array('depression', $analysis['primary_conditions'])) {
            $instructions[] = "- Include behavioral activation and cognitive restructuring techniques";
            $instructions[] = "- Consider medication evaluation if appropriate";
        }

        // Special considerations
        if (in_array('adolescent_treatment', $analysis['special_considerations'])) {
            $instructions[] = "- Use developmentally appropriate interventions for adolescents";
            $instructions[] = "- Include family involvement and school considerations";
        }

        if (in_array('cultural_sensitivity', $analysis['special_considerations'])) {
            $instructions[] = "- Incorporate cultural factors and culturally responsive interventions";
        }

        if (empty($instructions)) {
            return "Focus on evidence-based interventions appropriate for the identified conditions and patient needs.";
        }

        return "SPECIFIC CLINICAL CONSIDERATIONS:\n" . implode("\n", $instructions);
    }

    /**
     * Get formatting instructions for the treatment plan
     *
     * @param array $analysis Assessment analysis results
     * @return string Formatting instructions
     */
    private function getFormattingInstructions(array $analysis): string
    {
        $baseFormat = "

PLEASE CREATE A TREATMENT PLAN THAT INCLUDES THE FOLLOWING 6 COMPONENTS:

1. DIAGNOSIS (if known)
   - Primary diagnosis with DSM-5 codes if applicable
   - Secondary/co-occurring diagnoses
   - Differential diagnoses to consider
   - Clinical presentation summary

2. TREATMENT GOALS
   - 3-5 primary treatment goals
   - Long-term therapeutic objectives (3-6 months)
   - Client-centered and strength-based goals
   - Aligned with client's values and priorities

3. OBJECTIVES (measurable and time-bound)
   - 2-3 specific objectives per treatment goal
   - Measurable behavioral indicators
   - Realistic timeframes (2-8 weeks per objective)
   - SMART criteria (Specific, Measurable, Achievable, Relevant, Time-bound)

4. INTERVENTIONS (evidence-based therapeutic techniques)
   - Specific therapeutic modalities and approaches
   - Evidence-based techniques and strategies
   - Frequency and duration of interventions
   - Rationale for selected interventions

5. TIMELINE (expected duration for each objective and overall treatment course)
   - Short-term objectives timeline (1-8 weeks)
   - Medium-term goals timeline (2-4 months)
   - Overall treatment duration estimate
   - Key milestone checkpoints

6. CRITERIA FOR SUCCESS (specific indicators that demonstrate progress or completion)
   - Objective measures of progress
   - Behavioral indicators of improvement
   - Functional improvement markers
   - Client self-report measures
   - Clinical assessment tools for progress monitoring

ENSURE THE TREATMENT PLAN IS:
- Client-centered and culturally responsive
- Evidence-based and clinically appropriate
- Tailored to this specific client's needs and circumstances
- Aligned with current clinical standards and best practices
- Focused on measurable outcomes and realistic expectations";

        // Add crisis-specific formatting if needed
        if (in_array('suicide_risk', $analysis['risk_factors']) || in_array('violence_risk', $analysis['risk_factors'])) {
            $baseFormat = "

PRIORITY: SAFETY PLANNING - Include a detailed safety plan as the primary intervention with:
- Specific warning signs and triggers
- Coping strategies and self-soothing techniques
- Support person contacts and crisis resources
- Emergency contact information
- Steps to ensure immediate safety

" . $baseFormat;
        }

        return $baseFormat;
    }
}
