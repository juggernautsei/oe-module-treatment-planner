<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$twig = new Twig\Environment(new Twig\Loader\FilesystemLoader(dirname(__DIR__) . '/templates'), ['autoescape' => 'html', 'strict_variables' => true]);
foreach (['xlt', 'text', 'attr', 'js_escape'] as $name) { $twig->addFilter(new Twig\TwigFilter($name, fn($text) => $text)); }
$twig->addFunction(new Twig\TwigFunction('setupHeader', fn($assets) => ''));
$snapshot = ['version' => 1, 'status' => 'draft', 'content' => '<script>alert("bad")</script>', 'date_updated' => '2026-01-01', 'date_archived' => '2026-01-02', 'changed_by' => 7, 'archived_by_name' => 'Test Clinician'];
$plan = $snapshot + ['id' => 1, 'fname' => 'Synthetic', 'lname' => 'Patient'];
$plan['version'] = 2;
$html = $twig->render('treatment/history.html.twig', ['plan' => $plan, 'left' => $snapshot, 'right' => $plan, 'revisions' => [$snapshot], 'page' => 1, 'pages' => 2, 'total' => 30, 'webroot' => '', 'module_name' => 'oe-module-treatment-planner']);
if (strpos($html, '<script>alert') !== false || strpos($html, '&lt;script&gt;') === false || strpos($html, 'page=2') === false || strpos($html, 'Compare with current') === false) { throw new RuntimeException('History rendering, escaping, or navigation failed'); }
$empty = $twig->render('treatment/history.html.twig', ['plan' => $plan, 'left' => $plan, 'right' => $plan, 'revisions' => [], 'page' => 1, 'pages' => 1, 'total' => 0, 'webroot' => '', 'module_name' => 'oe-module-treatment-planner']);
if (strpos($empty, 'No archived versions yet.') === false) { throw new RuntimeException('Empty history must render'); }
$create = $twig->render('treatment/create.html.twig', ['plan' => null, 'error' => 'Synthetic validation error', 'csrf_token_form' => 'token', 'request_key' => str_repeat('a', 64), 'pid' => 10, 'encounter' => 20, 'webroot' => '', 'module_name' => 'oe-module-treatment-planner', 'observations' => '<script>alert("bad")</script>']);
if (strpos($create, 'name="request_key" value="' . str_repeat('a', 64) . '"') === false) { throw new RuntimeException('Create form must include a request key'); }
if (strpos($create, '<script>alert') !== false || strpos($create, '&lt;script&gt;') === false) { throw new RuntimeException('Preserved notes must be escaped'); }
echo "7 template integration checks passed\n";
