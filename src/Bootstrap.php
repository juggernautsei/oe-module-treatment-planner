<?php

/*
 * package   OpenEMR
 * link      http://www.open-emr.org
 * author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * Copyright (c) 2023.
 * All rights reserved
 */

namespace OpenEMR\Modules\TreatmentPlanner;

use OpenEMR\Core\Kernel;
use OpenEMR\Events\Encounter\EncounterMenuEvent;
use OpenEMR\Events\Encounter\LoadEncounterFormFilterEvent;
use OpenEMR\Menu\MenuEvent;
use OpenEMR\Modules\TreatmentPlanner\Menu\MenuInjectionItems;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

class Bootstrap
{
    /**
     * @var EventDispatcherInterface The object responsible for sending and subscribing to events through the OpenEMR system
     */
    private $eventDispatcher;
    private $twig;

    public function __construct(EventDispatcher $dispatcher, ?Kernel $kernel = null)
    {
        if (empty($kernel)) {
            $kernel = new Kernel();
        }
        $this->eventDispatcher = $dispatcher;
    }

    /**
     * @return \Twig\Environment
     */
    public function getTwig(): Environment
    {
        return $this->twig;
    }

    public function subscribeToEvents(): void
    {
        /**
         * @var EventDispatcherInterface $eventDispatcher
         * @var array $module
         * @global                       $eventDispatcher @see ModulesApplication::loadCustomModule
         * @global                       $module @see ModulesApplication::loadCustomModule
         */
        $this->eventDispatcher->addListener(EncounterMenuEvent::MENU_RENDER, [$this, 'addTreatmentPlanner']);
        $this->eventDispatcher->addListener(LoadEncounterFormFilterEvent::EVENT_NAME, [$this, 'filterEncounterForm']);
    }

    public function registerMenuItems(MenuEvent $event): void
    {
        $item = new MenuInjectionItems();
        $item->event = $event;
        $item->TreatmentPlannerMenuItem();
    }

    public function addTreatmentPlanner(EncounterMenuEvent $event): EncounterMenuEvent
    {
        $menu = $event->getMenuData();
        $menu['Treatment Planner'] = [
            'children' => [
                [
                    'state' => 1,
                    'directory' => 'oe-module-treatment-planner',
                    'id' => 41,
                    'unpackaged' => 1,
                    'date' => '2023-03-01 00:00:00',
                    'priority' => 13,
                    'aco_spec' => 'encounters|coding',
                    'LBF' => '',
                    'displayText' => 'Dashboard',
                    'href' => $GLOBALS['webroot'] . '/interface/modules/custom_modules/oe-module-treatment-planner/public/new.php'
                ]
            ],
        ];
        $event->setMenuData($menu);
        return $event;
    }

    /**
     * Event listener for LoadEncounterFormFilterEvent
     * This allows the treatment planner module to provide its own report.php path
     *
     * @param LoadEncounterFormFilterEvent $event
     * @return LoadEncounterFormFilterEvent
     */
    public function filterEncounterForm(LoadEncounterFormFilterEvent $event): LoadEncounterFormFilterEvent
    {
        error_log("Entering filterEncounterForm event listener");
        $formName = $event->getFormName();
        $pageName = $event->getPageName();
        
        // Debug logging
        error_log("Treatment Planner Event Listener - Form: $formName, Page: $pageName");
        
        // Check if this is a treatment planner form requesting a report
        if ($formName === 'treatment_planner' && $pageName === 'report.php') {
            // Set the path to our module's report.php file
            $modulePath = dirname(__DIR__) . '/';
            error_log("Treatment Planner - Setting module path: $modulePath");
            error_log("Treatment Planner - Realpath of module path: " . realpath($modulePath));
            error_log("Treatment Planner - File exists check: " . (file_exists($modulePath) ? 'true' : 'false'));
            
            try {
                $event->setDir($modulePath);
                error_log("Treatment Planner - setDir() successful");
            } catch (Exception $e) {
                error_log("Treatment Planner - setDir() failed: " . $e->getMessage());
            }
        }
        
        error_log("Exiting filterEncounterForm event listener");
        return $event;
    }
}
