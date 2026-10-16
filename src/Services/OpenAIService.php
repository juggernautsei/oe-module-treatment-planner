<?php

/**
 * OpenAIService.php
 * Service for OpenAI API calls used by Treatment Planner.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2025 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\TreatmentPlanner\Services;

use Exception;
use OpenEMR\Modules\TreatmentPlanner\Services\ConfigService;
use OpenEMR\Modules\TreatmentPlanner\Services\UsageService;
use OpenEMR\Modules\TreatmentPlanner\Services\PromptGenerationService;

/**
 * Service for interacting with OpenAI API to generate treatment plans
 */
class OpenAIService
{
    private ConfigService $configService;
    private ?UsageService $usageService;
    private PromptGenerationService $promptGenerationService;
    
    /**
     * OpenAIService constructor
     * 
     * @param ConfigService $configService Service for fetching API configuration
     * @param UsageService|null $usageService Optional service for tracking usage
     * @param PromptGenerationService $promptGenerationService Service for generating prompts
     */
    public function __construct(ConfigService $configService, ?UsageService $usageService = null, PromptGenerationService $promptGenerationService)
    {
        $this->configService = $configService;
        $this->usageService = $usageService;
        $this->promptGenerationService = $promptGenerationService;
    }
    
    /**
     * Generate a treatment plan using OpenAI based on comprehensive assessment data
     * 
     * @param string $assessmentData Formatted assessment data from AssessmentService
     * @param string $observations Additional clinical observations
     * @param array $options Additional options for generation
     * @param int $providerId Provider ID for usage tracking
     * 
     * @return array Generated treatment plan data
     * @throws Exception If generation fails
     */
    public function generateTreatmentPlan(
        string $assessmentData,
        string $observations = '',
        array $options = [],
        int $providerId = 0
    ): array {
        // Check if API key is configured
        $apiKey = $this->configService->getDecryptedApiKey();
        if (empty($apiKey)) {
            throw new Exception(xlt('OpenAI API key is not configured'));
        }
        
        // Generate the prompt for the treatment plan
        $prompt = $this->promptGenerationService->generatePrompt($assessmentData, $observations, $options);
        
        // Get model and temperature from configuration
        $model = $this->configService->getModel();
        $temperature = $this->configService->getTemperature();
        
        // Define request data
        $data = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a professional mental health or behavioral health provider assisting in creating a comprehensive treatment plan based on clinical observations. Provide structured plans with goals, interventions, and timeline.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => $temperature,
            'max_tokens' => 2000,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        ];
        
        // Track usage start
        $startTime = microtime(true);
        
        // Make the API request
        $response = $this->makeApiRequest($data);
        
        // Parse the response and extract the treatment plan
        $planData = $this->parseResponse($response);
        
        // Calculate tokens and usage
        $promptTokens = $this->estimateTokenCount($prompt);
        $completionTokens = $this->estimateTokenCount($planData['content']);
        $totalTokens = $promptTokens + $completionTokens;
        
        // Track API usage if usage service is available
        if ($this->usageService && $providerId > 0) {
            $this->usageService->trackUsage(
                $providerId,
                $model,
                $promptTokens,
                $completionTokens,
                microtime(true) - $startTime
            );
        }
        
        // Add metadata to the result
        $planData['metadata'] = [
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'options' => $options
        ];
        
        return $planData;
    }
    
    /**
     * Make an API request to OpenAI
     * 
     * @param array $data Request data
     * @return array Response data
     * @throws Exception If the API request fails
     */
    private function makeApiRequest(array $data): array
    {
        $apiKey = $this->configService->getDecryptedApiKey();
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $errorMessage = $error;
            if (!$errorMessage && $response) {
                $responseData = json_decode($response, true);
                $errorMessage = $responseData['error']['message'] ?? 'Unknown API error';
            }
            throw new Exception(xlt('OpenAI API Error') . ': ' . $errorMessage);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Parse the API response to extract the treatment plan
     * 
     * @param array $response API response
     * @return array Treatment plan data
     * @throws Exception If response parsing fails
     */
    private function parseResponse(array $response): array
    {
        if (!isset($response['choices'][0]['message']['content'])) {
            throw new Exception(xlt('Invalid response format from OpenAI API'));
        }
        
        $content = $response['choices'][0]['message']['content'];
        
        // Extract sections if possible (diagnosis, goals, etc.)
        $sections = $this->extractSections($content);
        
        return [
            'content' => $content,
            'sections' => $sections
        ];
    }
    
    /**
     * Try to extract structured sections from the treatment plan text
     * 
     * @param string $content The treatment plan content
     * @return array Extracted sections
     */
    private function extractSections(string $content): array
    {
        $sections = [];
        
        // Common section patterns to look for
        $sectionPatterns = [
            'diagnosis' => '/(?:diagnosis|problem statement|clinical impression)(?:\s*:|\s*\n)(.*?)(?=\n\s*\d\.|\n\s*[A-Z]|\Z)/is',
            'goals' => '/(?:long.?term goals|treatment goals)(?:\s*:|\s*\n)(.*?)(?=\n\s*\d\.|\n\s*[A-Z]|\Z)/is',
            'objectives' => '/(?:short.?term objectives|objectives|treatment objectives)(?:\s*:|\s*\n)(.*?)(?=\n\s*\d\.|\n\s*[A-Z]|\Z)/is',
            'interventions' => '/(?:interventions|strategies|treatment strategies)(?:\s*:|\s*\n)(.*?)(?=\n\s*\d\.|\n\s*[A-Z]|\Z)/is',
            'timeline' => '/(?:timeline|treatment duration|timeframe)(?:\s*:|\s*\n)(.*?)(?=\n\s*\d\.|\n\s*[A-Z]|\Z)/is',
            'progress' => '/(?:progress measurement|assessment|evaluation)(?:\s*:|\s*\n)(.*?)(?=\n\s*\d\.|\n\s*[A-Z]|\Z)/is'
        ];
        
        foreach ($sectionPatterns as $key => $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $sections[$key] = trim($matches[1]);
            }
        }
        
        return $sections;
    }
    
    /**
     * Estimate token count for a given text string
     * This is a simplified estimator as exact token count requires the tokenizer
     * 
     * @param string $text The text to estimate token count for
     * @return int Estimated token count
     */
    private function estimateTokenCount(string $text): int
    {
        // Simple approximation: average of 4 chars per token
        // OpenAI's tokenization is more complex, but this provides a rough estimate
        return (int)(mb_strlen($text) / 4);
    }
}
