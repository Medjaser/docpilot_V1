<?php

namespace App\Domain\Documents;

/**
 * Génère un document PDF valide (%PDF-1.4) sans bibliothèque externe lourde,
 * utilisé pour la synthèse PDF d'un sous-traitant (§9.20) et l'aperçu des pièces de démo.
 */
final class SimplePdfGenerator
{
    /**
     * @param  list<string>  $lines
     */
    public static function fromLines(string $title, array $lines): string
    {
        $Commands = [];
        $Commands[] = 'BT';
        $Commands[] = '/F1 14 Tf';
        $Commands[] = '45 790 Td';
        $Commands[] = '('.self::escape($title).') Tj';
        $Commands[] = '/F1 10 Tf';
        $Commands[] = '0 -24 Td';

        foreach (array_slice($lines, 0, 48) as $line) {
            $Commands[] = '('.self::escape($line).') Tj';
            $Commands[] = '0 -14 Td';
        }
        $Commands[] = 'ET';

        $stream = implode("\n", $Commands);
        $len = strlen($stream);

        $objects = [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
            4 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
            5 => "<< /Length {$len} >>\nstream\n{$stream}\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }

    private static function escape(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        $clean = $converted !== false ? $converted : $text;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $clean);
    }
}

