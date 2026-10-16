<?php

/*
 * package   OpenEMR
 * link      http://www.open-emr.org
 * author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * All rights reserved
 * Copyright (c) 2025.
 */

namespace OpenEMR\Modules\TreatmentPlanner\Controllers;

use OpenEMR\Common\Twig\TwigContainer;
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
            // Total plans
            $total_sql = "SELECT COUNT(*) as total FROM module_treatment_planner_plans";
            $total_result = sqlQuery($total_sql);
            $total_plans = (int)($total_result['total'] ?? 0);

            // Active plans (using 'approved' or 'finalized' as active status)
            $active_sql = "SELECT COUNT(*) as active FROM module_treatment_planner_plans WHERE status IN ('approved', 'finalized')";
            $active_result = sqlQuery($active_sql);
            $active_plans = (int)($active_result['active'] ?? 0);

            // AI generated plans (assuming all plans are AI generated for now)
            $ai_generated = $total_plans;

            // Recent plans (this week)
            $recent_sql = "SELECT COUNT(*) as recent FROM module_treatment_planner_plans WHERE date_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $recent_result = sqlQuery($recent_sql);
            $recent_plans = (int)($recent_result['recent'] ?? 0);

            return [
                'total_plans' => $total_plans,
                'active_plans' => $active_plans,
                'ai_generated' => $ai_generated,
                'recent_plans' => $recent_plans
            ];
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
