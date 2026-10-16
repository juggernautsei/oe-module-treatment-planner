<?php

/*
 *  package OpenEMR
 *  link    https://www.open-emr.org
 *  author  Sherwin Gaddis <sherwingaddis@gmail.com>
 *  Copyright (c) 2022.
 *  All Rights Reserved
 */

namespace OpenEMR\Modules\TreatmentPlanner\Exceptions;

/**
 * TODO: build a twig page for 404
 */
class RouteNotFoundException extends \Exception
{
    protected  $message = '404 Not Found';
}
