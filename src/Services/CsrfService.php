<?php
namespace OpenEMR\Modules\TreatmentPlanner\Services;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Session\SessionWrapperFactory;

/** Delegate token creation and verification to the installed core API. */
class CsrfService
{
    private static function requiresSession(): bool
    {
        $parameter = (new \ReflectionMethod(CsrfUtils::class, 'collectCsrfToken'))->getParameters()[0];
        $type = $parameter->getType();
        return $type instanceof \ReflectionNamedType && !$type->isBuiltin();
    }

    public static function collectCsrfToken(string $subject = 'default'): string
    {
        if (self::requiresSession()) {
            return CsrfUtils::collectCsrfToken(SessionWrapperFactory::getInstance()->getActiveSession(), $subject);
        }
        return CsrfUtils::collectCsrfToken($subject);
    }

    public static function verifyCsrfToken($token, string $subject = 'default'): bool
    {
        if (!is_string($token) || $token === '') { return false; }
        if (self::requiresSession()) {
            return CsrfUtils::verifyCsrfToken($token, SessionWrapperFactory::getInstance()->getActiveSession(), $subject);
        }
        return CsrfUtils::verifyCsrfToken($token, $subject);
    }
}
