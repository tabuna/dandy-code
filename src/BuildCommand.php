<?php

namespace Dendy\Book;

use Dendy\Book\BookPdfBuilder;
use Dendy\Book\MarkdownProcessor;
use SplFileInfo;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BuildCommand extends Command
{
    /**
     * @var Filesystem
     */
    private Filesystem $disk;


    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('build')
            ->setDescription('Generate the book.');
    }

    /**
     * Execute the command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @throws \Mpdf\MpdfException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->disk = new Filesystem();

        $currentPath = getcwd();
        $config = require $currentPath . '/config.php';

        $output->writeln('<fg=yellow>==></> Preparing Export Directory ...');
        $this->ensureExportDirectoryExists($currentPath);

        $theme = $this->getTheme($currentPath);

        $pdf = (new BookPdfBuilder($config))
            ->withCover($this->cover($currentPath, $config))
            ->withTheme($theme)
            ->setFooter('<div id="footer" style="text-align: center">{PAGENO}</div>');

        $output->writeln('<fg=yellow>==></> Building PDF page by page ...');

        $files = collect($this->disk->files($currentPath . '/content'))
            ->filter(fn(SplFileInfo $file) => $file->getExtension() === 'md')
            ->values();

        $processor = new MarkdownProcessor();

        foreach ($files as $index => $file) {

            $html = $processor->convert(
                $this->disk->get($file->getPathname()),
                $index + 1
            );

            // Добавляем страницу, кроме последней
            $pdf->addChapter($html, $index < $files->count() - 1);
        }

        $output->writeln('<fg=yellow>==></> Writing PDF To Disk ...');
        $output->writeln('');
        $output->writeln('✨✨ ' . $pdf->getPageCount() . ' PDF pages ✨✨');

        $pdf->Output(
            $currentPath . '/export/book.pdf'
        );

        $output->writeln('<info>Book Built Successfully!</info>');

        return Command::SUCCESS;
    }

    /**
     * @param string $currentPath
     */
    protected function ensureExportDirectoryExists(string $currentPath): void
    {
        if (!$this->disk->isDirectory($currentPath . '/export')) {
            $this->disk->makeDirectory(
                $currentPath . '/export',
                0755,
                true
            );
        }
    }

    /**
     * Возвращает HTML для обложки или пустую строку, если обложки нет.
     *
     * @param string $currentPath
     * @param array  $config
     *
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function cover(string $currentPath, array $config): string
    {
        if ($this->disk->isFile($currentPath . '/assets/cover.jpg')) {
            $coverPosition = $config['cover']['position'] ?? 'position: absolute; left:0; right: 0; top: -.2; bottom: 0;';
            $coverDimensions = $config['cover']['dimensions'] ?? 'width: 148mm; height: 210mm; margin: 0;';

            return <<<HTML
<div style="{$coverPosition}">
    <img src="assets/cover.jpg" style="{$coverDimensions}"/>
</div>
HTML;
        }

        if ($this->disk->isFile($currentPath . '/assets/cover.html')) {
            return $this->disk->get($currentPath . '/assets/cover.html');
        }

        return '';
    }

    /**
     * @param        $currentPath
     * @param string $themeName
     *
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function getTheme($currentPath, string $themeName = 'theme'): string
    {
        return $this->disk->get($currentPath . "/assets/$themeName.html");
    }

    /**
     * @param $config
     * @param $fontData
     *
     * @return array
     */
    protected function fonts($config, $fontData): array
    {
        return $fontData + collect($config['fonts'] ?? [])->mapWithKeys(function ($file, $name) {
                return [
                    $name => [
                        'R' => $file,
                    ],
                ];
            })->toArray();
    }
}
