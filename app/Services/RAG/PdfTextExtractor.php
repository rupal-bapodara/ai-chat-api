<?php

namespace App\Services\RAG;

use RuntimeException;
use Spatie\PdfToText\Pdf;
use Symfony\Component\Process\Process;

/**
 * Extracts text per real PDF page by shelling out to poppler's pdftotext
 * (-layout keeps reading order, -f/-l select one page) and pdfinfo.
 */
class PdfTextExtractor
{
    /**
     * @return array<int, string> page number (1-based) => text
     */
    public function extractPages(string $absolutePath): array
    {
        if (! is_readable($absolutePath)) {
            throw new RuntimeException('Document file could not be found on disk.');
        }

        $binary = (string) config('rag.pdftotext_path', '/usr/bin/pdftotext');
        $pages = [];

        foreach (range(1, $this->pageCount($absolutePath)) as $page) {
            $text = Pdf::getText($absolutePath, $binary, ['layout', "f {$page}", "l {$page}"]);

            if (trim($text) !== '') {
                $pages[$page] = $text;
            }
        }

        return $pages;
    }

    public function pageCount(string $absolutePath): int
    {
        $process = new Process([(string) config('rag.pdfinfo_path', '/usr/bin/pdfinfo'), $absolutePath]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()
            || ! preg_match('/^Pages:\s+(\d+)/m', $process->getOutput(), $matches)) {
            throw new RuntimeException('Could not read PDF page count: ' . trim($process->getErrorOutput()));
        }

        return max(1, (int) $matches[1]);
    }
}
