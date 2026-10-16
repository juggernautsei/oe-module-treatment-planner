<?php

/**
 * Class to be called from Laminas Module Manager for reporting management actions.
 * Example is if the module is enabled, disabled or unregistered ect.
 *
 * The class is in the Laminas "Installer\Controller" namespace.
 * Currently, register isn't supported of which support should be a part of install.
 * If an error needs to be reported to user, return description of error.
 * However, whatever action trapped here has already occurred in Manager.
 * Catch any exceptions because chances are they will be overlooked in Laminas module.
 * Report them in the return value.
 *
 * @package   OpenEMR Modules
 * @link      https://www.open-emr.org
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2024 Jerry Padgett <sjpadgett@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

/*
 * Do not declare a namespace
 * If you want Laminas manager to set namespace set it in getModuleNamespace
 * otherwise uncomment below and set path.
 *
 * */

/*
    $classLoader = new \OpenEMR\Core\ModulesClassLoader($GLOBALS['fileroot']);
    $classLoader->registerNamespaceIfNotExists("OpenEMR\\Module\\TreatmentPlanner\\App\\", __DIR__ . DIRECTORY_SEPARATOR . 'src');
*/

use OpenEMR\Core\AbstractModuleActionListener;
use OpenEMR\Modules\WenoModule\Services\ModuleService;

/* Allows maintenance of background tasks depending on Module Manager action. */

