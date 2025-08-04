<?php

namespace Dandy\Book;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

class Book
{
    /**
     * PDF document instance
     */
    protected Mpdf $pdf;

    /**
     * Custom fonts to register
     */
    protected array $customFonts = [];

    /**
     * Create PDF with default configuration
     */
    public function __construct(array $config = [])
    {
        $this->pdf = new Mpdf(
            array_merge($this->defaultConfig(), $config['document'] ?? [])
        );
        $this->pdf->debug = true;

        $this->initializePdf();
    }

    /**
     * Set title
     */
    public function withTitle(string $title): static
    {
        $this->pdf->SetTitle($title);

        return $this;
    }

    /**
     * Set author
     */
    public function withAuthor(string $author): static
    {
        $this->pdf->SetAuthor($author);
        $this->pdf->SetCreator($author);

        return $this;
    }

    /**
     * Set custom fonts
     */
    public function withFonts(array $fonts): static
    {
        $this->customFonts = $fonts;

        return $this;
    }

    /**
     * Apply theme HTML
     */
    public function withTheme(string $themeHtml): static
    {
        $this->pdf->WriteHTML($themeHtml);

        return $this;
    }

    /**
     * Apply theme HTML
     */
    public function withColophon(string $html): static
    {
        $this->pdf->WriteHTML(
            Str::of($html)
                ->replace('[commit]', self::getCurrentGitCommitHash())
                ->replace('[year]', Date::now()->format('Y'))
                ->toString()
        );

        return $this;
    }

    /**
     * Add cover page
     */
    public function withCover(string $coverHtml): static
    {
        if (! empty($coverHtml)) {
            $this->pdf->WriteHTML($coverHtml);
            $this->addPageBreak();
        }

        return $this;
    }

    /**
     * Add chapter with title header and optional page break
     */
    public function chapter(string $chapterHtml, bool $break = true): static
    {
        $title = $this->extractTitle($chapterHtml);

        if ($title) {
            $slug = Str::slug($title);

            $this->pdf->defHTMLHeaderByName($slug, <<<HTML
                <span style="text-align: center; color: #817d7d; font-size: 10px">
                    Dendy Code | $title
                </span>
            HTML);

            $this->pdf->WriteHTML("<setpageheader name=\"{$slug}\" page=\"E\" value=\"on\" />");
        }

        $this->pdf->WriteHTML("<div class='chapter'>{$chapterHtml}</div>");

        if ($break) {
            $this->addPageBreak();
        }

        return $this;
    }

    /**
     * Set footer HTML
     */
    public function setFooter(string $footerHtml): static
    {
        $this->pdf->SetHTMLFooter($footerHtml);

        return $this;
    }

    /**
     * Save PDF to file
     */
    public function output(string $path): void
    {
        $this->pdf->Output($path);
    }

    /**
     * Get total page count
     */
    public function getPageCount(): int
    {
        return $this->pdf->page;
    }

    /**
     * Return the underlying Mpdf instance
     */
    public function build(): Mpdf
    {
        return $this->pdf;
    }

    /**
     * Default PDF configuration array
     */
    protected function defaultConfig(): array
    {
        $baseConfig = (new ConfigVariables())->getDefaults();
        $fontConfig = (new FontVariables())->getDefaults();

        return [
            'mode'        => 'utf-8',
            'fontDir'     => array_merge($baseConfig['fontDir'], [getcwd().'/assets/fonts']),
            'fontdata'    => $this->prepareFontData($fontConfig['fontdata']),
            'SHYlang'     => 'ru',
            'justifyB4br' => false,
            'useKerning'  => true,
            'h2toc'       => ['H1' => 0, 'H2' => 1],
            'h2bookmarks' => ['H1' => 0, 'H2' => 1],
        ];
    }

    /**
     * Prepare font data by merging custom fonts with defaults
     */
    protected function prepareFontData(array $defaultFontData): array
    {
        return collect($defaultFontData)
            ->merge(
                collect($this->customFonts)->mapWithKeys(fn ($file, $name) => [
                    $name => ['R' => $file],
                ])
            )
            ->toArray();
    }

    /**
     * Initialize PDF document settings
     */
    protected function initializePdf(): void
    {
        $this->pdf->h2toc = ['H1' => 0, 'H2' => 1];
        $this->pdf->h2bookmarks = ['H1' => 0, 'H2' => 1];
        $this->pdf->defHTMLHeaderByName('[clear]', '');
        $this->pdf->setAutoTopMargin = 'pad';
        $this->pdf->setAutoBottomMargin = 'pad';
        $this->pdf->use_kwt = true;
    }

    /**
     * Extract the first H1 title from chapter HTML
     */
    protected function extractTitle(string $html): ?string
    {
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
            return trim(
                str_replace('&shy;', '', html_entity_decode($matches[1]))
            );
        }

        return null;
    }

    /**
     * Insert a page break into the PDF
     */
    protected function addPageBreak(): void
    {
        // $this->pdf->AddPage();
        $this->pdf->WriteHTML('<div style="page-break-after: always;"></div>');
    }

    /**
     * Attempt to retrieve the current Git commit hash in PHP.
     * Note: This method assumes the project is using Git for version control.
     *
     * @return string|null
     */
    public static function getCurrentGitCommitHash(): ?string
    {
        $gitPath = '.git/';

        if (! file_exists($gitPath)) {
            return null;
        }

        $head = trim(substr(file_get_contents($gitPath.'HEAD'), 4));

        return Str::of(file_get_contents($gitPath.$head))->trim()->limit(7, '');
    }
}
