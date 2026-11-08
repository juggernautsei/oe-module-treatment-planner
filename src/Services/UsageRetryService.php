<?php
namespace OpenEMR\Modules\TreatmentPlanner\Services;

use OpenEMR\Common\Database\QueryUtils;

/** No clinical content: a usage-only queue, consumed transactionally without calling AI. */
class UsageRetryService
{
    public function record(array $usage): bool
    {
        $key = bin2hex(random_bytes(16));
        try {
            QueryUtils::sqlStatementThrowException('INSERT INTO module_treatment_planner_usage_queue
                (event_key, provider_id, usage_datetime, model, prompt_tokens, completion_tokens, duration_seconds, estimated_cost)
                VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)', [$key, $usage['provider_id'], $usage['model'],
                $usage['prompt_tokens'], $usage['completion_tokens'], $usage['duration_seconds'], $usage['estimated_cost']]);
        } catch (\Throwable $e) {
            // Even queue unavailability must not discard a successful AI response.
            // Keep only usage metadata for operator reconciliation, never notes or credentials.
            error_log('Treatment Planner usage queue unavailable: ' . json_encode(['event_key' => $key, 'usage' => $usage]));
            return false;
        }
        return $this->process($key);
    }

    public function process(string $key): bool
    {
        try {
            QueryUtils::sqlStatementThrowException('START TRANSACTION');
            $row = QueryUtils::querySingleRow('SELECT * FROM module_treatment_planner_usage_queue WHERE event_key = ? FOR UPDATE', [$key]);
            if ($row) {
                QueryUtils::sqlStatementThrowException('INSERT INTO module_treatment_planner_usage
                    (provider_id, usage_datetime, model, prompt_tokens, completion_tokens, total_tokens, duration_seconds, estimated_cost)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$row['provider_id'], $row['usage_datetime'], $row['model'],
                    $row['prompt_tokens'], $row['completion_tokens'], $row['prompt_tokens'] + $row['completion_tokens'],
                    $row['duration_seconds'], $row['estimated_cost']]);
                QueryUtils::sqlStatementThrowException('DELETE FROM module_treatment_planner_usage_queue WHERE event_key = ?', [$key]);
            }
            QueryUtils::sqlStatementThrowException('COMMIT');
            return true;
        } catch (\Throwable $e) {
            try { QueryUtils::sqlStatementThrowException('ROLLBACK'); } catch (\Throwable $ignored) {}
            try {
                QueryUtils::sqlStatementThrowException('UPDATE module_treatment_planner_usage_queue SET attempts = attempts + 1, last_attempt = NOW() WHERE event_key = ?', [$key]);
            } catch (\Throwable $ignored) {}
            error_log('Treatment Planner usage recording deferred; use the administrator usage retry page.');
            return false;
        }
    }

    public function pendingCount(): int
    {
        $row = QueryUtils::querySingleRow('SELECT COUNT(*) AS total FROM module_treatment_planner_usage_queue');
        return (int)($row['total'] ?? 0);
    }

    public function retryPending(int $limit = 25): array
    {
        $limit = min(100, max(1, $limit));
        $rows = QueryUtils::fetchRecords('SELECT event_key FROM module_treatment_planner_usage_queue ORDER BY usage_datetime, event_key LIMIT ' . $limit);
        $recorded = 0;
        foreach ($rows as $row) { if ($this->process($row['event_key'])) { $recorded++; } }
        return ['recorded' => $recorded, 'remaining' => $this->pendingCount()];
    }
}
