<?php

namespace App\Support;

/**
 * Rewrites an SVG path ("d") into plain absolute M / L / C / Z commands with arcs turned into curves.
 * TCPDF's SVG reader misreads compact path syntax (e.g. arc flags written as "0 011.5.5"), which icon sets use a lot.
 */
class SvgPath
{
    public static function normalize(string $d): string
    {
        preg_match_all('/[MmLlHhVvCcSsQqTtAaZz]|[-+]?(?:\d*\.\d+|\d+\.?)(?:[eE][-+]?\d+)?/', $d, $m);
        $tok = $m[0];
        $i = 0;
        $n = count($tok);
        $out = [];
        $x = $y = $sx = $sy = 0.0;
        $cmd = '';
        $lastC = null;  // last control point (for S)
        $lastQ = null;  // last quadratic control point (for T)
        $num = function () use (&$tok, &$i) { return (float) $tok[$i++]; };
        // arc flags may be glued together: "011" = 0, 1, 1…
        $flag = function () use (&$tok, &$i) {
            $t = $tok[$i];
            if (strlen($t) > 1 && ($t[0] === '0' || $t[0] === '1')) { $tok[$i] = substr($t, 1); return (int) $t[0]; }
            $i++;
            return (int) $t;
        };
        $isNum = function () use (&$tok, &$i, $n) { return $i < $n && !ctype_alpha($tok[$i]); };
        while ($i < $n) {
            if (ctype_alpha($tok[$i])) $cmd = $tok[$i++];
            elseif ($cmd === '') { $i++; continue; }
            $rel = ctype_lower($cmd);
            $C = strtoupper($cmd);
            if ($C === 'Z') { $out[] = 'Z'; $x = $sx; $y = $sy; $lastC = $lastQ = null; while ($isNum()) $i++; $cmd = ''; continue; }
            if (!$isNum()) { if ($i < $n && !ctype_alpha($tok[$i])) $i++; continue; }
            switch ($C) {
                case 'M':
                    $x = $num() + ($rel ? $x : 0); $y = $num() + ($rel ? $y : 0);
                    $sx = $x; $sy = $y; $out[] = 'M' . static::f($x) . ' ' . static::f($y);
                    $cmd = $rel ? 'l' : 'L';
                    $lastC = $lastQ = null;
                    break;
                case 'L':
                    $x = $num() + ($rel ? $x : 0); $y = $num() + ($rel ? $y : 0);
                    $out[] = 'L' . static::f($x) . ' ' . static::f($y); $lastC = $lastQ = null;
                    break;
                case 'H':
                    $x = $num() + ($rel ? $x : 0); $out[] = 'L' . static::f($x) . ' ' . static::f($y); $lastC = $lastQ = null;
                    break;
                case 'V':
                    $y = $num() + ($rel ? $y : 0); $out[] = 'L' . static::f($x) . ' ' . static::f($y); $lastC = $lastQ = null;
                    break;
                case 'C':
                    $x1 = $num() + ($rel ? $x : 0); $y1 = $num() + ($rel ? $y : 0);
                    $x2 = $num() + ($rel ? $x : 0); $y2 = $num() + ($rel ? $y : 0);
                    $ex = $num() + ($rel ? $x : 0); $ey = $num() + ($rel ? $y : 0);
                    $out[] = static::c($x1, $y1, $x2, $y2, $ex, $ey);
                    $lastC = [$x2, $y2]; $lastQ = null; $x = $ex; $y = $ey;
                    break;
                case 'S':
                    [$x1, $y1] = $lastC ? [2 * $x - $lastC[0], 2 * $y - $lastC[1]] : [$x, $y];
                    $x2 = $num() + ($rel ? $x : 0); $y2 = $num() + ($rel ? $y : 0);
                    $ex = $num() + ($rel ? $x : 0); $ey = $num() + ($rel ? $y : 0);
                    $out[] = static::c($x1, $y1, $x2, $y2, $ex, $ey);
                    $lastC = [$x2, $y2]; $lastQ = null; $x = $ex; $y = $ey;
                    break;
                case 'Q':
                case 'T':
                    if ($C === 'Q') { $qx = $num() + ($rel ? $x : 0); $qy = $num() + ($rel ? $y : 0); }
                    else [$qx, $qy] = $lastQ ? [2 * $x - $lastQ[0], 2 * $y - $lastQ[1]] : [$x, $y];
                    $ex = $num() + ($rel ? $x : 0); $ey = $num() + ($rel ? $y : 0);
                    $out[] = static::c($x + 2 / 3 * ($qx - $x), $y + 2 / 3 * ($qy - $y), $ex + 2 / 3 * ($qx - $ex), $ey + 2 / 3 * ($qy - $ey), $ex, $ey);
                    $lastQ = [$qx, $qy]; $lastC = null; $x = $ex; $y = $ey;
                    break;
                case 'A':
                    $rx = abs($num()); $ry = abs($num()); $rot = $num(); $large = $flag(); $sweep = $flag();
                    $ex = $num() + ($rel ? $x : 0); $ey = $num() + ($rel ? $y : 0);
                    foreach (static::arc($x, $y, $rx, $ry, $rot, $large, $sweep, $ex, $ey) as $seg) $out[] = $seg;
                    $x = $ex; $y = $ey; $lastC = $lastQ = null;
                    break;
                default:
                    $i++;
            }
        }
        return implode(' ', $out);
    }

