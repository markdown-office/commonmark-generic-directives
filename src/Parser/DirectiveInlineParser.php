<?php

declare(strict_types=1);

namespace MarkdownOffice\CommonMark\Directive\Parser;

use League\CommonMark\Environment\EnvironmentAwareInterface;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\AdjacentTextMerger;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use League\CommonMark\Parser\InlineParserEngine;
use MarkdownOffice\CommonMark\Directive\Directive\DirectiveRegistry;
use MarkdownOffice\CommonMark\Directive\Directive\DirectiveSyntax;
use MarkdownOffice\CommonMark\Directive\DirectiveType;
use MarkdownOffice\CommonMark\Directive\Node\DirectiveInline;

/**
 * Recognises `:name[label]{attrs}` inside a paragraph.
 *
 * The label is parsed as inline Markdown, so `:badge[**Ship it**]` carries a bold run and
 * not four asterisks. The RFC says the content of an inline directive "may be further inline
 * markdown elements to be interpreted".
 *
 * The parse is a second pass: CommonMark's inline engine appends into the paragraph it was
 * given and offers no way for a parser to say "the rest of this run is mine", so the label
 * is parsed on its own and the nodes it makes are moved into the directive.
 *
 * league/commonmark has no public way to run an inline parse over a string:
 * `InlineParserEngine` is the one that does it, and league marks it `@internal` — as it
 * marks the `AdjacentTextMerger` that goes with it. The suite's presentation half already
 * uses the first for the same thing.
 */
final class DirectiveInlineParser implements InlineParserInterface, EnvironmentAwareInterface
{
    /**
     * The environment the label is parsed with.
     *
     * The environment hands it over when the parser is registered, and a parser that is not
     * registered is never asked to parse.
     */
    private EnvironmentInterface $environment;

    /**
     * Whether a label is being parsed right now.
     *
     * A directive written in a label is content rather than a directive: it could carry no
     * label of its own and would render as an empty span, and the registry would be asked
     * about a name the author wrote as prose.
     */
    private bool $parsingLabel = false;

    public function __construct(private readonly ?DirectiveRegistry $registry = null)
    {
    }

    public function setEnvironment(EnvironmentInterface $environment): void
    {
        $this->environment = $environment;
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        // The registry decides which directive a name means, and it folds the case. The
        // name is a character class, so the flag has nothing to fold: what keeps the two
        // parsers in step is the `DirectiveSyntax::NAME` they share. The flag stays
        // because a match is case-insensitive unless it says otherwise.
        return InlineParserMatch::regex(DirectiveSyntax::INLINE_BODY)->caseSensitive();
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        if ($this->refusesTheMatch($inlineContext)) {
            return false;
        }

        $cursor = $inlineContext->getCursor();

        // The engine already ran this pattern and handed back what it captured, so the
        // groups are read rather than matched a second time.
        [, $name, $label, $shorthand, $braces] = $inlineContext->getMatches() + [3 => '', 4 => ''];

        $this->registry?->assertKnown($name, DirectiveType::Text);

        $cursor->advanceBy($inlineContext->getFullMatchLength());

        $node = new DirectiveInline($name, DirectiveSyntax::attributes($braces ?: null, $shorthand), $label ?: null);

        $inlineContext->getContainer()->appendChild($node);

        if ($label !== null && $label !== '') {
            $this->parseLabel($node, $label, $inlineContext);
        }

        return true;
    }

    /**
     * The three cases in which what looks like a directive is not one.
     *
     * Each is a reason of its own, so they are named rather than folded into one
     * condition.
     */
    private function refusesTheMatch(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();

        // A directive inside a label is content, not a directive.
        $inLabel = $this->parsingLabel;

        // `word:name[...]` is prose, not a directive. Without this, a URL fragment or a
        // `status:error` in a sentence would become a node.
        $previous = $cursor->peek(-1);
        $afterWord = $previous !== null && \preg_match('/\w/', $previous) === 1;

        // The tail of a leaf or container fence that leaked into a paragraph is not a text
        // directive; `::` belongs to the block parser. `(?!\:)` in `INLINE_BODY` has already
        // refused the first colon of a pair, so this has to refuse the second — and the cursor
        // sits on the colon being matched, which is what `peek(-1)` reads. One further back is
        // the colon before a space, which refused `Note: :badge[x]`: prose, on its way in.
        $afterColon = $cursor->peek(-1) === ':';

        return $inLabel || $afterWord || $afterColon;
    }

    private function parseLabel(DirectiveInline $node, string $label, InlineParserContext $inlineContext): void
    {
        $paragraph = new Paragraph();

        $this->parsingLabel = true;

        try {
            // The document's reference map, so that a label is parsed in the document it
            // was written in.
            (new InlineParserEngine($this->environment, $inlineContext->getReferenceMap()))->parse($label, $paragraph);
        } finally {
            $this->parsingLabel = false;
        }

        $this->writeHtmlAsText($paragraph);

        // The constructor put the label there as text; the parsed form replaces it.
        $node->detachChildren();

        foreach ($paragraph->children() as $child) {
            $node->appendChild($child);
        }
    }

    /**
     * The raw HTML in a label, written as the text it is.
     *
     * A label is Markdown, and the package does not let an author's text become markup in
     * one — the rule the leaf's label has always had. An autolink is not raw HTML and
     * stays the link it is.
     */
    private function writeHtmlAsText(Node $node): void
    {
        foreach (\iterator_to_array($node->children()) as $child) {
            if ($child instanceof HtmlInline) {
                $child->replaceWith(new Text($child->getLiteral()));

                continue;
            }

            $this->writeHtmlAsText($child);
        }

        // The demotion leaves text beside text where the engine had one run.
        AdjacentTextMerger::mergeChildNodes($node);
    }
}
