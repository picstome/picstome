<?php

namespace App\Livewire\Forms\Concerns;

use League\HTMLToMarkdown\HtmlConverter;

/**
 * Converts the Trix HTML body of a contract into stored Markdown.
 */
trait ConvertsBodyToMarkdown
{
    protected function convertBodyToMarkdown(): string
    {
        return (new HtmlConverter(['header_style' => 'atx', 'strip_tags' => true]))
            ->convert($this->body);
    }
}
