<?php

/*
 *  package OpenEMR
 *  link    https://www.open-emr.org
 *  author  Sherwin Gaddis <sherwingaddis@gmail.com>
 *  Copyright (c) 2022.
 *  All Rights reserved
 */

namespace OpenEMR\Modules\TreatmentPlanner\Exceptions;

class NumberNotFoundException extends \Exception
{
    protected $message = "Patient Cell Phone Number Not Found";
}
