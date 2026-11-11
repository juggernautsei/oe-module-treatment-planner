<?php
namespace OpenEMR\Modules\TreatmentPlanner\Services;

use DateTimeImmutable;

class PatientAgeService
{
    public static function calculate(?string $dob, ?DateTimeImmutable $asOf = null): ?int
    {
        if (!$dob) { return null; }
        $today = ($asOf ?? new DateTimeImmutable('today'))->setTime(0, 0);
        $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $dob, $today->getTimezone());
        $errors = DateTimeImmutable::getLastErrors();
        if (!$birth || ($errors && ($errors['warning_count'] || $errors['error_count']))
            || $birth->format('Y-m-d') !== $dob || $birth > $today) { return null; }
        return $birth->diff($today)->y;
    }
}
