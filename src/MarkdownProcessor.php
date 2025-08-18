<?php

namespace Dandy\Book;

use Illuminate\Support\Str;
use JoliTypo\Fixer;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Component\DomCrawler\Crawler;
use Tempest\Highlight\CommonMark\HighlightExtension;
use Tempest\Highlight\Highlighter;
use Tempest\Highlight\Themes\InlineTheme;
use Vanderlee\Syllable\Syllable;

class MarkdownProcessor
{
    protected MarkdownConverter $converter;

    /**
     * Initialize the Markdown converter with required extensions and renderers.
     */
    public function __construct()
    {
        $environment = new Environment();

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new TaskListExtension());

        // $environment->addExtension(new PhikiExtension(Theme::GithubLight));

        $highlighter = new Highlighter(new InlineTheme(__DIR__.'/../assets/hightlight.css'));
        $environment->addExtension(new HighlightExtension($highlighter));

        /*
        $environment->addRenderer(FencedCode::class, new FencedCodeRenderer());

        $environment->addRenderer(IndentedCode::class, new IndentedCodeRenderer());
*/
        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Convert markdown into PDF-ready HTML with typography and hyphenation.
     */
    public function convert(string $markdown, int $index): string
    {
        $html = $this->converter->convert($markdown);
        $html = $this->prepareForPdf($html, $index);

        $fixer = new Fixer([
            'Ellipsis',
            'Dimension',
            'Unit',
            'Dash',
            'SmartQuotes',
            'NoSpaceBeforeComma',
            'CurlyQuote',
            'Trademark',
        ]);

        $fixer->setLocale('ru');

        $crawler = new Crawler($html);

        $crawler
            ->filter('p,li')
            ->each(function (Crawler $elm) use ($fixer, &$html) {

                $content = $elm->html();

                $paragraph = $fixer->fix($content);

                $html = Str::of($html)->replace($content, $paragraph);

                $t = new \Akh\Typograf\Typograf();
                $html = $t->apply($html);
            });

        $crawler
            ->filter('p,li')
            ->each(function (Crawler $elm) use (&$html) {

                $content = $elm->html();

                $paragraph = $this->preventShortWordOrphans($content, 4);

                $html = Str::of($html)->replace($content, $paragraph);
            });

        $syllable = new Syllable('ru');
        $syllable->setCache(null);
        $syllable->setMinWordLength(2);

        $html = $syllable->hyphenateHtmlText($html);

        $syllable->setLanguage('en-us');

        return $syllable->hyphenateHtmlText($html);

    }

    protected function preventShortWordOrphans(string $text, int $maxLength = 2): string
    {
        // Регулярка ищет слова длиной <= maxLength перед пробелом или переносом строки
        // \b — граница слова, \w{1,maxLength} — слово длиной 1 до maxLength букв
        // \s+ — пробелы после слова
        $pattern = '/\b(\w{1,'.$maxLength.'})\s+/u';

        // Заменяем пробелы на неразрывный пробел
        $replacement = '$1&nbsp;';

        return preg_replace($pattern, $replacement, $text);
    }

    protected function wrapHeadersWithParagraphs(string $html): string
    {
        return Str::of($html)->replaceMatches(
            '/<(h[1-6])([^>]*)>(.*?)<\/\1>\s*<p([^>]*)>(.*?)<\/p>/is',
            function ($match) {
                $headingTag = $match[1];
                $headingAttr = $match[2];
                $headingContent = $match[3];
                $pAttr = $match[4];
                $pContent = $match[5];

                return '<div keep_block_together>'
                    ."<$headingTag$headingAttr>$headingContent</$headingTag>"
                    ."<p$pAttr>$pContent</p>"
                    .'</div>';
            }
        )
            ->toString();
    }

    /**
     * Prepare HTML for PDF rendering (styling, breaks, and custom blocks).
     */
    protected function prepareForPdf(string $html, int $index): string
    {
        $html = $this->wrapHeadersWithParagraphs($html);


            $html = str_replace('<h1>', <<<'HTML'
<span style="display: block;"></span>
<div class="chapter-padding">
    <table class="chapter-table">
        <tr class="chapter-row">
            <td class="chapter-icon-cell">&gt;_</td>
            <td class="chapter-cell"></td>
            <td class="chapter-cell"></td>
        </tr>
    </table>
</div>
<h1>
HTML, $html);

        $html = str_replace([
            "<blockquote>\n<p>{notice}",
            "<blockquote>\n<p>{warning}",
            "<blockquote>\n<p>{quote}",
            '[break]',
        ], [
            "<blockquote class='notice'><p>",
            "<blockquote class='warning'><p>",
            "<blockquote class='quote'><p>",
            '<div style="page-break-after: always;"></div>',
        ], $html);

        return $html;
    }
}
