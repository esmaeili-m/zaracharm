<?php

namespace App\Services\Sms;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * خواندن فایل CSV یا Excel (xlsx، اولین برگه) بدون وابستگی خارجی
 * سطر اول عنوان ستون‌ها در نظر گرفته می‌شود.
 */
class SpreadsheetReader
{
    /**
     * @return array{headers: list<string>, rows: list<list<string>>, truncated: bool}
     */
    public static function read(string $path, string $extension, int $maxRows = 20000): array
    {
        $rows = match (strtolower($extension)) {
            'csv', 'txt' => self::csv($path, $maxRows + 2),
            'xlsx' => self::xlsx($path, $maxRows + 2),
            default => throw new RuntimeException('فقط فایل CSV یا Excel (xlsx) پشتیبانی می‌شود.'),
        };

        $rows = array_values(array_filter($rows, fn ($row) => implode('', array_map('trim', $row)) !== ''));

        if (! $rows) {
            throw new RuntimeException('فایل خالی است.');
        }

        $headers = array_map(fn ($h) => trim((string) $h), array_shift($rows));
        $width = count($headers);

        foreach ($headers as $i => $header) {
            if ($header === '') {
                $headers[$i] = 'ستون ' . ($i + 1);
            }
        }

        $rows = array_map(function ($row) use ($width) {
            $row = array_map(fn ($v) => trim((string) $v), array_slice(array_pad($row, $width, ''), 0, $width));

            return $row;
        }, $rows);

        $truncated = count($rows) > $maxRows;

        return ['headers' => $headers, 'rows' => array_slice($rows, 0, $maxRows), 'truncated' => $truncated];
    }

    protected static function csv(string $path, int $limit): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // CSV ذخیره‌شده با Excel فارسی معمولاً Windows-1256 است
        if (! mb_check_encoding($content, 'UTF-8') && function_exists('iconv')) {
            $converted = @iconv('WINDOWS-1256', 'UTF-8//IGNORE', $content);
            $content = $converted !== false ? $converted : $content;
        }

        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn ($d) => substr_count($firstLine, $d))->first();

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false && count($rows) < $limit) {
            $rows[] = array_map(fn ($v) => (string) $v, $row);
        }
        fclose($handle);

        return $rows;
    }

    protected static function xlsx(string $path, int $limit): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('فایل Excel قابل خواندن نیست.');
        }

        try {
            $strings = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                foreach (self::xml($xml)->si as $si) {
                    // متن ساده یا متن با قالب‌بندی (r/t)
                    $strings[] = isset($si->t) ? (string) $si->t : implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]') ?: []));
                }
            }

            $sheetXml = $zip->getFromName(self::firstSheetPath($zip));

            if ($sheetXml === false) {
                throw new RuntimeException('برگه‌ای در فایل Excel یافت نشد.');
            }

            $rows = [];
            foreach (self::xml($sheetXml)->sheetData->row as $row) {
                if (count($rows) >= $limit) {
                    break;
                }

                $cells = [];
                $next = 0;
                foreach ($row->c as $c) {
                    $index = (string) $c['r'] !== '' ? self::columnIndex((string) $c['r']) : $next;
                    $next = $index + 1;
                    $type = (string) $c['t'];
                    $value = match ($type) {
                        's' => $strings[(int) $c->v] ?? '',
                        'inlineStr' => (string) ($c->is->t ?? ''),
                        default => (string) $c->v,
                    };

                    // عدد بزرگ با نماد علمی (مثل 9.12E+9) → رقم کامل
                    if ($type === '' && preg_match('/^\d+(\.\d+)?E\+?\d+$/i', $value)) {
                        $value = sprintf('%.0f', (float) $value);
                    }

                    $cells[$index] = $value;
                }

                if ($cells) {
                    $max = max(array_keys($cells));
                    $rows[] = array_map(fn ($i) => $cells[$i] ?? '', range(0, $max));
                } else {
                    $rows[] = [];
                }
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    protected static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook !== false && $rels !== false) {
            $sheet = self::xml($workbook)->sheets->sheet[0] ?? null;
            $rid = $sheet ? (string) ($sheet->attributes('r', true)['id'] ?? '') : '';

            foreach (self::xml($rels)->Relationship as $rel) {
                if ((string) $rel['Id'] === $rid) {
                    $target = ltrim((string) $rel['Target'], '/');

                    return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    protected static function columnIndex(string $reference): int
    {
        $letters = preg_replace('/\d+/', '', strtoupper($reference));
        $index = 0;

        foreach (str_split($letters) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }

        return max(0, $index - 1);
    }

    protected static function xml(string $content): SimpleXMLElement
    {
        // بدون بارگذاری موجودیت خارجی (XXE)
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET);

        if ($xml === false) {
            throw new RuntimeException('ساختار فایل Excel معتبر نیست.');
        }

        return $xml;
    }
}
