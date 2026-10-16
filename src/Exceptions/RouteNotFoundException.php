<?php

/**
 * Exception thrown when a route cannot be resolved.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2022 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\TreatmentPlanner\Exceptions;

/**
 * TODO: build a twig page for 404
 */
class RouteNotFoundException extends \Exception
{
    protected  $message = '404 Not Found';
}
