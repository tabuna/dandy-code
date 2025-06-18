<?php

namespace Dendy\Book;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;
use Spatie\CommonMarkHighlighter\FencedCodeRenderer;
use Spatie\CommonMarkHighlighter\IndentedCodeRenderer;
use Akh\Typograf\Typograf;
use Vanderlee\Syllable\Syllable;

class MarkdownProcessor
{
    protected  $converter;

    public function __construct()
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new TaskListExtension());
        $environment->addRenderer(FencedCode::class, new FencedCodeRenderer());
        $environment->addRenderer(IndentedCode::class, new IndentedCodeRenderer());

        $this->converter = new MarkdownConverter($environment);
    }

    public function convert(string $markdown, int $index): string
    {
        $html = $this->converter->convert($markdown);
        $html = $this->prepareForPdf($html, $index);

        $typo = $html;

        // TODO: Типограф
        //$typo = (new Typograf())->apply($html);

        Syllable::setCacheDir(__DIR__ . '/runtime');
        $syllable = new Syllable('ru');
        $syllable->setCache(null);
        $syllable->setMinWordLength(2);

        $ru = $syllable->hyphenateHtmlText($typo);

        $syllable->setLanguage('en-us');

        return $syllable->hyphenateHtmlText($ru);
    }

    protected function prepareForPdf(string $html, int $index): string
    {
        if ($index > 1) {
       //     $html = str_replace('<h1>', '[break2]<h1>', $html);
        }

        //$html = str_replace('<h2>', '[break2]<h2>', $html);
        $html = str_replace("<blockquote>\n<p>{notice}", "<blockquote class='notice'><p><strong>Notice:</strong>", $html);
        $html = str_replace("<blockquote>\n<p>{warning}", "<blockquote class='warning'><p><strong>Warning:</strong>", $html);
        $html = str_replace("<blockquote>\n<p>{quote}", "<blockquote class='quote'><p>", $html);

        $html = str_replace(
            ['[break]'],
            ['<div style="page-break-after: always;"></div>'],
            $html
        );

        return $html;
    }
}
