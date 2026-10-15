<?php

namespace App\Support;

/**
 * Dates of birth typed on the registration form ("Month / Day / Year", but some people write day/month, or a 2-digit year).
 */
class BirthDate
{
    /** "01/28/1978", "23/07/1985", "12/10/80", "1978-01-28" → "1978-01-28", or null when it is not a believable birth date. */
    public static function parse(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') return null;
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $v, $m)) [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        elseif (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{2}|\d{4})$#', $v, $m)) {
            [$a, $b, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            // the form asks for month / day; a first number above 12 can only be a day
            [$mo, $d] = $a > 12 ? [$b, $a] : [$a, $b];
            if ($y < 100) $y += $y > (int) date('y') ? 1900 : 2000;
        } else return null;
        if (!checkdate($mo, $d, $y)) return null;
        $date = sprintf('%04d-%02d-%02d', $y, $mo, $d);
        $age = static::age($date);
        return $age !== null && $age >= 10 && $age <= 110 ? $date : null;
    }

    public static function age(?string $date): ?int
    {
        if (!$date) return null;
        try { return \Carbon\Carbon::parse($date)->age; } catch (\Throwable $e) { return null; }
    }
}
