<?php

namespace app\services;

use app\services\copyformat\DeltaParser;
use app\services\copyformat\FormatWriterInterface;
use app\services\copyformat\HtmlWriter;
use app\services\copyformat\LlmXmlWriter;
use app\services\copyformat\MarkdownWriter;
use app\services\copyformat\PlainTextWriter;
use app\services\copyformat\QuillDeltaWriter;
use common\enums\CopyType;

class CopyFormatConverter
{
    private DeltaParser $parser;
    private MarkdownWriter $markdownWriter;
    private HtmlWriter $htmlWriter;
    private PlainTextWriter $plainTextWriter;
    private LlmXmlWriter $llmXmlWriter;
    private QuillDeltaWriter $quillDeltaWriter;

    public function __construct(
        ?DeltaParser $parser = null,
        ?MarkdownWriter $markdownWriter = null,
        ?HtmlWriter $htmlWriter = null,
        ?PlainTextWriter $plainTextWriter = null,
        ?LlmXmlWriter $llmXmlWriter = null,
        ?QuillDeltaWriter $quillDeltaWriter = null
    ) {
        $this->parser = $parser ?? new DeltaParser();
        $this->markdownWriter = $markdownWriter ?? new MarkdownWriter();
        $this->htmlWriter = $htmlWriter ?? new HtmlWriter();
        $this->plainTextWriter = $plainTextWriter ?? new PlainTextWriter();
        $this->llmXmlWriter = $llmXmlWriter ?? new LlmXmlWriter();
        $this->quillDeltaWriter = $quillDeltaWriter ?? new QuillDeltaWriter();
    }

    public function convertFromQuillDelta(string $content, CopyType $type): string
    {
        $delta = $this->parser->decode($content);
        if ($delta === null) {
            return '';
        }

        $blocks = $this->parser->buildBlocks($delta['ops'] ?? []);

        return match ($type) {
            CopyType::MD => $this->markdownWriter->writeFromBlocks($blocks),
            CopyType::TEXT => $this->plainTextWriter->writeFromBlocks($blocks),
            CopyType::HTML => $this->htmlWriter->writeFromBlocks($blocks),
            CopyType::LLM_XML => $this->llmXmlWriter->writeFromBlocks($blocks),
            CopyType::QUILL_DELTA => $this->parser->encode($delta),
        };
    }

    public function convertFromHtml(string $content, CopyType $type): string
    {
        return match ($type) {
            CopyType::MD => $this->markdownWriter->writeFromHtml($content),
            CopyType::TEXT => $this->plainTextWriter->writeFromHtml($content),
            CopyType::HTML, CopyType::QUILL_DELTA => $content,
            CopyType::LLM_XML => $this->llmXmlWriter->writeFromHtml($content),
        };
    }

    public function convertFromPlainText(string $content, CopyType $type): string
    {
        return match ($type) {
            CopyType::MD, CopyType::TEXT, CopyType::QUILL_DELTA => trim($content),
            CopyType::HTML => $this->htmlWriter->writeFromPlainText($content),
            CopyType::LLM_XML => $this->llmXmlWriter->writeFromPlainText($content),
        };
    }

    public function getWriter(CopyType $type): FormatWriterInterface
    {
        return match ($type) {
            CopyType::MD => $this->markdownWriter,
            CopyType::TEXT => $this->plainTextWriter,
            CopyType::HTML => $this->htmlWriter,
            CopyType::LLM_XML => $this->llmXmlWriter,
            CopyType::QUILL_DELTA => $this->quillDeltaWriter,
        };
    }
}
