<?php
namespace OpenEMR\Modules\TreatmentPlanner\Services;

/** Text-level review flags only; never labels an AI claim clinically correct. */
class SourceReviewService
{
    public static function warnings(string $content, ?array $snapshot): array
    {
        if (!$snapshot) { return ['Original input evidence is unavailable for this older plan. Check the chart directly.']; }
        $warnings = [];
        preg_match_all('/\[(?:S|P|D|O)\d+\]/', $content, $matches);
        $references = array_unique($matches[0]);
        $known = array_map(function ($source) { return '[' . $source['reference'] . ']'; }, $snapshot['sources'] ?? []);
        if (!$references) { $warnings[] = 'No source references were found in this plan. Compare every clinical assertion with the recorded inputs.'; }
        $unknown = array_diff($references, $known);
        if ($unknown) { $warnings[] = 'Unknown source references require review: ' . implode(', ', $unknown) . '.'; }
        // These are code-shaped strings, not a diagnosis/coding validation algorithm.
        preg_match_all('/\b(?:[A-Z][0-9]{2}(?:\.[A-Z0-9]{1,4})?|[0-9]{3}\.[0-9]{1,3})\b/', $content, $codes);
        $sourceText = implode("\n", array_column($snapshot['sources'] ?? [], 'value'));
        $unmatched = array_filter(array_unique($codes[0]), function ($code) use ($sourceText) { return !preg_match('/(?<![A-Za-z0-9.])' . preg_quote($code, '/') . '(?![A-Za-z0-9.])/', $sourceText); });
        if ($unmatched) { $warnings[] = 'Code-like values not found verbatim in recorded inputs: ' . implode(', ', $unmatched) . '. Verify them against the chart and coding references.'; }
        $warnings[] = 'Source matches are text-level checks, not proof of clinical correctness. Suggestions and diagnostic claims still require clinician verification.';
        return $warnings;
    }
}
