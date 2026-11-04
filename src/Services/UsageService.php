<?php

/**
 * UsageService.php
 * Service for tracking OpenAI API usage metrics.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2025 Sherwin Gaddis <sherwingaddis@gmail.com>
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
    private array $pricing;

    /** Optional administrator-supplied USD rates per million tokens, keyed by exact model. */
    public function __construct(?array $pricing = null)
    {
        $this->pricing = $pricing ?? ($GLOBALS['treatment_planner_token_pricing'] ?? []);
    }
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
    private function calculateCost(string $model, int $promptTokens, int $completionTokens): ?float
    {
        $rates = $this->pricing[$model] ?? null;
        if (!is_array($rates) || !isset($rates['prompt'], $rates['completion'])
            || !is_numeric($rates['prompt']) || !is_numeric($rates['completion'])
            || !is_finite((float)$rates['prompt']) || !is_finite((float)$rates['completion'])
            || $rates['prompt'] < 0 || $rates['completion'] < 0) {
            return null;
        }
        return ($promptTokens * (float)$rates['prompt'] + $completionTokens * (float)$rates['completion']) / 1000000;
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
                    CASE WHEN SUM(estimated_cost IS NULL) > 0 THEN NULL ELSE SUM(estimated_cost) END as total_cost,
                    SUM(estimated_cost IS NULL) as unpriced_calls,
                    AVG(duration_seconds) as avg_duration
                FROM `module_treatment_planner_usage`
                WHERE $whereClause";
        
        $totalStats = QueryUtils::querySingleRow($sql, $params);
        
        // Get usage by model
        $sql = "SELECT 
                    model,
                    COUNT(*) as calls,
                    SUM(total_tokens) as tokens,
                    CASE WHEN SUM(estimated_cost IS NULL) > 0 THEN NULL ELSE SUM(estimated_cost) END as cost,
                    SUM(estimated_cost IS NULL) as unpriced_calls
                FROM `module_treatment_planner_usage`
                WHERE $whereClause
                GROUP BY model";
        
        $modelStats = QueryUtils::fetchRecords($sql, $params);
        
        // Get usage by provider
        $sql = "SELECT 
                    t.provider_id,
                    CONCAT(u.fname, ' ', u.lname) as provider_name,
                    COUNT(t.id) as calls,
                    SUM(t.total_tokens) as tokens,
                    CASE WHEN SUM(t.estimated_cost IS NULL) > 0 THEN NULL ELSE SUM(t.estimated_cost) END as cost,
                    SUM(t.estimated_cost IS NULL) as unpriced_calls
                FROM `module_treatment_planner_usage` t
                JOIN `users` u ON t.provider_id = u.id
                WHERE $whereClause
                GROUP BY t.provider_id, u.fname, u.lname
                ORDER BY tokens DESC";
        
        $providerStats = QueryUtils::fetchRecords($sql, $params);
        
        // Get usage by day
        $sql = "SELECT 
                    DATE(usage_datetime) as date,
                    COUNT(*) as calls,
                    SUM(total_tokens) as tokens,
                    CASE WHEN SUM(estimated_cost IS NULL) > 0 THEN NULL ELSE SUM(estimated_cost) END as cost,
                    SUM(estimated_cost IS NULL) as unpriced_calls
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
