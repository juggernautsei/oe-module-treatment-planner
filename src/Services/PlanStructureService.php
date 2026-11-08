<?php
namespace OpenEMR\Modules\TreatmentPlanner\Services;

use OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException;

class PlanStructureService
{
    private PlanSectionParser $parser;
    public function __construct(PlanSectionParser $parser) { $this->parser = $parser; }
    public function missingSections(string $content): array
    {
        $sections = $this->parser->extract($content);
        $labels = ['diagnosis' => 'Diagnosis', 'goals' => 'Treatment goals', 'objectives' => 'Objectives',
            'interventions' => 'Interventions', 'timeline' => 'Timeline', 'progress' => 'Criteria for success'];
        $missing = [];
        foreach ($labels as $key => $label) { if (trim($sections[$key] ?? '') === '') { $missing[] = $label; } }
        return $missing;
    }
    public function assertComplete(string $content): void
    {
        $missing = $this->missingSections($content);
        if ($missing) { throw new UserFacingException('Before approval or finalization, complete these sections: ' . implode(', ', $missing) . '.'); }
    }
}
