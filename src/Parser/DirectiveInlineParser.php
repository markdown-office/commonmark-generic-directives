<?php

declare(strict_types=1);

namespace MarkdownOffice\CommonMark\Directive\Parser;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use MarkdownOffice\CommonMark\Directive\Directive\DirectiveRegistry;
use MarkdownOffice\CommonMark\Directive\Directive\DirectiveSyntax;
use MarkdownOffice\CommonMark\Directive\DirectiveType;
use MarkdownOffice\CommonMark\Directive\Node\DirectiveInline;

/**
 * Recognises `:name[label]{attrs}` inside a paragraph.
 *
 * The label is stored as text rather than parsed as inline Markdown. CommonMark's inline
 * engine appends into the paragraph it was given and offers no way for a parser to say
 * "the rest of this run is mine", so a parsed label would need a nested parse of its own
 * — and would then be the one construct in the syntax whose behaviour depends on a second
 * pass. Leaf directives keep their label as a string by the RFC anyway, so text is the
 * honest shape for both.
 */
final class DirectiveInlineParser implements InlineParserInterface
{
    public function __construct(private readonly ?DirectiveRegistry $registry = null)
    {
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        // Directive names are case-sensitive: `Note` and `note` being different things is
        // what lets a document say `note` without colliding with a later `Note`.
        return InlineParserMatch::regex(DirectiveSyntax::INLINE_BODY)->caseSensitive();
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();

        // `word:name[...]` is prose, not a directive. Without this, a URL fragment or a
        // `status:error` in a sentence would become a node.
        $previous = $cursor->peek(-1);

        if ($previous !== null && \preg_match('/\w/', $previous) === 1) {
            return false;
        }

        // The tail of a leaf or container fence that leaked into a paragraph is not a text
        // directive; `::` belongs to the block parser.
        if ($cursor->peek(-2) === ':') {
            return false;
        }

        // The engine already ran this pattern and handed back what it captured, so the
        // groups are read rather than matched a second time.
        [, $name, $label, $shorthand, $braces] = $inlineContext->getMatches() + [3 => '', 4 => ''];

        $this->registry?->assertKnown($name, DirectiveType::Text);

        $cursor->advanceBy($inlineContext->getFullMatchLength());

        $inlineContext->getContainer()->appendChild(
            new DirectiveInline($name, DirectiveSyntax::attributes($braces ?: null, $shorthand), $label ?: null)
        );

        return true;
    }
}