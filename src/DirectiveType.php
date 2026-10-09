<?php

declare(strict_types=1);

namespace MarkdownOffice\CommonMark\Directive;

/**
 * The three directive shapes of the Generic Directives RFC, distinguished only by how
 * many colons introduce them.
 *
 * The colon count is the whole difference, and it is what makes the syntax readable at a
 * glance: `:x` sits inside a sentence, `::x` stands alone on its line, `:::x` opens a
 * fenced block. There is no other keyword and no other marker to learn.
 */
enum DirectiveType: string
{
    /** `:name[label]{attrs}` — sits inside a paragraph. */
    case Text = 'text';

    /** `::name[label]{attrs}` — a standalone block with no body. */
    case Leaf = 'leaf';

    /** `:::name{attrs}` … `:::` — a fenced block that may contain anything. */
    case Container = 'container';

    /**
     * The shortest run of colons that introduces this type.
     *
     * A container may be opened with four or more colons so that a body containing a
     * three-colon fence still closes correctly; see {@see self::closingColons()}.
     */
    public function minimumColons(): int
    {
        return match ($this) {
            self::Text => 1,
            self::Leaf => 2,
            self::Container => 3,
        };
    }

    /**
     * @param int $colons how many colons were actually used
     */
    public static function fromColons(int $colons): self
    {
        return match (true) {
            $colons >= 3 => self::Container,
            $colons === 2 => self::Leaf,
            default => self::Text,
        };
    }

    public function isBlock(): bool
    {
        return $this !== self::Text;
    }
}