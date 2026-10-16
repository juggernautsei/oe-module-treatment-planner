<?php

/**
 * Treatment Planner report.php
 * Display treatment plans in the encounter summary page
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Treatment Planner Module
 * @copyright Copyright (c) 2025
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once(dirname(__FILE__, 5) . '/library/api.inc.php');

function treatment_planner_report($pid, $encounter, $cols, $id)
{
    // Query the treatment planner table
    $query = "SELECT tp.*,
                     CONCAT(p.fname, ' ', p.lname) as patient_name,
                     CONCAT(u.fname, ' ', u.lname) as provider_name
              FROM module_treatment_planner_plans tp
              LEFT JOIN patient_data p ON tp.patient_id = p.pid
              LEFT JOIN users u ON tp.provider_id = u.id
              WHERE tp.patient_id = ?
              AND tp.encounter_id = ?
              AND tp.status IN ('approved', 'finalized')
              ORDER BY tp.date_created DESC";

    $result = sqlStatementCdrEngine($query, [$pid, $encounter]);

    if (!$result) {
        print "<div class='text-muted'>" . xlt('No finalized treatment plans found for this encounter') . "</div>";
        return;
    }

    $plans_found = false;

    print "<div class='treatment-planner-report'>";
    print "<h4>" . xlt('Treatment Plans') . "</h4>";

    while ($plan = sqlFetchArray($result)) {
        $plans_found = true;

        // Status badge styling
        $status_class = '';
        $status_text = '';
        switch ($plan['status']) {
            case 'approved':
                $status_class = 'badge-success';
                $status_text = xl('Approved');
                break;
            case 'finalized':
                $status_class = 'badge-primary';
                $status_text = xl('Finalized');
                break;
            default:
                $status_class = 'badge-secondary';
                $status_text = xl(ucfirst($plan['status']));
        }

        print "<div class='card mb-3'>";
        print "<div class='card-header d-flex justify-content-between align-items-center'>";
        print "<h6 class='mb-0'>" . xlt('Treatment Plan') . " #" . text($plan['id']) . "</h6>";
        print "<span class='badge " . attr($status_class) . "'>" . text($status_text) . "</span>";
        print "</div>";

        print "<div class='card-body'>";

        // Plan metadata
        print "<div class='row mb-2'>";
        print "<div class='col-md-4'>";
        print "<strong>" . xlt('Created') . ":</strong> " . text(date('M j, Y g:i A', strtotime($plan['date_created'])));
        print "</div>";
        print "<div class='col-md-4'>";
        print "<strong>" . xlt('Provider') . ":</strong> " . text($plan['provider_name'] ?: 'Unknown');
        print "</div>";
        print "<div class='col-md-4'>";
        print "<strong>" . xlt('Version') . ":</strong> " . text($plan['version']);
        print "</div>";
        print "</div>";

        // Treatment plan content
        print "<div class='treatment-content'>";
        print "<strong>" . xlt('Treatment Plan') . ":</strong>";
        print "<div class='mt-2 p-3 bg-light border rounded'>";

        // Format the content nicely
        $content = $plan['content'];
        if (!empty($content)) {
            // Convert line breaks to HTML
            $formatted_content = nl2br(text($content));

            // Limit display length for summary view
            if (strlen($content) > 500) {
                $truncated = substr($content, 0, 500);
                $formatted_content = nl2br(text($truncated)) .
                    "<span class='text-muted'>... <em>" . xlt('(truncated)') . "</em></span>";
            }

            print $formatted_content;
        } else {
            print "<em class='text-muted'>" . xlt('No content available') . "</em>";
        }

        print "</div>";
        print "</div>";

        // Action links
        print "<div class='mt-3'>";
        print "<a href='" . attr($GLOBALS['webroot']) . "/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php/treatment/view?id=" . attr($plan['id']) . "' " .
              "class='btn btn-sm btn-outline-primary'>" . xlt('View Full Plan') . "</a>";

        if ($plan['status'] !== 'finalized') {
            print " <a href='" . attr($GLOBALS['webroot']) . "/interface/modules/custom_modules/oe-module-treatment-planner/public/index.php/treatment/edit?id=" . attr($plan['id']) . "' " .
                  "class='btn btn-sm btn-outline-secondary'>" . xlt('Edit Plan') . "</a>";
        }
        print "</div>";

        print "</div>"; // card-body
        print "</div>"; // card
    }

    if (!$plans_found) {
        print "<div class='alert alert-info'>" . xlt('No finalized treatment plans found for this encounter') . "</div>";
    }

    print "</div>"; // treatment-planner-report
}
