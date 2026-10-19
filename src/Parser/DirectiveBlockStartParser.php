<?php

declare(strict_types=1);

namespace MarkdownOffice\CommonMark\Directive\Parser;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;
use MarkdownOffice\CommonMark\Directive\Directive\DirectiveRegistry;
use MarkdownOffice\CommonMark\Directive\Directive\DirectiveSyntax;
use MarkdownOffice\CommonMark\Directive\DirectiveType;
use MarkdownOffice\CommonMark\Directive\Node\DirectiveBlock;

/**
 * Recognises `::leaf` and `:::container` at the start of a line.
 *
 * Registered behind the CommonMark block parsers so that a fenced code block wins: a
 * `:::` inside a fence is code, and only code, and an author debugging their template
 * should not have to think about which parser claimed their fence first.
 */
final class DirectiveBlockStartParser implements BlockStartParserInterface
{
    public function __construct(private readonly ?DirectiveRegistry $registry = null)
    {
    }

    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        // Four spaces of indent is a code block in CommonMark, not a directive.
        if ($cursor->isIndented()) {
            return BlockStart::none();
        }

        $cursor->advanceToNextNonSpaceOrTab();
        $match = DirectiveSyntax::matchBlock($cursor->getRemainder());

        if ($match === null) {
            return BlockStart::none();
        }

        [, $colons, $name, $label, $shorthand, $braces] = $match;

        $type = DirectiveType::fromColons($colons);
        $this->registry?->assertKnown($name, $type);

        $block = new DirectiveBlock(
            $name,
            $type,
            DirectiveSyntax::attributes($braces, $shorthand),
            $label
        );

        // The whole line belongs to the directive; nothing after it is body.
        $cursor->advanceToEnd();

        if ($type === DirectiveType::Container) {
            // CommonMark closes a container and everything above it together, so a parent
            // has to know it has a child to wait for before it honours its own fence.
            DirectiveBlock::enclosing($parserState->getActiveBlockParser()->getBlock())
                ?->openNestedContainer();
        }

        return BlockStart::of(new DirectiveContinueParser($block, $colons))->at($cursor);
    }
}
