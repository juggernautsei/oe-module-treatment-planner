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

require_once(__DIR__ . "/../../../../globals.php");

use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\Header;

// Set up Twig with the module's template directory
$twigContainer = new TwigContainer(__DIR__ . '/../templates', $GLOBALS['kernel']);
$twig = $twigContainer->getTwig();

// Add the setupHeader function to Twig globals so templates can use it
$twig->addGlobal('setupHeader', function($assets = []) {
    return Header::setupHeader($assets);
});

// Prepare template variables
$templateVars = [
    'pid' => $pid ?? null,
    'encounter' => $encounter ?? null,
    'patient_name' => $patient_name ?? 'Unknown Patient',
    'webroot' => $GLOBALS['webroot'] ?? '',
];

// Render the dashboard template
echo $twig->render('dashboard/home.twig', $templateVars);
