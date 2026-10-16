<?php

/**
 * ConfigService.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    [Author Name]
 * @copyright Copyright (c) 2025
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\TreatmentPlanner\Services;

use OpenEMR\Common\Crypto\CryptoGen;
use OpenEMR\Common\Database\QueryUtils;

class ConfigService
{
    private CryptoGen $cryptoGen;
    private const API_KEY_SETTING = 'openai_api_key';
    private const MODEL_SETTING = 'openai_model';
    private const TEMPERATURE_SETTING = 'openai_temperature';
    
    // Default values
    private const DEFAULT_MODEL = 'gpt-4';
    private const DEFAULT_TEMPERATURE = '0.7';

    public function __construct()
    {
        $this->cryptoGen = new CryptoGen();
    }

    /**
     * Save the API key to the database with encryption
     * 
     * @param string $apiKey The API key to save
     * @return bool Whether the save operation was successful
     */
    public function saveApiKey(string $apiKey): bool
    {
        $encryptedKey = $this->cryptoGen->encryptStandard($apiKey);
        return $this->saveSetting(self::API_KEY_SETTING, $encryptedKey, true);
    }

    /**
     * Get the encrypted API key from the database
     * 
     * @return string|null The encrypted API key or null if not found
     */
    public function getEncryptedApiKey(): ?string
    {
        return $this->getSetting(self::API_KEY_SETTING);
    }

    /**
     * Get the decrypted API key
     * 
     * @return string|null The decrypted API key or null if not found/decryption failed
     */
    public function getDecryptedApiKey(): ?string
    {
        $encryptedKey = $this->getEncryptedApiKey();
        if (empty($encryptedKey)) {
            return null;
        }
        
        return $this->cryptoGen->decryptStandard($encryptedKey);
    }

    /**
     * Check if the API key is configured
     * 
     * @return bool Whether the API key is configured
     */
    public function isApiKeyConfigured(): bool
    {
        return !empty($this->getEncryptedApiKey());
    }
    
    /**
     * Save the OpenAI model selection
     * 
     * @param string $model The model name (e.g., 'gpt-4', 'gpt-3.5-turbo')
     * @return bool Whether the save operation was successful
     */
    public function saveModel(string $model): bool
    {
        return $this->saveSetting(self::MODEL_SETTING, $model);
    }
    
    /**
     * Get the configured OpenAI model
     * 
     * @return string The model name, or default if not configured
     */
    public function getModel(): string
    {
        $model = $this->getSetting(self::MODEL_SETTING);
        return $model !== null ? $model : self::DEFAULT_MODEL;
    }
    
    /**
     * Save the temperature setting for the API
     * 
     * @param float $temperature The temperature value between 0 and 1
     * @return bool Whether the save operation was successful
     */
    public function saveTemperature(float $temperature): bool
    {
        $temperature = min(1.0, max(0.0, $temperature)); // Ensure value is between 0 and 1
        return $this->saveSetting(self::TEMPERATURE_SETTING, (string)$temperature);
    }
    
    /**
     * Get the configured temperature
     * 
     * @return float The temperature value, or default if not configured
     */
    public function getTemperature(): float
    {
        $temperature = $this->getSetting(self::TEMPERATURE_SETTING);
        return $temperature !== null ? (float)$temperature : (float)self::DEFAULT_TEMPERATURE;
    }

    /**
     * Save a setting to the database
     * 
     * @param string $key The setting key
     * @param string $value The setting value
     * @param bool $encrypted Whether the value is encrypted
     * @return bool Whether the save operation was successful
     */
    private function saveSetting(string $key, string $value, bool $encrypted = false): bool
    {
        $existing = $this->getSetting($key);
        $encryptedFlag = $encrypted ? 1 : 0;
        
        if ($existing === null) {
            // Insert new setting
            $sql = "INSERT INTO `module_treatment_planner_config` 
                    (`setting_key`, `setting_value`, `encrypted`) 
                    VALUES (?, ?, ?)";
            $params = [$key, $value, $encryptedFlag];
            return QueryUtils::sqlInsert($sql, $params) > 0;
        } else {
            // Update existing setting
            $sql = "UPDATE `module_treatment_planner_config` 
                    SET `setting_value` = ?, `encrypted` = ? 
                    WHERE `setting_key` = ?";
            $params = [$value, $encryptedFlag, $key];
            $result = QueryUtils::sqlStatementThrowException($sql, $params);
            return $result !== false;
        }
    }

    /**
     * Get a setting from the database
     * 
     * @param string $key The setting key
     * @return string|null The setting value or null if not found
     */
    private function getSetting(string $key): ?string
    {
        $sql = "SELECT `setting_value` FROM `module_treatment_planner_config` WHERE `setting_key` = ?";
        $result = QueryUtils::fetchSingleValue($sql, 'setting_value', [$key]);
        
        return $result !== false ? $result : null;
    }

    /**
     * Get all settings
     * 
     * @return array All settings as key-value pairs
     */
    public function getAllSettings(): array
    {
        $sql = "SELECT `setting_key`, `setting_value`, `encrypted` FROM `module_treatment_planner_config`";
        $results = QueryUtils::fetchRecords($sql);
        
        $settings = [];
        foreach ($results as $row) {
            $value = $row['setting_value'];
            if ($row['encrypted']) {
                $value = $this->cryptoGen->decryptStandard($value);
            }
            $settings[$row['setting_key']] = $value;
        }
        
        return $settings;
    }
    
    /**
     * Test the API key connection to OpenAI
     * 
     * @return array Result with status and message
     */
    public function testApiConnection(): array
    {
        $apiKey = $this->getDecryptedApiKey();
        
        if (empty($apiKey)) {
            return [
                'success' => false,
                'message' => xl('No API key configured')
            ];
        }
        
        // Simple cURL request to test the API connection
        $ch = curl_init('https://api.openai.com/v1/models');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($info['http_code'] == 200) {
            return [
                'success' => true,
                'message' => xl('Connection successful')
            ];
        } else {
            $errorMsg = !empty($error) ? $error : xl('Error code') . ': ' . $info['http_code'];
            return [
                'success' => false,
                'message' => $errorMsg
            ];
        }
    }
}
