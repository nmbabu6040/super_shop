<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a report as a CSV download. Text cells that a spreadsheet could run as a formula
 * (starting with = + - @) are neutralised with a leading apostrophe (CSV injection).
 */
final class ReportCsvWriter
{
    /**
     * @param  list<string>  $headings
     * @param  iterable<list<string|int|null>>  $rows
     */
    public function download(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows): void {
            $handle = fopen('php://output', 'w');

            // The byte order mark lets Excel read Bangla text correctly.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headings, ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($handle, array_map($this->safeCell(...), $row), ',', '"', '');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCell(string|int|null $cell): string
    {
        $text = (string) ($cell ?? '');

        if ($text === '' || is_numeric($text)) {
            return $text;
        }

        return in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $text : $text;
    }
}
