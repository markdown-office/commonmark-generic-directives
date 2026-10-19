<?php

declare(strict_types=1);

namespace MarkdownOffice\CommonMark\Directive\Parser;

use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;
use MarkdownOffice\CommonMark\Directive\Directive\DirectiveSyntax;
use MarkdownOffice\CommonMark\Directive\DirectiveType;
use MarkdownOffice\CommonMark\Directive\Node\DirectiveBlock;

/**
 * Carries one directive block from the line that opened it to the line that closes it.
 *
 * The closing rule is the only interesting part. A container opened with N colons closes
 * on a line of M colons where M >= N — so `:::col` cannot be closed by a stray `::` in
 * its body, and a container opened with four colons stays open over a line of three.
 *
 * A leaf has no body and does not look for a fence. It ends where its line ends, and it
 * ends by returning `null` rather than by reporting itself finished: a block that reports
 * itself finished takes the rest of the line down with it, so a leaf written on the line
 * before a paragraph would swallow that paragraph.
 */
final class DirectiveContinueParser extends AbstractBlockContinueParser
{
    /** @var DirectiveBlock */
    private AbstractBlock $block;

    public function __construct(DirectiveBlock $block, private readonly int $colons = 3)
    {
        $this->block = $block;
    }

    public function getBlock(): AbstractBlock
    {
        return $this->block;
    }

    public function isContainer(): bool
    {
        return $this->block->getType() === DirectiveType::Container;
    }

    public function canHaveLazyContinuationLines(): bool
    {
        return false;
    }

    public function canContain(AbstractBlock $childBlock): bool
    {
        return true;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        if (!$this->isContainer()) {
            return BlockContinue::none();
        }

        $cursor->advanceToNextNonSpaceOrTab();

        if (
            \preg_match(DirectiveSyntax::closingPattern($this->colons), $cursor->getRemainder()) === 1
            && !$this->block->hasOpenNestedContainer()
        ) {
            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }

    public function closeBlock(): void
    {
        if (!$this->isContainer()) {
            return;
        }

        // Tell the container we were nested in that it has one fewer child to wait for,
        // so its own closing fence is honoured again on the next line. The one we were
        // nested in is the nearest ancestor that is a container, which is the one the
        // start parser counted this container against.
        $parent = DirectiveBlock::enclosing($this->block->parent());

        if ($parent !== null) {
            $parent->closeNestedContainer();
        }
    }
}
