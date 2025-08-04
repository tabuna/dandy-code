<?php

namespace Dandy\Book;

use Illuminate\Filesystem\Filesystem;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
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
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @throws \Mpdf\MpdfException
     *
     * @return int
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->disk = new Filesystem();

        $currentPath = getcwd();
        $config = require $currentPath.'/config.php';

        $output->writeln('<fg=yellow>==></> Preparing Export Directory ...');
        $this->ensureExportDirectoryExists($currentPath);

        $theme = $this->getTheme($currentPath);

        $pdf = (new Book($config))
            ->withCover($this->cover($currentPath, $config))
            ->withCover($this->cover($currentPath, $config, 'cover-back'))
            ->withColophon(file_get_contents($currentPath.'/assets/colophon.html'))
            ->withTheme($theme)
            ->withTitle($config['title'])
            ->withAuthor($config['author'])
            ->setFooter('<div id="footer" style="text-align: center">{PAGENO}</div>');

        $files = collect($this->disk->files($currentPath.'/content'))
            ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'md')
            ->values();

        $processor = new MarkdownProcessor();

        $progressBar = new ProgressBar($output, $files->count());
        $progressBar->start();

        foreach ($files as $index => $file) {

            $html = $processor->convert(
                $this->disk->get($file->getPathname()),
                $index + 1
            );

            // Добавляем страницу, кроме последней
            $pdf->chapter($html, $index < $files->count() - 1);

            $progressBar->advance();
        }

        $progressBar->finish();

        $pdfFilePath = sprintf('%s/export/%s.pdf', $currentPath, $config['title']);
        $pdf->Output($pdfFilePath);

        $output->writeln('');
        $output->writeln(
            sprintf('<fg=yellow>==></> Writing %s PDF Pages To Disk ...', $pdf->getPageCount())
        );

        // Создаем кликабельную ссылку для поддерживающих терминалов
        $output->writeln(sprintf(
            ' <href=file://%s>📄 Click to open: %s</>',
            $pdfFilePath,
            $pdfFilePath
        ));

        $output->writeln('<info>Book Built Successfully!</info>');

        return Command::SUCCESS;
    }

    /**
     * @param string $currentPath
     */
    protected function ensureExportDirectoryExists(string $currentPath): void
    {
        if (! $this->disk->isDirectory($currentPath.'/export')) {
            $this->disk->makeDirectory(
                $currentPath.'/export',
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
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     * @return string
     */
    protected function cover(string $currentPath, array $config, string $filename = 'cover'): string
    {
        $jpgPath = $currentPath.'/assets/'.$filename.'.jpg';
        $htmlPath = $currentPath.'/assets/'.$filename.'.html';

        if ($this->disk->isFile($jpgPath)) {
            $coverPosition = $config['cover']['position'] ?? 'position: absolute; left:0; right: 0; top: -.2; bottom: 0;';
            $coverDimensions = $config['cover']['dimensions'] ?? 'width: 148mm; height: 210mm; margin: 0;';

            return <<<HTML
<div style="{$coverPosition}">
    <img src="assets/{$filename}.jpg" style="{$coverDimensions}"/>
</div>
HTML;
        }

        if ($this->disk->isFile($htmlPath)) {
            return $this->disk->get($htmlPath);
        }

        return '';
    }

    /**
     * @param        $currentPath
     * @param string $themeName
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     * @return string
     */
    private function getTheme($currentPath, string $themeName = 'theme'): string
    {
        return $this->disk->get($currentPath."/assets/$themeName.html");
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
