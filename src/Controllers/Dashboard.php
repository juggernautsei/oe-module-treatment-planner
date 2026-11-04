<?php

/**
 * Dashboard controller for the Treatment Planner module.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2025 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\TreatmentPlanner\Controllers;

use OpenEMR\Common\Twig\TwigContainer;
use Exception;
use OpenEMR\Modules\TreatmentPlanner\Services\AccessService;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class Dashboard
{
    private TwigContainer $twig;
    private $template;

    public function __construct()
    {
        $this->twig = new TwigContainer(dirname(__DIR__, 2) . '/templates', $GLOBALS['kernel']);
        $this->template = $this->twig->getTwig();
    }

    /**
     * Index page for the dashboard
     */
    public function indexAction()
    {
        // Get dashboard statistics
        $stats = $this->getDashboardStats();
        
        // Get recent treatment plans
        $recent_plans = $this->getRecentPlans();

        echo $this->template->render('dashboard/home.twig', [
            'title' => 'AI Treatment Planner Dashboard',
            'stats' => $stats,
            'recent_plans' => $recent_plans,
            'encounter' => $_SESSION['encounter'] ?? '',
            'webroot' => $GLOBALS['webroot']
        ]);
    }

    /**
     * Get dashboard statistics
     * 
     * @return array Statistics data
     */
    private function getDashboardStats()
    {
        try {
            $stats = ['total_plans' => 0, 'active_plans' => 0, 'ai_generated' => 0, 'recent_plans' => 0];
            $pid = (int)($_SESSION['pid'] ?? 0);
            if ($pid <= 0) { return $stats; }
            AccessService::assertPatient($pid);
            $result = sqlStatement('SELECT encounter_id, status, date_created FROM module_treatment_planner_plans WHERE patient_id = ?', [$pid]);
            while ($plan = sqlFetchArray($result)) {
                try {
                    AccessService::assertPatient($pid, false, (int)$plan['encounter_id']);
                } catch (\RuntimeException $e) { continue; }
                $stats['total_plans']++;
                $stats['ai_generated']++;
                if (in_array($plan['status'], ['approved', 'finalized'], true)) { $stats['active_plans']++; }
                if (strtotime($plan['date_created']) >= strtotime('-7 days')) { $stats['recent_plans']++; }
            }
            return $stats;
        } catch (Exception $e) {
            error_log("Dashboard: Error fetching stats - " . $e->getMessage());
            return [
                'total_plans' => 0,
                'active_plans' => 0,
                'ai_generated' => 0,
                'recent_plans' => 0
            ];
        }
    }

    /**
     * Get recent treatment plans for dashboard
     * 
     * @return array Recent plans data
     */
    private function getRecentPlans()
    {
        try {
            // Get current patient ID from session
            $pid = $_SESSION['pid'] ?? 0;
            
            // If no patient is selected, return empty array
            if (empty($pid)) {
                return [];
            }
            
            $sql = "SELECT 
                        tp.id,
                        tp.status,
                        tp.encounter_id,
                        tp.date_created,
                        tp.date_updated,
                        CONCAT(pd.fname, ' ', pd.lname) as patient_name,
                        tp.patient_id as pid
                    FROM module_treatment_planner_plans tp
                    LEFT JOIN patient_data pd ON tp.patient_id = pd.pid
                    WHERE tp.patient_id = ?
                    ORDER BY tp.date_created DESC
                    LIMIT 10";

            $result = sqlStatement($sql, [$pid]);
            $plans = [];

            while ($row = sqlFetchArray($result)) {
                try {
                    AccessService::assertPatient((int)$pid, false, (int)$row['encounter_id']);
                } catch (\RuntimeException $e) { continue; }
                $plans[] = [
                    'id' => $row['id'],
                    'status' => $row['status'],
                    'date_created' => $row['date_created'],
                    'date_updated' => $row['date_updated'],
                    'patient_name' => $row['patient_name'] ?? 'Unknown Patient'
                ];
            }

            return $plans;
        } catch (Exception $e) {
            error_log("Dashboard: Error fetching recent plans - " . $e->getMessage());
            return [];
        }
    }
}
