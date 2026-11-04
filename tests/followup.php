<?php
namespace OpenEMR\Core {
    abstract class AbstractModuleActionListener {
        public static array $states = [];
        public function __construct() {}
        public static function setModuleActiveState($id, $active, $ui) { self::$states[] = [$id, $active, $ui]; }
    }
}
namespace {
    require __DIR__ . '/regression.php';
    use OpenEMR\Common\Database\QueryUtils;
    use OpenEMR\Modules\TreatmentPlanner\Services\AssessmentService;
    use OpenEMR\Modules\TreatmentPlanner\Services\SchemaService;
    use OpenEMR\Modules\TreatmentPlanner\Services\UsageService;
    $before = $count;
    resetState();
    $assessment = new AssessmentService();
    $lookup = new ReflectionMethod($assessment, 'getListValue');
    if (PHP_VERSION_ID < 80100) { $lookup->setAccessible(true); }
    $GLOBALS['testLists'] = ['mood' => ['0' => ['title' => 'No symptoms'], '1' => ['title' => 'Depressed']], 'risk' => ['1' => ['title' => 'High']]];
    check($lookup->invoke($assessment, '0', 'mood') === 'No symptoms', 'Zero must be a valid option');
    check($lookup->invoke($assessment, '1', 'mood') === 'Depressed', 'List labels must resolve');
    check($lookup->invoke($assessment, '1', 'risk') === 'High', 'Identical codes in different lists must not collide');
    check($lookup->invoke($assessment, '0|1', 'mood') === 'No symptoms; Depressed', 'Multiple selections must resolve');
    check(strpos($lookup->invoke($assessment, 'missing', 'mood'), 'Unrecognized option') === 0, 'Unknown codes must be explicit');
    check($lookup->invoke($assessment, 'Free text', '') === 'Free text', 'Free text must stay unchanged');
    $sections = $ai->extractSections("1. DIAGNOSIS\nDepression\nAnother sentence\n2. TREATMENT GOALS\n1. Improve sleep\n2. Improve functioning\n3. OBJECTIVES\nTrack sleep\n4. INTERVENTIONS\nTherapy\n5. TIMELINE\nEight weeks\n6. CRITERIA FOR SUCCESS\nImproved functioning");
    check(count($sections) === 6, 'All six requested components must parse');
    check(strpos($sections['goals'], '2. Improve functioning') !== false, 'Numbered goal items must not end a section');
    check($sections['diagnosis'] === "Depression\nAnother sentence", 'Sentences must not end a section');
    check($sections['progress'] === 'Improved functioning', 'Success criteria must map to progress');
    $html = $ai->extractSections('<h2>Treatment Goals</h2><p>First &amp; second</p><h2>Timeline</h2><p>Four weeks</p>');
    check($html['goals'] === 'First & second' && $html['timeline'] === 'Four weeks', 'HTML headings and entities must parse');
    $markdown = $ai->extractSections("## **Diagnosis**\nExample\n## **Treatment Goals**\nImprove");
    check($markdown['diagnosis'] === 'Example', 'Markdown headings must parse');
    $promptHeadings = $ai->extractSections("**1. DIAGNOSIS (if known):**\nExample\n**2. OBJECTIVES (measurable and time-bound):**\nMeasure sleep");
    check($promptHeadings['diagnosis'] === 'Example' && $promptHeadings['objectives'] === 'Measure sleep', 'Prompt-style headings must parse');
    $usage = new UsageService(['example-model' => ['prompt' => 2, 'completion' => 4]]);
    $cost = new ReflectionMethod($usage, 'calculateCost');
    if (PHP_VERSION_ID < 80100) { $cost->setAccessible(true); }
    check($cost->invoke($usage, 'example-model', 1000000, 1000000) === 6.0, 'Configured per-million rates must be used');
    check($cost->invoke($usage, 'unknown', 1000, 1000) === null, 'Unknown model must not inherit another price');
    $bad = new UsageService(['bad' => ['prompt' => -1, 'completion' => 1]]);
    check($cost->invoke($bad, 'bad', 1000, 1000) === null, 'Invalid prices must be unavailable');
    $GLOBALS['testTableCount'] = 4;
    rejects(fn() => SchemaService::assertReady(), 'upgrade required');
    unset($GLOBALS['testTableCount']);
    require dirname(__DIR__) . '/ModuleManagerListener.php';
    $listener = new \ModuleManagerListener();
    check($listener->moduleManagerAction('install', 9) === 'Success', 'Install must work without Weno');
    check(\OpenEMR\Core\AbstractModuleActionListener::$states[0] === [9, '0', '1'], 'Only this module state must change');
    check($listener->moduleManagerAction('upgrade_sql', 9) === 'Success', 'Upgrade must apply schema');
    check($listener->moduleManagerAction('unregister', 9) === 'Success', 'Unregister must preserve records');
    check($listener->moduleManagerAction('unknown', 9) === 'Success', 'Unknown lifecycle actions must be ignored');
    // History queries must be patient-scoped before revision data is requested.
    resetState();
    QueryUtils::$singleHandler = function ($sql, $params) {
        if (strpos($sql, 'COUNT(*)') !== false) { return ['total' => 30]; }
        return QueryUtils::$plan;
    };
    QueryUtils::$fetchHandler = function ($sql, $params) {
        check($params === [1] && strpos($sql, 'OFFSET 25') !== false, 'History must paginate the authorized plan');
        return [];
    };
    $history = $service->getRevisionHistory(1, 2);
    check($history['page'] === 2 && $history['pages'] === 2 && $history['total'] === 30, 'History pagination must reflect stored snapshots');
    $_SESSION['pid'] = 11;
    rejects(fn() => $service->getRevisionHistory(1), 'Access denied');
    QueryUtils::$singleHandler = null;
    QueryUtils::$fetchHandler = null;
    echo ($count - $before) . " follow-up checks passed\n";
}
