<?php

/**
 * Treatment Planner module setup and configuration page.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2025 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

$module_name = 'oe-module-treatment-planner';

require_once dirname(__FILE__, 4) . '/globals.php';

use OpenEMR\Core\Header;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\TreatmentPlanner\Services\ConfigService;

if (!\OpenEMR\Common\Acl\AclMain::aclCheckCore('admin', 'manage_modules')) {
    http_response_code(403);
    exit(xlt('Access denied'));
}

// Instantiate the ConfigService for API key management
$configService = new ConfigService();

// Process form submission
$errorMessage = '';
$successMessage = '';
$apiKey = '';
$model = $configService->getModel();
$temperature = $configService->getTemperature();
$testConnectionResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = is_string($_POST['csrf_token_form'] ?? null) ? $_POST['csrf_token_form'] : '';
    if (!CsrfUtils::verifyCsrfToken($token)) {
        $errorMessage = xl('Authentication Error');
    } else {
        try {
            // Validate before writing any settings; invalid values are not silently clamped.
            $newTemperature = ConfigService::validateTemperature($_POST['temperature'] ?? $temperature);
            $newModel = is_string($_POST['model'] ?? null) ? trim($_POST['model']) : $model;
            $newKey = is_string($_POST['api_key'] ?? null) ? trim($_POST['api_key']) : '';
            if ($newModel === '') { throw new \OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException('Select a model.'); }
            if ($newKey !== '' && !$configService->saveApiKey($newKey)) { throw new RuntimeException('Could not save API key.'); }
            if (!$configService->saveModel($newModel) || !$configService->saveTemperature($newTemperature)) {
                throw new RuntimeException('Could not save settings.');
            }
            $model = $newModel;
            $temperature = $newTemperature;
            if (isset($_POST['test_connection'])) { $testConnectionResult = $configService->testApiConnection(); }
            else { $successMessage = xl('Configuration saved successfully'); }
        } catch (\OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException $e) {
            $errorMessage = xl($e->getMessage());
        } catch (Throwable $e) {
            $errorMessage = \OpenEMR\Modules\TreatmentPlanner\Services\ErrorService::publicMessage($e, 'save_config');
        }
    }
}

// Get current API key status (configured or not)
$apiKeyConfigured = $configService->isApiKeyConfigured();

?>
<!DOCTYPE html>
<html>
<head>
    <?php Header::setupHeader(['opener']); ?>
    <title><?php echo xlt('Treatment Planner Configuration'); ?></title>
</head>
<body>
    <div class="container mt-3">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h2><?php echo xlt('Treatment Planner Configuration'); ?></h2>
                    </div>
                    <div class="card-body">
                        <?php if ($errorMessage): ?>
                            <div class="alert alert-danger">
                                <?php echo text($errorMessage); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($successMessage): ?>
                            <div class="alert alert-success">
                                <?php echo text($successMessage); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($testConnectionResult): ?>
                            <div class="alert alert-<?php echo $testConnectionResult['success'] ? 'success' : 'danger'; ?>">
                                <strong><?php echo xlt('Connection Test Result'); ?>:</strong><br>
                                <?php echo text($testConnectionResult['message']); ?>
                                <?php if (isset($testConnectionResult['details'])): ?>
                                    <br><small><?php echo text($testConnectionResult['details']); ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" action="" id="config-form">
                            <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>">
                            
                            <div class="form-group">
                                <label for="api-key"><?php echo xlt('OpenAI API Key'); ?></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="api-key" name="api_key" 
                                           placeholder="<?php echo xla('Enter your OpenAI API key'); ?>" 
                                           value="<?php echo attr($apiKey); ?>">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" id="toggle-password">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <small class="form-text text-muted">
                                    <?php echo xlt('Your API key is encrypted and stored securely.'); ?>
                                    <?php if ($apiKeyConfigured): ?>
                                        <span class="text-success"><?php echo xlt('API Key is currently configured.'); ?></span>
                                    <?php else: ?>
                                        <span class="text-warning"><?php echo xlt('No API Key configured.'); ?></span>
                                    <?php endif; ?>
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label for="model"><?php echo xlt('OpenAI Model'); ?></label>
                                <select class="form-control" id="model" name="model">
                                    <option value="gpt-4" <?php echo ($model === 'gpt-4') ? 'selected' : ''; ?>>GPT-4</option>
                                    <option value="gpt-4-turbo" <?php echo ($model === 'gpt-4-turbo') ? 'selected' : ''; ?>>GPT-4 Turbo</option>
                                    <option value="gpt-3.5-turbo" <?php echo ($model === 'gpt-3.5-turbo') ? 'selected' : ''; ?>>GPT-3.5 Turbo</option>
                                </select>
                                <small class="form-text text-muted">
                                    <?php echo xlt('Select the OpenAI model to use for generating treatment plans.'); ?>
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label for="temperature"><?php echo xlt('Temperature'); ?></label>
                                <input type="number" class="form-control" id="temperature" name="temperature" 
                                       min="0" max="1" step="0.1" value="<?php echo attr($temperature); ?>">
                                <small class="form-text text-muted">
                                    <?php echo xlt('Controls randomness: 0 = least variable, 1 = more variable. Recommended: 0.7'); ?>
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <div class="btn-group" role="group">
                                    <button type="submit" class="btn btn-primary">
                                        <?php echo xlt('Save Configuration'); ?>
                                    </button>
                                    <button type="submit" name="test_connection" value="1" class="btn btn-info">
                                        <?php echo xlt('Test Connection'); ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer">
                        <div class="row">
                            <div class="col-md-6">
                                <h5><?php echo xlt('Usage Information'); ?></h5>
                                <p class="small text-muted">
                                    <?php echo xlt('This module uses OpenAI\'s API to generate treatment plans. Usage is tracked. Cost estimates require administrator-configured model rates; otherwise cost is unavailable.'); ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h5><?php echo xlt('Quick Links'); ?></h5>
                                <ul class="list-unstyled small">
                                    <li><a href="retry_usage.php"><?php echo xlt('Retry Usage Recording'); ?></a></li>
                                    <li><a href="initialize_templates.php"><?php echo xlt('Initialize Default Templates'); ?></a></li>
                                    <li><a href="public/index.php/treatment/create"><?php echo xlt('Create Treatment Plan'); ?></a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle password visibility
        const toggleButton = document.getElementById('toggle-password');
        const passwordField = document.getElementById('api-key');
        
        toggleButton.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    });
    </script>
</body>
</html>
