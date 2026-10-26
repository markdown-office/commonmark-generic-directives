<?php

declare(strict_types=1);

namespace MarkdownOffice\CommonMark\Directive;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use MarkdownOffice\CommonMark\Directive\Directive\DirectiveRegistry;
use MarkdownOffice\CommonMark\Directive\Node\DirectiveBlock;
use MarkdownOffice\CommonMark\Directive\Node\DirectiveInline;
use MarkdownOffice\CommonMark\Directive\Parser\DirectiveBlockStartParser;
use MarkdownOffice\CommonMark\Directive\Parser\DirectiveInlineParser;
use MarkdownOffice\CommonMark\Directive\Renderer\DirectiveRenderer;

/**
 * The Generic Directives RFC, for league/commonmark 2.x.
 *
 * Three shapes, told apart by how many colons introduce them and by nothing else:
 *
 * ```
 * :badge[PRODUCTION]{type=success}     inline, inside a sentence
 * ::image{src="chart.png"}              a standalone block
 * :::callout{type=info}                 a fenced block …
 * Body parsed as Markdown.              … closed by :::
 * ```
 *
 * With no registry the extension is permissive and parses any well-formed directive,
 * which is what the RFC describes. Given a {@see DirectiveRegistry} it is strict, and an
 * unregistered name raises {@see Exception\UnknownDirective} instead of being ignored —
 * the behaviour worth having when a machine wrote the document.
 *
 * @see https://talk.commonmark.org/t/generic-directives-plugins-syntax/444
 */
final class GenericDirectiveExtension implements ExtensionInterface
{
    public function __construct(
        private readonly ?DirectiveRegistry $registry = null,
        private readonly AttributeNaming $naming = AttributeNaming::Namespaced,
    ) {
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        // Default priority on purpose. A `:::` inside a fenced code block stays code
        // because CommonMark does not look for new block starts inside a block that is
        // not a container, not because of where this parser sits in the order — raising
        // it to run first changes nothing for any input, which is worth knowing before
        // someone adds a priority here expecting it to matter.
        $environment->addBlockStartParser(new DirectiveBlockStartParser($this->registry));
        $environment->addInlineParser(new DirectiveInlineParser($this->registry));

        $renderer = new DirectiveRenderer($this->naming);
        $environment->addRenderer(DirectiveBlock::class, $renderer);
        $environment->addRenderer(DirectiveInline::class, $renderer);
    }
}
