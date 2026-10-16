<?php

/**
 * Encounter integration hooks for the Treatment Planner module.
 *
 * Provides integration for OpenEMR encounter forms to display
 * Treatment Planner quick access and related information.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2025 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once __DIR__ . "/menu_integration.php";

/**
 * Display Treatment Planner integration in encounter form
 *
 * @param int $pid Patient ID
 * @param int $encounter Encounter ID
 * @return string HTML content for encounter integration
 */
function displayTreatmentPlannerEncounterIntegration($pid, $encounter)
{
    if (!hasTreatmentPlannerAccess()) {
        return '';
    }

    $html = getTreatmentPlannerQuickAccess($pid, $encounter);

    // Add existing treatment plans summary
    $existing_plans = getTreatmentPlansSummary($pid);
    if (!empty($existing_plans)) {
        $html .= getExistingPlansWidget($existing_plans, $pid);
    }

    return $html;
}

/**
 * Get summary of existing treatment plans for a patient
 *
 * @param int $pid Patient ID
 * @return array Array of treatment plan summaries
 */
function getTreatmentPlansSummary($pid)
{
    if (!$pid) {
        return [];
    }

    try {
        // Query the treatment_plans table for this patient
        $sql = "SELECT id, title, status, created_date, updated_date
                FROM treatment_plans
                WHERE pid = ?
                ORDER BY created_date DESC
                LIMIT 5";

        $result = sqlStatement($sql, [$pid]);
        $plans = [];

        while ($row = sqlFetchArray($result)) {
            $plans[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'status' => $row['status'],
                'created_date' => $row['created_date'],
                'updated_date' => $row['updated_date']
            ];
        }

        return $plans;
    } catch (Exception $e) {
        error_log("Treatment Planner: Error fetching plans summary - " . $e->getMessage());
        return [];
    }
}

/**
 * Generate widget showing existing treatment plans
 *
 * @param array $plans Array of treatment plans
 * @param int $pid Patient ID
 * @return string HTML widget
 */
function getExistingPlansWidget($plans, $pid)
{
    if (empty($plans)) {
        return '';
    }

    $webroot = $GLOBALS['webroot'];
    $module_route = "/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php";

    $html = '<div class="treatment-plans-summary" style="margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 5px; background-color: #f0f8ff;">';
    $html .= '<h5 style="margin: 0 0 10px 0; color: #333;">' . xlt('Recent Treatment Plans') . '</h5>';

    $html .= '<div class="plans-list" style="max-height: 200px; overflow-y: auto;">';
    foreach ($plans as $plan) {
        $view_url = $webroot . $module_route . '/treatment/view?id=' . urlencode($plan['id']);
        $edit_url = $webroot . $module_route . '/treatment/edit?id=' . urlencode($plan['id']);

        $status_class = '';
        $status_text = ucfirst($plan['status']);
        switch ($plan['status']) {
            case 'active':
                $status_class = 'badge-success';
                break;
            case 'completed':
                $status_class = 'badge-primary';
                break;
            case 'on_hold':
                $status_class = 'badge-warning';
                break;
            case 'cancelled':
                $status_class = 'badge-danger';
                break;
            default:
                $status_class = 'badge-secondary';
        }

        $html .= '<div class="plan-item" style="margin-bottom: 8px; padding: 8px; border: 1px solid #e0e0e0; border-radius: 3px; background-color: white;">';
        $html .= '<div style="display: flex; justify-content: space-between; align-items: center;">';
        $html .= '<div>';
        $html .= '<strong>' . text($plan['title']) . '</strong>';
        $html .= ' <span class="badge ' . $status_class . '" style="font-size: 0.8em;">' . text($status_text) . '</span>';
        $html .= '<br><small class="text-muted">' . xlt('Created') . ': ' . text(date('M j, Y', strtotime($plan['created_date']))) . '</small>';
        $html .= '</div>';
        $html .= '<div class="btn-group btn-group-sm">';
        $html .= '<a href="' . attr($view_url) . '" class="btn btn-outline-primary btn-sm" target="main" title="' . xla('View Plan') . '">';
        $html .= '<i class="fa fa-eye"></i></a>';
        $html .= '<a href="' . attr($edit_url) . '" class="btn btn-outline-secondary btn-sm" target="main" title="' . xla('Edit Plan') . '">';
        $html .= '<i class="fa fa-edit"></i></a>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
    }
    $html .= '</div>';

    // Fix the URL construction to match the router configuration
    $list_url = $webroot . $module_route . '/treatment/list?pid=' . urlencode($pid);
    $html .= '<div style="margin-top: 10px; text-align: center;">';
    $html .= '<a href="' . attr($list_url) . '" class="btn btn-outline-info btn-sm" target="main">';
    $html .= '<i class="fa fa-list"></i> ' . xlt('View Patient Plans') . '</a>';
    $html .= '</div>';

    $html .= '</div>';

    return $html;
}

