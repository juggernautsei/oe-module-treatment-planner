<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$twig = new Twig\Environment(new Twig\Loader\FilesystemLoader(dirname(__DIR__) . '/templates'), ['autoescape' => 'html', 'strict_variables' => true]);
foreach (['xlt', 'text', 'attr', 'js_escape'] as $name) { $twig->addFilter(new Twig\TwigFilter($name, fn($text) => $text)); }
$twig->addFilter(new Twig\TwigFilter('dateToTime', fn($text) => strtotime($text)));
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
$plan += ['patient_id' => 10, 'sex' => 'unspecified', 'age' => 0, 'date_created' => '2026-01-01', 'provider_fname' => 'Test', 'provider_lname' => 'Clinician'];
$plan['version'] = 5;
$editContext = ['plan' => $plan, 'error' => 'Save conflict', 'message' => '', 'csrf_token_form' => 'valid', 'editor_content' => '<script>unsaved notes</script>', 'form_version' => 3, 'selected_status' => 'draft', 'read_only' => false, 'statuses' => ['draft' => 'Draft', 'finalized' => 'Finalized'], 'webroot' => '', 'module_name' => 'oe-module-treatment-planner'];
$edit = $twig->render('treatment/edit.html.twig', $editContext);
if (strpos($edit, 'name="expected_version" value="3"') === false || strpos($edit, '&lt;script&gt;unsaved notes&lt;/script&gt;') === false || strpos($edit, '<script>unsaved notes') !== false) { throw new RuntimeException('Edit recovery must preserve escaped text and original version'); }
if (strpos($edit, '0 years') === false) { throw new RuntimeException('Zero age must display without being treated as missing'); }
$editContext['plan']['status'] = 'finalized'; $editContext['read_only'] = true;
$readOnly = $twig->render('treatment/edit.html.twig', $editContext);
if (strpos($readOnly, 'readonly') === false || strpos($readOnly, 'name="submit_edit"') !== false) { throw new RuntimeException('Finalized recovery must not expose a save action'); }
$viewPlan = $plan; $viewPlan['status'] = 'finalized'; $viewPlan['encounter_id'] = 20;
$viewPlan['generation_metadata'] = ['model' => '<script>metadata payload</script>', 'prompt_version' => 'v1', 'total_tokens' => 15];
$viewContext = ['plan' => $viewPlan, 'error' => '', 'statuses' => ['finalized' => 'Finalized'], 'csrf_token_form' => 'valid', 'missing_sections' => ['Objectives'], 'webroot' => '', 'module_name' => 'oe-module-treatment-planner'];
$view = $twig->render('treatment/view.html.twig', $viewContext);
if (strpos($view, '&lt;script&gt;metadata payload&lt;/script&gt;') === false || strpos($view, '<script>metadata payload') !== false) { throw new RuntimeException('Generation metadata must be escaped'); }
if (strpos($view, 'Expected sections not detected') === false || strpos($view, 'This finalized record remains read-only.') === false) { throw new RuntimeException('Existing finalized records must remain read-only while showing structure warnings'); }
$viewContext['plan']['generation_metadata'] = null;
$legacyView = $twig->render('treatment/view.html.twig', $viewContext);
if (strpos($legacyView, 'Generation metadata was not recorded for this plan.') === false) { throw new RuntimeException('Legacy missing metadata must be explicit'); }
echo "13 template integration checks passed\n";
