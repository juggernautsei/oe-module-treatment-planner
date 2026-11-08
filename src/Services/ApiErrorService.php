<?php
namespace OpenEMR\Modules\TreatmentPlanner\Services;

use OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException;

class ApiErrorService
{
    /** Never expose arbitrary upstream messages, which can echo request data. */
    public static function fromResponse(int $status, string $body): \RuntimeException
    {
        $data = json_decode($body, true);
        $code = is_array($data) ? ($data['error']['code'] ?? null) : null;
        if ($status === 404 && $code === 'model_not_found') {
            return new UserFacingException('The configured OpenAI model is unavailable to this API key. Ask an administrator to select an accessible model in Treatment Planner settings.', $status);
        }
        if ($status === 401) {
            return new UserFacingException('OpenAI rejected the configured API key. Ask an administrator to check the key in Treatment Planner settings.', $status);
        }
        if ($status === 429 && $code === 'insufficient_quota') {
            return new UserFacingException('The OpenAI project has insufficient quota. Check API billing and project limits before submitting a new request.', $status);
        }
        if ($status === 429) {
            return new UserFacingException('OpenAI rate limit reached. Wait before submitting a new request.', $status);
        }
        return new \RuntimeException('OpenAI request failed.', $status);
    }
}