    private static function f(float $v): string
    {
        return rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.') ?: '0';
    }

    private static function c($x1, $y1, $x2, $y2, $x, $y): string
    {
        return 'C' . implode(' ', array_map([static::class, 'f'], [$x1, $y1, $x2, $y2, $x, $y]));
    }

    /** Elliptical arc → cubic curves (SVG implementation notes, F.6). */
    private static function arc(float $x1, float $y1, float $rx, float $ry, float $phi, int $fa, int $fs, float $x2, float $y2): array
    {
        if ($rx == 0 || $ry == 0) return ['L' . static::f($x2) . ' ' . static::f($y2)];
        if ($x1 == $x2 && $y1 == $y2) return [];
        $p = deg2rad($phi);
        $cp = cos($p); $sp = sin($p);
        $dx = ($x1 - $x2) / 2; $dy = ($y1 - $y2) / 2;
        $x1p = $cp * $dx + $sp * $dy; $y1p = -$sp * $dx + $cp * $dy;
        $lam = ($x1p * $x1p) / ($rx * $rx) + ($y1p * $y1p) / ($ry * $ry);
        if ($lam > 1) { $rx *= sqrt($lam); $ry *= sqrt($lam); }
        $num = $rx * $rx * $ry * $ry - $rx * $rx * $y1p * $y1p - $ry * $ry * $x1p * $x1p;
        $den = $rx * $rx * $y1p * $y1p + $ry * $ry * $x1p * $x1p;
        $co = ($fa == $fs ? -1 : 1) * sqrt(max(0, $num / $den));
        $cxp = $co * $rx * $y1p / $ry; $cyp = -$co * $ry * $x1p / $rx;
        $cx = $cp * $cxp - $sp * $cyp + ($x1 + $x2) / 2;
        $cy = $sp * $cxp + $cp * $cyp + ($y1 + $y2) / 2;
        $ang = fn ($ux, $uy, $vx, $vy) => atan2($ux * $vy - $uy * $vx, $ux * $vx + $uy * $vy);
        $t1 = $ang(1, 0, ($x1p - $cxp) / $rx, ($y1p - $cyp) / $ry);
        $dt = $ang(($x1p - $cxp) / $rx, ($y1p - $cyp) / $ry, (-$x1p - $cxp) / $rx, (-$y1p - $cyp) / $ry);
        if (!$fs && $dt > 0) $dt -= 2 * M_PI;
        if ($fs && $dt < 0) $dt += 2 * M_PI;
        $segs = max(1, (int) ceil(abs($dt) / (M_PI / 2)));
        $d = $dt / $segs;
        $k = 4 / 3 * tan($d / 4);
        $out = [];
        $pt = function ($t) use ($cx, $cy, $rx, $ry, $cp, $sp) {
            return [$cx + $rx * cos($t) * $cp - $ry * sin($t) * $sp, $cy + $rx * cos($t) * $sp + $ry * sin($t) * $cp];
        };
        $der = function ($t) use ($rx, $ry, $cp, $sp) {
            return [-$rx * sin($t) * $cp - $ry * cos($t) * $sp, -$rx * sin($t) * $sp + $ry * cos($t) * $cp];
        };
        $t = $t1;
        for ($s = 0; $s < $segs; $s++) {
            [$ax, $ay] = $pt($t); [$adx, $ady] = $der($t);
            [$bx, $by] = $pt($t + $d); [$bdx, $bdy] = $der($t + $d);
            $out[] = static::c($ax + $k * $adx, $ay + $k * $ady, $bx - $k * $bdx, $by - $k * $bdy, $bx, $by);
            $t += $d;
        }
        return $out;
    }
}
