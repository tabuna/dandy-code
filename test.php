<?php

function extractMarkdownHeadings(string $directory): array
{
    $headings = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'md') {
            $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);
            foreach ($lines as $line) {
                if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches)) {
                    $headings[] = [
                        'file'  => $file->getPathname(),
                        'level' => strlen($matches[1]),
                        'text'  => $matches[2],
                    ];
                }
            }
        }
    }

    // Сортировка по имени файла
    usort($headings, function ($a, $b) {
        return strcmp($a['file'], $b['file']);
    });

    return $headings;
}

// Пример использования:
$directory = __DIR__.'/content'; // укажи путь к каталогу с md файлами
$headings = extractMarkdownHeadings($directory);

// Вывод
foreach ($headings as $heading) {

    if ($heading['level'] < 2) {
        // continue;
    }

    echo sprintf(
        "[%s] %s %s\n",
        // $heading['file'],
        '',
        str_repeat('#', $heading['level']),
        $heading['text']
    );
}
