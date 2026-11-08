<?php
namespace OpenEMR\Modules\TreatmentPlanner\Services;

class GenerationMetadataService
{
    /** Persist provenance only; intentionally exclude options, prompts, notes and credentials. */
    public static function encode(array $metadata): ?string
    {
        $safe = [];
        foreach (['model', 'requested_model', 'prompt_version', 'generated_at'] as $key) {
            if (is_string($metadata[$key] ?? null) && $metadata[$key] !== '') { $safe[$key] = substr($metadata[$key], 0, 255); }
        }
        foreach (['prompt_tokens', 'completion_tokens', 'total_tokens'] as $key) {
            if (isset($metadata[$key]) && is_int($metadata[$key]) && $metadata[$key] >= 0) { $safe[$key] = $metadata[$key]; }
        }
        return $safe ? json_encode($safe, JSON_INVALID_UTF8_SUBSTITUTE) : null;
    }
}
