<?php

/**
 * Public front controller for the Treatment Planner module.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2023 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

//prevent UI redressing
use OpenEMR\Modules\TreatmentPlanner\Controllers\Dashboard;
use OpenEMR\Modules\TreatmentPlanner\Controllers\TreatmentController;
use OpenEMR\Modules\TreatmentPlanner\Exceptions\RouteNotFoundException;
use OpenEMR\Modules\TreatmentPlanner\Router;
use OpenEMR\Common\Csrf\CsrfUtils;

// Enable authentication - remove ignoreAuth for production security
$ignoreAuth = false;
// Set $sessionAllowWrite to true to prevent session concurrency issues during authorization related code
$sessionAllowWrite = true;
require_once dirname(__DIR__, 4) . "/globals.php";

// Include menu integration functions
require_once dirname(__DIR__) . "/menu_integration.php";

// Check authentication and permissions
if (!hasTreatmentPlannerAccess()) {
    echo "<div class='alert alert-danger'>" . xlt('Access denied. Please log in to access the Treatment Planner.') . "</div>";
    exit;
}

try {
    \OpenEMR\Modules\TreatmentPlanner\Services\SchemaService::assertReady();
} catch (\Throwable $e) {
    http_response_code(503);
    echo '<div class="alert alert-warning">' . text(\OpenEMR\Modules\TreatmentPlanner\Services\ErrorService::publicMessage($e, 'module_route')) . '</div>';
    exit;
}

// Note: CSRF validation is handled by individual controllers to prevent double validation

$router = new Router();
$webroot = $GLOBALS['webroot'];
const MODULE_ROUTE = "/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php";

$router
    ->get($webroot . MODULE_ROUTE . "/dashboard", [Dashboard::class, 'indexAction'])
    ->get($webroot . MODULE_ROUTE . "/treatment/list", [TreatmentController::class, 'listAction'])
    ->get($webroot . MODULE_ROUTE . "/treatment/history", [TreatmentController::class, 'historyAction'])
    ->get($webroot . MODULE_ROUTE . "/treatment/view", [TreatmentController::class, 'viewAction'])
    ->get($webroot . MODULE_ROUTE . "/treatment/create", [TreatmentController::class, 'createAction'])
    ->post($webroot . MODULE_ROUTE . "/treatment/create", [TreatmentController::class, 'createAction'])
    ->post($webroot . MODULE_ROUTE . "/treatment/revise", [TreatmentController::class, 'reviseAction'])
    ->get($webroot . MODULE_ROUTE . "/treatment/edit", [TreatmentController::class, 'editAction'])
    ->post($webroot . MODULE_ROUTE . "/treatment/edit", [TreatmentController::class, 'editAction'])
    ->post($webroot . MODULE_ROUTE . "/treatment/finalize", [TreatmentController::class, 'finalizeAction'])
    ->post($webroot . MODULE_ROUTE . "/treatment/change-status", [TreatmentController::class, 'changeStatusAction']);

try {
    echo $router->resolve($_SERVER['REQUEST_URI'], strtolower($_SERVER['REQUEST_METHOD']));
} catch (RouteNotFoundException $e) {
    http_response_code(404);
    echo text(\OpenEMR\Modules\TreatmentPlanner\Services\ErrorService::publicMessage($e, 'module_route'));
} catch (\Throwable $e) {
    http_response_code(500);
    echo text(\OpenEMR\Modules\TreatmentPlanner\Services\ErrorService::publicMessage($e, 'module_route'));
}
