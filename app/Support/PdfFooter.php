<?php

namespace App\Support;

use App\Models\Event;

/**
 * Same footer on every page of the PDF reports: the event / venue the report is about (left), when it was made
 * (middle, with an optional note such as "Confidential") and "Page X of Y" (right), above a thin rule.
 *
 *   $dompdf = $pdf->getDomPDF(); $dompdf->render(); PdfFooter::apply($dompdf, PdfFooter::label($venue));
 */
class PdfFooter
{
    /** The report's event name and venue (from Admin › Events) for a venue code, or "All events" when it covers several. */
    public static function label(?string $venue = null, ?Event $event = null): string
    {
        $event = $event ?: ($venue ? Event::forVenue($venue) : null);
        if ($event) {
            // "4th Beacon International Cancer Summit 2026 · Feria Valencia, 46035 Valencia, Spain" / "BICS Algeria · Venue: Algeria"
            $name = $event->event_name ?: $event->event_short;
            $where = $event->event_venue ?: ($event->venue_code ? 'Venue: ' . $event->venue_code : null);
            return trim($name . ($where && !($event->event_venue && stripos($name, $event->event_venue) !== false) ? ' · ' . $where : ''));
        }
        return $venue ? 'Venue: ' . $venue : 'All events';
    }

    public static function apply($dompdf, string $label, ?string $note = null): void
    {
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = $metrics->getFont('DejaVu Sans', 'normal');
        $bold = $metrics->getFont('DejaVu Sans', 'bold') ?: $font;
        $w = $canvas->get_width();
        $h = $canvas->get_height();
        $y = $h - 24;
        $grey = [0.42, 0.45, 0.52];
        $made = ($note ? $note . '  ·  ' : '') . 'Generated ' . now(EventSettings::timezone())->format('j M Y, H:i');
        $size = 7.5;
        $left = 36;
        $pageX = $w - 110;
        $width = fn ($t, $f) => $metrics->getTextWidth($t, $f, $size);
        // the event name never runs into the page number
        while (mb_strlen($label) > 8 && $width($label, $bold) > $pageX - $left - 16) $label = rtrim(mb_substr($label, 0, -2)) . '…';
        $labelW = $width($label, $bold);
        $madeW = $width($made, $font);
        $madeX = max(($w - $madeW) / 2, $left + $labelW + 18);
        $oneLine = $madeX + $madeW + 16 <= $pageX;
        // drawn on every page, after the content
        $canvas->page_line($left, $y - 6, $w - $left, $y - 6, [0.85, 0.87, 0.91], 0.6);
        $canvas->page_text($left, $y, $label, $bold, $size, [0.06, 0.1, 0.18]);
        if ($oneLine) $canvas->page_text($madeX, $y, $made, $font, $size, $grey);
        else $canvas->page_text($left, $y + 10, $made, $font, $size - 0.5, $grey);   // narrow page / long name: second line
        $canvas->page_text($pageX, $y, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, $size, $grey);
    }
}
