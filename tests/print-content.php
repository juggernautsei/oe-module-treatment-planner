<?php
require dirname(__DIR__) . '/src/Services/PlanContentRenderer.php';
$r = new \OpenEMR\Modules\TreatmentPlanner\Services\PlanContentRenderer();
$cases = [
    ["### Plan\n\n#### 1. Diagnosis\n**Review required**", ['<h2>Plan</h2>', '<h3>1. Diagnosis</h3>', '<strong>Review required</strong>']],
    ["- Parent\n  - Child\n- Next", ['<ul><li>Parent<ul><li>Child</li></ul></li><li>Next</li></ul>']],
    ["3. Three\n4. Four", ['<ol start="3"><li>Three</li><li>Four</li></ol>']],
    ["First\nsecond\n\nNew paragraph", ['<p>First<br>second</p><p>New paragraph</p>']],
    ["---\n*Emphasis*", ['<hr>', '<em>Emphasis</em>']],
    ['<script>alert(1)</script> **<img src=x onerror=alert(1)>**', ['&lt;script&gt;', '<strong>&lt;img']],
    ['[unsafe](javascript:alert(1))', ['[unsafe](javascript:alert(1))']],
];
foreach ($cases as [$source, $expected]) {
    $before = $source;
    $html = $r->render($source);
    foreach ($expected as $fragment) { if (strpos($html, $fragment) === false) { throw new RuntimeException('Missing expected formatting'); } }
    if ($source !== $before || preg_match('/<(?:script|img|a)\b/i', $html)) { throw new RuntimeException('Unsafe markup or modified source'); }
}
echo count($cases) . " print rendering checks passed\n";
