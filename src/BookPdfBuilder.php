<?php

namespace Dendy\Book;

namespace Dendy\Book;

use Mpdf\Mpdf;
use Mpdf\Config\FontVariables;
use Mpdf\Config\ConfigVariables;

class BookPdfBuilder
{
    protected Mpdf $pdf;

    public function __construct(array $config = [])
    {
        $defaultConfig = (new ConfigVariables())->getDefaults();
        $defaultFontConfig = (new FontVariables())->getDefaults();

        $default = [
            'mode'          => 'utf-8',
            'fontDir'       => array_merge(
                $defaultConfig['fontDir'],
                [getcwd() . '/assets/fonts']
            ),
            'fontdata'      => $this->prepareFontData($config['fonts'] ?? [], $defaultFontConfig['fontdata']),
            'SHYlang'       => 'ru',
            'justifyB4br'   => false,
            'useKerning'    => true,
        ];

        $config = array_merge($default, $config['document'] ?? []);

        $this->pdf = new Mpdf($config);

        // $this->pdf->setAutoTopMargin = 'pad';
        // $this->pdf->setAutoBottomMargin = 'pad';
        $this->pdf->h2toc = ['H1' => 0, 'H2' => 1];
        $this->pdf->h2bookmarks = ['H1' => 0, 'H2' => 1];
    }

    protected function prepareFontData(array $customFonts, array $defaultFontData): array
    {
        return collect($customFonts)
            ->mapWithKeys(fn($file, $name) => [
                $name => ['R' => $file],
            ])
            ->merge($defaultFontData)
            ->toArray();
    }

    public function withTheme(string $themeHtml): static
    {
        $this->pdf->WriteHTML($themeHtml);
        return $this;
    }

    public function withCover(string $coverHtml): static
    {
        if ($coverHtml !== '') {
            $this->pdf->WriteHTML($coverHtml);
            $this->pdf->AddPage();
        }

        return $this;
    }

    public function addChapter(string $chapterHtml, $break = true): static
    {
        preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $chapterHtml, $matches);
        $title = isset($matches[1])
            ? str_replace('&shy;', '', html_entity_decode(trim($matches[1])))
            : '';

        // 👉 Установить заголовок сверху страницы
        // TODO: Это работает отстойно. нужно переделать
        $this->pdf->SetHTMLHeader("<small style='text-align: center; opacity: 0.5'>$title</small>", 'E', true);

        $this->pdf->WriteHTML("<div class='chapter'>$chapterHtml</div>");

        if ($break) {
            $this->pdf->AddPage();
            $this->pdf->SetHTMLHeader(' ', null, true); // Удаляем заголовок для следующей страницы
        }

        return $this;
    }

    public function setFooter(string $footerHtml): static
    {
        $this->pdf->SetHTMLFooter($footerHtml);
        return $this;
    }

    public function output(string $path): void
    {
        $this->pdf->Output($path);
    }

    public function getPageCount(): int
    {
        return $this->pdf->page;
    }

    public function build(): Mpdf
    {
        return $this->pdf;
    }
}
