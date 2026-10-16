<?php

/**
 * UsageService.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    [Author Name]
 * @copyright Copyright (c) 2025
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\TreatmentPlanner\Services;

use OpenEMR\Common\Database\QueryUtils;
use DateTime;

/**
 * Service for tracking and reporting on API usage
 */
class UsageService
{
    /**
     * Track API usage by provider
     * 
     * @param int $providerId Provider ID
     * @param string $model OpenAI model used
     * @param int $promptTokens Number of tokens in the prompt
     * @param int $completionTokens Number of tokens in the completion
     * @param float $duration Request duration in seconds
     * @return bool Whether the tracking was successful
     */
    public function trackUsage(
        int $providerId, 
        string $model, 
        int $promptTokens = 0, 
        int $completionTokens = 0, 
        float $duration = 0
    ): bool {
        // Calculate the total tokens
        $totalTokens = $promptTokens + $completionTokens;
        
        // Calculate approximate cost based on token usage and model
        // These are approximations and may need to be updated as OpenAI pricing changes
        $cost = $this->calculateCost($model, $promptTokens, $completionTokens);
        
        // Build the SQL query
        $sql = "INSERT INTO `module_treatment_planner_usage` 
                (`provider_id`, `usage_datetime`, `model`, `prompt_tokens`, 
                 `completion_tokens`, `total_tokens`, `duration_seconds`, `estimated_cost`) 
                VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $providerId,
            $model,
            $promptTokens,
            $completionTokens,
            $totalTokens,
            $duration,
            $cost
        ];
        
        return QueryUtils::sqlInsert($sql, $params) > 0;
    }
    
    /**
     * Calculate the estimated cost of an API call based on token usage and model
     * 
     * @param string $model OpenAI model used
     * @param int $promptTokens Number of tokens in the prompt
     * @param int $completionTokens Number of tokens in the completion
     * @return float Estimated cost in USD
     */
    private function calculateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        // Pricing as of 2023-2024, may need updates as OpenAI pricing changes
        $pricing = [
            'gpt-4' => [
                'prompt' => 0.03,      // $0.03 per 1K tokens 
                'completion' => 0.06   // $0.06 per 1K tokens
            ],
            'gpt-3.5-turbo' => [
                'prompt' => 0.0015,    // $0.0015 per 1K tokens
                'completion' => 0.002  // $0.002 per 1K tokens
            ]
        ];
        
        // Default to gpt-3.5-turbo pricing if model not found
        $modelPricing = $pricing[$model] ?? $pricing['gpt-3.5-turbo'];
        
        // Calculate cost
        $promptCost = ($promptTokens / 1000) * $modelPricing['prompt'];
        $completionCost = ($completionTokens / 1000) * $modelPricing['completion'];
        
        return $promptCost + $completionCost;
    }
    
    /**
     * Get usage statistics by date range
     * 
     * @param string|null $startDate Start date in YYYY-MM-DD format, null for all time
     * @param string|null $endDate End date in YYYY-MM-DD format, defaults to today
     * @param int $providerId Optional provider ID to filter by
     * @return array Usage statistics
     */
    public function getUsageStats(?string $startDate = null, ?string $endDate = null, int $providerId = 0): array
    {
        // Default end date to today if not provided
        if (empty($endDate)) {
            $endDate = date('Y-m-d');
        }
        
        // Build the WHERE clause
        $whereClause = "";
        $params = [];
        
        if (!empty($startDate)) {
            $whereClause .= "usage_datetime >= ? AND ";
            $params[] = $startDate . ' 00:00:00';
        }
        
        $whereClause .= "usage_datetime <= ?";
        $params[] = $endDate . ' 23:59:59';
        
        if ($providerId > 0) {
            $whereClause .= " AND provider_id = ?";
            $params[] = $providerId;
        }
        
        // Get total usage
        $sql = "SELECT 
                    COUNT(*) as total_calls,
                    SUM(total_tokens) as total_tokens,
                    SUM(estimated_cost) as total_cost,
                    AVG(duration_seconds) as avg_duration
                FROM `module_treatment_planner_usage`
                WHERE $whereClause";
        
        $totalStats = QueryUtils::querySingleRow($sql, $params);
        
        // Get usage by model
        $sql = "SELECT 
                    model,
                    COUNT(*) as calls,
                    SUM(total_tokens) as tokens,
                    SUM(estimated_cost) as cost
                FROM `module_treatment_planner_usage`
                WHERE $whereClause
                GROUP BY model";
        
        $modelStats = QueryUtils::fetchRecords($sql, $params);
        
        // Get usage by provider
        $sql = "SELECT 
                    u.provider_id,
                    CONCAT(u.fname, ' ', u.lname) as provider_name,
                    COUNT(t.id) as calls,
                    SUM(t.total_tokens) as tokens,
                    SUM(t.estimated_cost) as cost
                FROM `module_treatment_planner_usage` t
                JOIN `users` u ON t.provider_id = u.id
                WHERE $whereClause
                GROUP BY t.provider_id
                ORDER BY tokens DESC";
        
        $providerStats = QueryUtils::fetchRecords($sql, $params);
        
        // Get usage by day
        $sql = "SELECT 
                    DATE(usage_datetime) as date,
                    COUNT(*) as calls,
                    SUM(total_tokens) as tokens,
                    SUM(estimated_cost) as cost
                FROM `module_treatment_planner_usage`
                WHERE $whereClause
                GROUP BY DATE(usage_datetime)
                ORDER BY date";
        
        $dailyStats = QueryUtils::fetchRecords($sql, $params);
        
        return [
            'total' => $totalStats,
            'by_model' => $modelStats,
            'by_provider' => $providerStats,
            'by_date' => $dailyStats,
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ];
    }
}
