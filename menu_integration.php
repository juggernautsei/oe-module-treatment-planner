<?php

/**
 * Menu integration for the Treatment Planner module.
 *
 * Adds Treatment Planner entries to the OpenEMR main navigation.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2025 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

/**
 * Add Treatment Planner menu items to OpenEMR navigation
 *
 * @param array $menu_items Existing menu items
 * @return array Modified menu items with Treatment Planner entries
 */
function addTreatmentPlannerMenuItems($menu_items = [])
{
    $webroot = $GLOBALS['webroot'];
    $module_route = "/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php";

    // Add Treatment Planner section to Clinical menu
    $treatment_planner_menu = [
        'label' => xl('Treatment Planner'),
        'url' => $webroot . $module_route . '/dashboard',
        'requirement' => 0,
        'target' => 'main',
        'menu_id' => 'treatment_planner',
        'children' => [
            [
                'label' => xl('Dashboard'),
                'url' => $webroot . $module_route . '/dashboard',
                'requirement' => 0,
                'target' => 'main',
                'menu_id' => 'treatment_planner_dashboard'
            ],
            [
                'label' => xl('Create Treatment Plan'),
                'url' => $webroot . $module_route . '/treatment/create',
                'requirement' => 0,
                'target' => 'main',
                'menu_id' => 'treatment_planner_create'
            ],
            [
                'label' => xl('View Treatment Plans'),
                'url' => $webroot . $module_route . '/treatment/list',
                'requirement' => 0,
                'target' => 'main',
                'menu_id' => 'treatment_planner_list'
            ]
        ]
    ];

    // Add to Clinical menu if it exists, otherwise create new section
    if (isset($menu_items['Clinical'])) {
        $menu_items['Clinical']['children'][] = $treatment_planner_menu;
    } else {
        $menu_items['Treatment Planner'] = $treatment_planner_menu;
    }

    return $menu_items;
}

/**
 * Add Treatment Planner quick access to patient encounter
 *
 * @param int $pid Patient ID
 * @param int $encounter Encounter ID
 * @return string HTML for quick access link
 */
function getTreatmentPlannerQuickAccess($pid = null, $encounter = null)
{
    if (!$pid) {
        $pid = $_SESSION['pid'] ?? null;
    }

    if (!$encounter) {
        $encounter = $_SESSION['encounter'] ?? null;
    }

    if (!$pid || !$encounter) {
        return '';
    }

    $webroot = $GLOBALS['webroot'];
    $module_route = "/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php";
    $create_url = $webroot . $module_route . '/treatment/create?pid=' . urlencode($pid) . '&encounter=' . urlencode($encounter);
    $list_url = $webroot . $module_route . '/treatment/list?pid=' . urlencode($pid);

    $html = '<div class="treatment-planner-quick-access" style="margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9;">';
    $html .= '<h4 style="margin: 0 0 10px 0; color: #333;">' . xlt('AI Treatment Planner') . '</h4>';
    $html .= '<div class="btn-group" role="group">';
    $html .= '<a href="' . attr($create_url) . '" class="btn btn-primary btn-sm" target="main">';
    $html .= '<i class="fa fa-plus"></i> ' . xlt('Create AI Treatment Plan') . '</a>';
    $html .= '<a href="' . attr($list_url) . '" class="btn btn-secondary btn-sm" target="main" style="margin-left: 5px;">';
    $html .= '<i class="fa fa-list"></i> ' . xlt('View Plans') . '</a>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

/**
 * Check if user has permission to access Treatment Planner
 *
 * @return bool True if user has access
 */
function hasTreatmentPlannerAccess()
{
    // Check if user is logged in
    if (!isset($_SESSION['authUser']) || empty($_SESSION['authUser'])) {
        return false;
    }

    // Check for specific ACL permissions if needed
    // For now, allow all authenticated users
    // In production, you might want to check specific ACLs:
    // return acl_check('patients', 'med');

    return true;
}

/**
 * Get Treatment Planner navigation breadcrumbs
 *
 * @param string $current_page Current page identifier
 * @param array $params Additional parameters
 * @return array Breadcrumb array
 */
function getTreatmentPlannerBreadcrumbs($current_page = '', $params = [])
{
    $webroot = $GLOBALS['webroot'];
    $module_route = "/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php";

    $breadcrumbs = [
        [
            'label' => xl('Home'),
            'url' => $webroot . '/interface/main/main_screen.php',
            'active' => false
        ],
        [
            'label' => xl('Treatment Planner'),
            'url' => $webroot . $module_route . '/dashboard',
            'active' => false
        ]
    ];

    switch ($current_page) {
        case 'dashboard':
            $breadcrumbs[1]['active'] = true;
            break;

        case 'create':
            $breadcrumbs[] = [
                'label' => xl('Create Treatment Plan'),
                'url' => '',
                'active' => true
            ];
            break;

        case 'list':
            $breadcrumbs[] = [
                'label' => xl('Treatment Plans'),
                'url' => '',
                'active' => true
            ];
            break;

        case 'view':
            $breadcrumbs[] = [
                'label' => xl('Treatment Plans'),
                'url' => $webroot . $module_route . '/treatment/list',
                'active' => false
            ];
            $breadcrumbs[] = [
                'label' => xl('View Plan'),
                'url' => '',
                'active' => true
            ];
            break;

        case 'edit':
            $breadcrumbs[] = [
                'label' => xl('Treatment Plans'),
                'url' => $webroot . $module_route . '/treatment/list',
                'active' => false
            ];
            $breadcrumbs[] = [
                'label' => xl('Edit Plan'),
                'url' => '',
                'active' => true
            ];
            break;
    }

    return $breadcrumbs;
}

/**
 * Render breadcrumb navigation HTML
 *
 * @param array $breadcrumbs Breadcrumb array
 * @return string HTML for breadcrumbs
 */
function renderTreatmentPlannerBreadcrumbs($breadcrumbs)
{
    if (empty($breadcrumbs)) {
        return '';
    }

    $html = '<nav aria-label="breadcrumb" style="margin-bottom: 20px;">';
    $html .= '<ol class="breadcrumb">';

    foreach ($breadcrumbs as $crumb) {
        if ($crumb['active']) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . text($crumb['label']) . '</li>';
        } else {
            if (!empty($crumb['url'])) {
                $html .= '<li class="breadcrumb-item"><a href="' . attr($crumb['url']) . '">' . text($crumb['label']) . '</a></li>';
            } else {
                $html .= '<li class="breadcrumb-item">' . text($crumb['label']) . '</li>';
            }
        }
    }

    $html .= '</ol>';
    $html .= '</nav>';

    return $html;
}
