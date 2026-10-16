<?php

/*
 * package   OpenEMR
 * link      http://www.open-emr.org
 * author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * All rights reserved
 * Copyright (c) 2025.
 */

namespace OpenEMR\Modules\TreatmentPlanner\Menu;

use OpenEMR\Menu\MenuEvent;
use stdClass;

class MenuInjectionItems
{
    public MenuEvent $event;

    public function TreatmentPlannerMenuItem(): MenuEvent
    {
        $menu = $this->event->getMenu();
        $menuItem = new stdClass();
        $menuItem->requirement = 0;
        $menuItem->target = 'tmp';
        $menuItem->menu_id = 'tmp0';
        $menuItem->label = xlt("Treatment Planner");
        $menuItem->url = "/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php/dashboard";
        $menuItem->children = [];
        $menuItem->global_req = [];

        foreach ($menu as $item) {
            if ($item->menu_id == 'modimg') {
                $item->children[] = $menuItem;
                break;
            }
        }
        $this->event->setMenu($menu);
        return $this->event;
    }
}
