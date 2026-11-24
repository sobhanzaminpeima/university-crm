<?php

namespace App\Support\Export;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds consistent spreadsheet downloads for every CRM export.
 *
 * Usage:
 *   return SpreadsheetExport::make('Students', $columns, $rows, $filters)->download('students');
 *
 * $columns: [['label' => 'Student', 'type' => 'string'], ...]
 *   type: string | integer | decimal | money | percent | date | datetime
 * $rows: iterable of arrays (column order) — generators / lazy collections keep memory bounded.
 * $filters: ['Stage' => 'Lead', 'Country' => 'Turkey'] — empty values are ignored.
 */
class SpreadsheetExport
{
    /**
     * @param  array<int, array{label:string,type?:string,width?:float|int}>  $columns
     * @param  iterable<int, array<int, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     */
    private function __construct(
        private string $title,
        private array $columns,
        private iterable $rows,
        private array $filters = [],
    ) {}

    public static function make(string $title, array $columns, iterable $rows, array $filters = []): self
    {
        return new self($title, $columns, $rows, $filters);
    }

    /**
     * Excel (.xlsx) download. Falls back to a clearly labelled UTF-8 CSV when
     * ext-zip is unavailable on the host — a CSV is never named .xlsx.
     */
    public function download(string $baseName): Response
    {
        $filename = self::filename($baseName);

        if (! class_exists(\ZipArchive::class)) {
            return $this->csv($baseName);
        }

        $writer = new XlsxWriter($this->title, $this->columns);
        foreach ($this->rows as $row) {
            $writer->addRow($row);
        }
        $writer->setSubtitle($this->subtitle($writer->rowCount()));
        $path = $writer->save();

        $response = new BinaryFileResponse($path, 200, [
            'Content-Type' => XlsxWriter::MIME,
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setContentDisposition('attachment', $filename.'.xlsx', Str::ascii($filename).'.xlsx');
        $response->deleteFileAfterSend(true);

        return $response;
    }

    /**
     * UTF-8 CSV with BOM (so Excel shows non-Latin characters correctly) and
     * formula-injection protection for text cells.
     */
    public function csv(string $baseName): StreamedResponse
    {
        $filename = self::filename($baseName).'.csv';
        $columns = $this->columns;
        $rows = $this->rows;

        $response = new StreamedResponse(function () use ($columns, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(fn ($c) => self::safeCsv((string) $c['label']), $columns));
            foreach ($rows as $row) {
                $line = [];
                foreach (array_values($columns) as $i => $col) {
                    $value = array_values($row)[$i] ?? '';
                    if ($value instanceof \DateTimeInterface) {
                        $value = $value->format(($col['type'] ?? '') === 'date' ? 'Y-m-d' : 'Y-m-d H:i');
                    }
                    $line[] = in_array($col['type'] ?? 'string', ['integer', 'decimal', 'money', 'percent'], true) && is_numeric($value)
                        ? $value
                        : self::safeCsv((string) $value);
                }
                fputcsv($out, $line);
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition('attachment', $filename, Str::ascii($filename)));

        return $response;
    }

    /**
     * Neutralise spreadsheet formulas in untrusted text while leaving real numbers alone.
     */
    public static function safeCsv(string $value): string
    {
        if ($value === '' || is_numeric($value)) {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    public static function filename(string $baseName): string
    {
        $slug = Str::slug($baseName) ?: 'export';

        return $slug.'-'.now()->format('Y-m-d_His');
    }

    /** Human readable enum value: "documents_pending" -> "Documents pending". */
    public static function human(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '' : Str::ucfirst(str_replace('_', ' ', $value));
    }

    private function subtitle(int $count): string
    {
        $parts = ['Generated '.now()->format('Y-m-d H:i').' ('.config('app.timezone', 'UTC').')', number_format($count).' '.($count === 1 ? 'row' : 'rows')];

        $active = [];
        foreach ($this->filters as $label => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $active[] = $label.': '.(is_array($value) ? implode(', ', $value) : (string) $value);
        }
        $parts[] = $active ? 'Filters — '.implode(' · ', $active) : 'Filters — none';

        return implode('   |   ', $parts);
    }
}
