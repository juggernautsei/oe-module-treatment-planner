<?php
namespace OpenEMR\Modules\TreatmentPlanner\Services;

use OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException;

class ErrorService
{
    public static function publicMessage(\Throwable $error, string $operation = 'module'): string
    {
        if ($error instanceof UserFacingException && !$error->getPrevious()) { return $error->getMessage(); }
        try { $reference = bin2hex(random_bytes(6)); }
        catch (\Throwable $ignored) { $reference = substr(hash('sha256', uniqid('', true)), 0, 12); }
        $cause = $error->getPrevious() ?? $error;
        // Deliberately omit messages, arguments, SQL, paths, notes, keys and stack traces.
        error_log('Treatment Planner error ' . json_encode([
            'reference' => $reference, 'operation' => preg_replace('/[^a-zA-Z0-9_-]/', '', $operation),
            'type' => get_class($error), 'cause_type' => get_class($cause),
            'code' => preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$cause->getCode()),
            'file' => basename($cause->getFile()), 'line' => $cause->getLine()
        ]));
        return ($error instanceof UserFacingException ? $error->getMessage() : 'The operation could not be completed.') . ' Reference: ' . $reference;
    }
}