class ModuleManagerListener extends AbstractModuleActionListener
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param        $methodName
     * @param        $modId
     * @param string $currentActionStatus
     * @return string On method success a $currentAction status should be returned or error string.
     */
    public function moduleManagerAction($methodName, $modId, string $currentActionStatus = 'Success'): string
    {
        if (method_exists(self::class, $methodName)) {
            return self::$methodName($modId, $currentActionStatus);
        } else {
            // no reason to report action method is missing.
            return $currentActionStatus;
        }
    }

    /**
     * Required method to return namespace
     * If namespace isn't provided return empty string
     * and register namespace at top of this script..
     *
     * @return string
     */
    public static function getModuleNamespace(): string
    {
        // Module Manager will register this namespace.
        // You don't have to use the OpenEMR namespace. You can create your own namespace.
        return 'OpenEMR\\Module\\TreatmentPlanner\\App\\';
    }

    /**
     * Required method to return this class object
     * so it will be instantiated in Laminas Manager.
     *
     * @return ModuleManagerListener
     */
    public static function initListenerSelf(): ModuleManagerListener
    {
        return new self();
    }

    /**
     * @param $modId
     * @param $currentActionStatus
     * @return mixed
     */
    private function install($modId, $currentActionStatus): mixed
    {
        $modService = new ModuleService();
        /* setting the active ui flag here will allow the config button to show
         * before enable. This is a good thing because it allows the user to
         * configure the module before enabling it. However, if the module is disabled
         * this flag is reset by MM.
        */
        $modService::setModuleState($modId, '0', '1');
        return $currentActionStatus;
    }

    /**
     * @param $modId
     * @param $currentActionStatus
     * @return mixed
     */
    private function help_requested($modId, $currentActionStatus): mixed
    {
        // must call a script that implements a dialog to show help.
        // I can't find a way to override the Lamina's UI except using a dialog.
        if (file_exists(__DIR__ . '/show_help.php')) {
            include __DIR__ . '/show_help.php';
        }
        return $currentActionStatus;
    }

    /**
     * @param $modId
     * @param $currentActionStatus
     * @return mixed
     */
    private function enable($modId, $currentActionStatus): mixed
    {
        $logMessage = 'Skeleton Background tasks have been enabled';
        // Register background services
//        $sql = "UPDATE `background_services` SET `active` = '1' WHERE `name` = ? OR `name` = ? OR `name` = ?";
//        $status = sqlQuery($sql, array('Skeleton_Send', 'Skeleton_Receive', 'Skeleton_Send_Receive'));
//        error_log($logMessage . ' ' . text($status));
        // Return the current action status from Module Manager in case of error from its action.
        return $currentActionStatus;
    }

    /**
     * @param $modId
     * @param $currentActionStatus
     * @return mixed
     */
    private function disable($modId, $currentActionStatus): mixed
    {
        // allow config button to show before enable.
        ModuleService::setModuleState($modId, '0', '1');
        return $currentActionStatus;
    }

    /**
     * @param $modId
     * @param $currentActionStatus
     * @return mixed
     */
    private function unregister($modId, $currentActionStatus): mixed
    {
        /*
         * This function removes all data that was created by the Treatment Planner module.
         * This includes custom tables, layout options, and list options.
         */
        $logMessage = 'Treatment Planner module cleanup initiated: '; // Initialize log message
        $errors = [];
        
        try {
            // Drop module-specific tables
            $tables = [
                'module_treatment_planner_config',
                'module_treatment_planner_plans',
                'module_treatment_planner_templates',
                'module_treatment_planner_usage'
            ];
            
            foreach ($tables as $table) {
                $sql = "DROP TABLE IF EXISTS `$table`";
                $result = sqlQuery($sql);
                if ($result === false) {
                    $errors[] = "Failed to drop table: $table";
                    $logMessage .= "DROP TABLE `$table`: Failed\n";
                } else {
                    $logMessage .= "DROP TABLE `$table`: Success\n";
                }
            }
            
            // Clean up layout options for LBF_MHINTAKE form
            $sql = "DELETE FROM `layout_options` WHERE `form_id` = 'LBF_MHINTAKE'";
            $result = sqlQuery($sql);
            if ($result === false) {
                $errors[] = "Failed to delete layout_options for LBF_MHINTAKE";
                $logMessage .= "DELETE layout_options for LBF_MHINTAKE: Failed\n";
            } else {
                $logMessage .= "DELETE layout_options for LBF_MHINTAKE: Success\n";
            }
            
            // Clean up layout group properties for LBF_MHINTAKE form
            $sql = "DELETE FROM `layout_group_properties` WHERE `grp_form_id` = 'LBF_MHINTAKE'";
            $result = sqlQuery($sql);
            if ($result === false) {
                $errors[] = "Failed to delete layout_group_properties for LBF_MHINTAKE";
                $logMessage .= "DELETE layout_group_properties for LBF_MHINTAKE: Failed\n";
            } else {
                $logMessage .= "DELETE layout_group_properties for LBF_MHINTAKE: Success\n";
            }
            
            // Clean up mental health assessment list options
            $mentalHealthLists = [
                'mental_status_affect_amplitude',
                'mental_status_affect_appropriateness',
                'mental_status_affect_intensity',
                'mental_status_affect_mobility',
                'mental_status_affect_range',
                'mental_status_appearance',
                'mental_status_attitude',
                'mental_status_build',
                'mental_status_cognition_insight',
                'mental_status_eye_contact',
                'mental_status_gait',
                'mental_status_hygiene',
                'mental_status_intelligence',
                'mental_status_judgment',
                'mental_status_long_term',
                'mental_status_mood',
                'mental_status_orientation',
                'mental_status_phobias',
                'mental_status_psychomotor',
                'mental_status_short_term',
                'mental_status_speech_rate',
                'mental_status_speech_rhythm_tonality',
                'mental_status_speech_volume',
                'mental_status_thought_content',
                'mental_status_thought_process',
                'RiskProtection',
                'risklevel'
            ];
            
            foreach ($mentalHealthLists as $listId) {
                // Delete list options
                $sql = "DELETE FROM `list_options` WHERE `list_id` = ?";
                $result = sqlQuery($sql, [$listId]);
                if ($result === false) {
                    $errors[] = "Failed to delete list_options for: $listId";
                    $logMessage .= "DELETE list_options for $listId: Failed\n";
                } else {
                    $logMessage .= "DELETE list_options for $listId: Success\n";
                }
                
                // Delete from lists registry
                $sql = "DELETE FROM `list_options` WHERE `list_id` = 'lists' AND `option_id` = ?";
                $result = sqlQuery($sql, [$listId]);
                if ($result === false) {
                    $errors[] = "Failed to delete from lists registry for: $listId";
                    $logMessage .= "DELETE from lists registry for $listId: Failed\n";
                } else {
                    $logMessage .= "DELETE from lists registry for $listId: Success\n";
                }
            }
            
            // If there were any errors, update the status
            if (!empty($errors)) {
                $currentActionStatus = 'Partial cleanup completed with errors: ' . implode('; ', $errors);
                $logMessage .= "Cleanup completed with " . count($errors) . " errors\n";
            } else {
                $logMessage .= "Treatment Planner module cleanup completed successfully\n";
            }
            
        } catch (Exception $e) {
            $currentActionStatus = 'Error during cleanup: ' . $e->getMessage();
            $logMessage .= "Exception during cleanup: " . $e->getMessage() . "\n";
        }
        
        // Log the cleanup results
        error_log(text($logMessage));
        
        return $currentActionStatus;
    }

    /**
     * @param $modId
     * @param $currentActionStatus
     * @return mixed
     */
    private function install_sql($modId, $currentActionStatus): mixed
    {
        return $currentActionStatus;
    }

    /**
     * @param $modId
     * @param $currentActionStatus
     * @return mixed
     */
    private function upgrade_sql($modId, $currentActionStatus): mixed
    {
        return $currentActionStatus;
    }
}
