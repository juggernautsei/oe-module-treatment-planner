<?php
namespace OpenEMR\Modules\TreatmentPlanner\Services;

use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Services\PatientService;
use OpenEMR\Services\EncounterService;

/** Default wiring for OpenEMR's zero-argument router; tests can supply dependencies directly. */
class ModuleServiceFactory
{
    public static function config(): ConfigService { return new ConfigService(); }
    public static function sectionParser(): PlanSectionParser { return new PlanSectionParser(); }
    public static function structure(): PlanStructureService { return new PlanStructureService(self::sectionParser()); }
    public static function assessment(): AssessmentService { return new AssessmentService(); }
    public static function requests(): GenerationRequestService { return new GenerationRequestService(); }
    public static function openAI(ConfigService $config): OpenAIService
    {
        return new OpenAIService($config, new UsageService(), new PromptGenerationService(), self::sectionParser());
    }
    public static function treatment(OpenAIService $ai): TreatmentService
    {
        return new TreatmentService($ai, new PatientService(), new EncounterService(), self::assessment(), self::requests(), self::structure());
    }
    public static function twig(): TwigContainer
    {
        return new TwigContainer(dirname(__DIR__, 2) . '/templates', $GLOBALS['kernel']);
    }
}
