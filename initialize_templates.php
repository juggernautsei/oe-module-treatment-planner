<?php

/**
 * Initialize default templates for Treatment Planner.
 * Run this script once to set up the default templates.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2025 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once dirname(__FILE__, 4) . '/globals.php';

use OpenEMR\Modules\TreatmentPlanner\Services\TemplateService;
use OpenEMR\Modules\TreatmentPlanner\Services\ConfigService;
use OpenEMR\Modules\TreatmentPlanner\Services\OpenAIService;
use OpenEMR\Modules\TreatmentPlanner\Services\UsageService;

echo "Initializing Treatment Planner Templates...\n";

try {
    // Initialize services
    $templateService = new TemplateService();
    $configService = new ConfigService();
    
    // Check if templates already exist
    $existingTemplates = $templateService->getAllTemplates();
    
    if (count($existingTemplates) > 0) {
        echo "Templates already exist (" . count($existingTemplates) . " found). Skipping initialization.\n";
        echo "Existing templates:\n";
        foreach ($existingTemplates as $template) {
            echo "- " . $template['name'] . "\n";
        }
    } else {
        echo "Creating default templates...\n";
        
        // Create default templates
        $createdIds = $templateService->createDefaultTemplates();
        
        echo "Successfully created " . count($createdIds) . " templates:\n";
        foreach ($createdIds as $id) {
            $template = $templateService->getTemplate($id);
            echo "- " . $template['name'] . " (ID: $id)\n";
        }
    }
    
    echo "\n--- Configuration Status ---\n";
    
    // Check API key configuration
    $apiKey = $configService->getDecryptedApiKey();
    if ($apiKey) {
        echo "✅ OpenAI API Key: Configured\n";
        
        // Test API connection
        echo "Testing API connection...\n";
        
        $usageService = new UsageService();
        $openAIService = new OpenAIService($configService, $usageService);
        
        try {
            $testResult = $openAIService->generateTreatmentPlan(
                ['age' => 35, 'gender' => 'Female'],
                'Patient reports feeling sad and hopeless for the past 3 weeks. Difficulty sleeping and loss of appetite.',
                ['template' => 'Create a brief test treatment plan for depression symptoms.'],
                1
            );
            
            echo "✅ API Connection: SUCCESS\n";
            echo "Test response received (". strlen($testResult['content']) . " characters)\n";
            echo "Model used: " . $testResult['metadata']['model'] . "\n";
            echo "Tokens used: " . $testResult['metadata']['total_tokens'] . "\n";
            
        } catch (Exception $e) {
            echo "❌ API Connection: FAILED\n";
            echo "Error: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "❌ OpenAI API Key: Not configured\n";
        echo "Please run the setup page to configure your API key.\n";
    }
    
    echo "\n--- Next Steps ---\n";
    echo "1. Configure OpenAI API key via setup page if not done\n";
    echo "2. Test treatment plan generation via the interface\n";
    echo "3. Create additional custom templates as needed\n";
    echo "\nSetup URL: " . $GLOBALS['webroot'] . "/interface/modules/custom_modules/oe-module-treatment-planner/setup.php\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nInitialization complete.\n";
