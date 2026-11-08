<?php
require dirname(__DIR__) . '/src/Exceptions/UserFacingException.php';
require dirname(__DIR__) . '/src/Services/ApiErrorService.php';
use OpenEMR\Modules\TreatmentPlanner\Services\ApiErrorService;
use OpenEMR\Modules\TreatmentPlanner\Services\ErrorService;
use OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException;
$cases = [
    [404, '{"error":{"code":"model_not_found","message":"SECRET_PATIENT_SENTINEL"}}', 'select an accessible model', true],
    [401, '{"error":{"message":"SECRET_PATIENT_SENTINEL"}}', 'rejected the configured API key', true],
    [429, '{"error":{"code":"insufficient_quota"}}', 'insufficient quota', true],
    [429, '{"error":{"code":"rate_limit_exceeded"}}', 'rate limit', true],
    [500, '{"error":{"message":"SECRET_PATIENT_SENTINEL"}}', 'OpenAI request failed.', false],
    [404, '{"error":{"code":"other","message":"SECRET_PATIENT_SENTINEL"}}', 'OpenAI request failed.', false],
    [502, 'SECRET_PATIENT_SENTINEL malformed JSON', 'OpenAI request failed.', false],
];
foreach ($cases as [$status, $body, $expected, $safe]) {
    $error = ApiErrorService::fromResponse($status, $body);
    if ($error->getCode() !== $status || strpos($error->getMessage(), $expected) === false
        || ($error instanceof UserFacingException) !== $safe || strpos($error->getMessage(), 'SECRET_PATIENT_SENTINEL') !== false) {
        throw new RuntimeException('API error mapping or redaction failed');
    }
}
echo count($cases) . " API error checks passed\n";
