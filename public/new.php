<?php

/**
 * Public entrypoint for creating a new treatment plan.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2025 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

$ignoreAuth = false;
$sessionAllowWrite = true;
require_once dirname(__DIR__, 4) . '/globals.php';
// The destination applies the shared authentication, ACL and schema checks.
header('Location: ' . $GLOBALS['webroot'] . '/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php/dashboard');
exit;
