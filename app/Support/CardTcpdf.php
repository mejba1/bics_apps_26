<?php

namespace App\Support;

// TCPDF's default font is Helvetica, which is never embedded and makes print preflight checks complain.
// Use its own settings (no config file) with an embeddable default font.
if (!class_exists(\TCPDF::class, false)) {
    defined('K_TCPDF_EXTERNAL_CONFIG') || define('K_TCPDF_EXTERNAL_CONFIG', true);
    defined('PDF_FONT_NAME_MAIN') || define('PDF_FONT_NAME_MAIN', 'dejavusans');
    defined('PDF_FONT_NAME_DATA') || define('PDF_FONT_NAME_DATA', 'dejavusans');
    defined('PDF_FONT_MONOSPACED') || define('PDF_FONT_MONOSPACED', 'dejavusansmono');
    defined('K_TCPDF_THROW_EXCEPTION_ERROR') || define('K_TCPDF_THROW_EXCEPTION_ERROR', true);
}

/** TCPDF with one extra: fill an SVG-style path (M / L / C / Z, as made by SvgPath::normalize) as native PDF vectors. */
class CardTcpdf extends \TCPDF
{
    public function __construct(...$args)
    {
        parent::__construct(...$args);
        $this->tcpdflink = false;   // no hidden "Powered by TCPDF" link (it also uses Helvetica)
    }

    /** Draw $d (in its own units) at ($x, $y) scaled by $scale, filled with the current fill colour. */
    public function fillSvgPath(string $d, float $x, float $y, float $scale, bool $evenOdd = false): void
    {
        $k = $this->k;
        $h = $this->h;
        $X = fn ($v) => sprintf('%F', ($x + $v * $scale) * $k);
        $Y = fn ($v) => sprintf('%F', ($h - ($y + $v * $scale)) * $k);
        $ops = [];
        preg_match_all('/([MLCZ])([^MLCZ]*)/', $d, $m, PREG_SET_ORDER);
        foreach ($m as [, $c, $args]) {
            $n = $args === '' ? [] : array_map('floatval', preg_split('/[\s,]+/', trim($args)));
            if ($c === 'M' && count($n) >= 2) $ops[] = $X($n[0]) . ' ' . $Y($n[1]) . ' m';
            elseif ($c === 'L' && count($n) >= 2) $ops[] = $X($n[0]) . ' ' . $Y($n[1]) . ' l';
            elseif ($c === 'C' && count($n) >= 6) $ops[] = $X($n[0]) . ' ' . $Y($n[1]) . ' ' . $X($n[2]) . ' ' . $Y($n[3]) . ' ' . $X($n[4]) . ' ' . $Y($n[5]) . ' c';
            elseif ($c === 'Z') $ops[] = 'h';
        }
        if ($ops) $this->_out(implode("\n", $ops) . ($evenOdd ? ' f*' : ' f'));
    }
}
