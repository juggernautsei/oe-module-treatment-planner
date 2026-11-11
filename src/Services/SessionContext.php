<?php
namespace OpenEMR\Modules\TreatmentPlanner\Services;

use OpenEMR\Common\Session\SessionWrapperFactory;

/** Read the core session without mixing modern and legacy identities. */
class SessionContext
{
    public static function get(string $key, $default = null)
    {
        if (class_exists(SessionWrapperFactory::class)) {
            // Do not fall back to stale native values when the core session is empty or fails.
            return SessionWrapperFactory::getInstance()->getActiveSession()->get($key, $default);
        }
        return $_SESSION[$key] ?? $default;
    }

    public static function isAuthenticated(): bool
    {
        return (string)self::get('authUser', '') !== '' && (int)self::get('authUserID', 0) > 0;
    }
}