/**
 * Get Treatment Planner status for patient dashboard
 *
 * @param int $pid Patient ID
 * @return array Status information
 */
function getTreatmentPlannerStatus($pid)
{
    if (!$pid) {
        return ['has_plans' => false, 'active_plans' => 0, 'total_plans' => 0];
    }

    try {
        $sql = "SELECT
                    COUNT(*) as total_plans,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_plans
                FROM treatment_plans
                WHERE pid = ?";

        $result = sqlQuery($sql, [$pid]);

        return [
            'has_plans' => $result['total_plans'] > 0,
            'active_plans' => (int)$result['active_plans'],
            'total_plans' => (int)$result['total_plans']
        ];
    } catch (Exception $e) {
        error_log("Treatment Planner: Error fetching status - " . $e->getMessage());
        return ['has_plans' => false, 'active_plans' => 0, 'total_plans' => 0];
    }
}

/**
 * Display Treatment Planner status badge for patient summary
 *
 * @param int $pid Patient ID
 * @return string HTML badge
 */
function getTreatmentPlannerStatusBadge($pid)
{
    $status = getTreatmentPlannerStatus($pid);

    if (!$status['has_plans']) {
        return '<span class="badge badge-light" title="' . xla('No treatment plans') . '">' .
               '<i class="fa fa-clipboard"></i> ' . xlt('No Plans') . '</span>';
    }

    if ($status['active_plans'] > 0) {
        return '<span class="badge badge-success" title="' . xla('Active treatment plans') . ': ' . $status['active_plans'] . '">' .
               '<i class="fa fa-clipboard-check"></i> ' . $status['active_plans'] . ' ' . xlt('Active') . '</span>';
    }

    return '<span class="badge badge-secondary" title="' . xla('Total treatment plans') . ': ' . $status['total_plans'] . '">' .
           '<i class="fa fa-clipboard"></i> ' . $status['total_plans'] . ' ' . xlt('Plans') . '</span>';
}

/**
 * Add Treatment Planner JavaScript for enhanced integration
 *
 * @return string JavaScript code
 */
function getTreatmentPlannerJavaScript()
{
    return "
    <script>
    // Treatment Planner Integration JavaScript

    // Function to refresh treatment plans widget
    function refreshTreatmentPlans(pid) {
        // This could be enhanced to use AJAX to refresh the widget
        console.log('Refreshing treatment plans for patient: ' + pid);
    }

    // Function to show treatment plan creation modal
    function showTreatmentPlanModal(pid, encounter) {
        var url = webroot_url + '/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php/treatment/create';
        if (pid) url += '?pid=' + encodeURIComponent(pid);
        if (encounter) url += '&encounter=' + encodeURIComponent(encounter);

        // Open in new window or modal depending on preference
        window.open(url, 'treatment_planner', 'width=1200,height=800,scrollbars=yes,resizable=yes');
    }

    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+Shift+T to open Treatment Planner
        if (e.ctrlKey && e.shiftKey && e.key === 'T') {
            e.preventDefault();
            var pid = document.querySelector('input[name=\"pid\"]')?.value ||
                     document.querySelector('[data-pid]')?.getAttribute('data-pid');
            if (pid) {
                showTreatmentPlanModal(pid);
            }
        }
    });
    </script>
    ";
}
