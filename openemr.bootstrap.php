<?php

/*
 * package   OpenEMR
 * link      http://www.open-emr.org
 * author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * Copyright (c) 2023.
 * All rights reserved
 */

namespace OpenEMR\Modules\TreatmentPlanner;

/**
 * @global OpenEMR\Core\ModulesClassLoader $classLoader
 */
$classLoader->registerNamespaceIfNotExists('OpenEMR\\Modules\\TreatmentPlanner\\', __DIR__ . DIRECTORY_SEPARATOR . 'src');

/**
 * @global EventDispatcher $eventDispatcher Injected by the OpenEMR module loader;
 */
$bootstrap = new Bootstrap($eventDispatcher);
$bootstrap->subscribeToEvents();
