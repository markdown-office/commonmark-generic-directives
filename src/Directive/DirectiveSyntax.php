<?php

declare(strict_types=1);

namespace MarkdownOffice\CommonMark\Directive\Directive;

/**
 * The one place the directive grammar is written down as a pattern.
 *
 * Both the block and the inline parser need "colons, then a name, then an optional
 * label, then optional shorthands, then optional attributes". Keeping that in one class
 * means a change to what counts as a directive name cannot land in one parser and not
 * the other — which is the failure that makes a syntax extension feel like it parses
 * differently in a heading than in a paragraph.
 */
final class DirectiveSyntax
{
    /**
     * A directive name: a letter, then letters, digits, `_` and `-`.
     *
     * A leading letter rather than a digit is what keeps `ratio:3:2` and a clock time out
     * of the syntax, and requiring the name to start with a letter is also what the RFC
     * asks for.
     */
    public const NAME = '[A-Za-z][A-Za-z0-9_-]*';

    /**
     * An attribute block, capturing so a caller can read it back out, and allowing braces
     * inside quoted values so `{title="a } b"}` is one attribute rather than an error.
     */
    public const ATTRIBUTES = '(\{(?:[^{}"\']|"[^"]*"|\'[^\']*\')*\})?';

    /**
     * A label. Brackets do not nest, so a `[` inside a label has no closing partner and
     * the directive is left as literal text — which is the recoverable outcome.
     */
    public const LABEL = '(?:\[([^\]\[]*)\])?';

    /**
     * A run of bare shorthands.
     *
     * `:::note#lead.terse` and `:::note{#lead .terse}` mean the same thing. An author who
     * has seen one form should not have to know that only the other one is valid.
     */
    public const SHORTHAND = '((?:[#.][A-Za-z0-9_-]+)*)';

    /**
     * A text directive as a bare pattern: no delimiters, no anchors, no flags.
     *
     * {@see \League\CommonMark\Parser\Inline\InlineParserMatch} wants exactly this shape,
     * and wants it to come from the same place as the block form so the two cannot drift
     * into accepting different names.
     *
     * `(?!:)` keeps `::` out. A leaf directive written on its own line is a block, and
     * matching it here as well would let the two parsers disagree about three characters.
     */
    public const INLINE_BODY = ':(?!:)(' . self::NAME . ')' . self::LABEL . self::SHORTHAND . self::ATTRIBUTES;

    /** The whole of a text directive. */
    public const INLINE_PATTERN = '/' . self::INLINE_BODY . '/';

    /**
     * The whole of a block directive on one line.
     *
     * Group 1 is the colon run, 2 the name, 3 the label without brackets, 4 the bare
     * shorthands, 5 the attributes with braces.
     */
    public const BLOCK_PATTERN = '/\A(:{2,})(' . self::NAME . ')' . self::LABEL . self::SHORTHAND . self::ATTRIBUTES . '[ \t]*\z/';

    /**
     * The closing fence of a container opened with `$colons` colons.
     *
     * At least as many colons as the opening fence, so a body that shows the author how to
     * nest a three-colon container can open one with four and still close its own.
     */
    public static function closingPattern(int $colons): string
    {
        return '/\A:{' . $colons . ',}[ \t]*\z/';
    }

    /**
     * @return array{0: string, 1: int, 2: string, 3: string|null, 4: string, 5: string|null}|null
     */
    public static function matchBlock(string $line): ?array
    {
        if (\preg_match(self::BLOCK_PATTERN, $line, $m) !== 1) {
            return null;
        }

        // Groups 3 and 4 are optional yet always present once the pattern matched: an
        // optional group that did not participate holds an empty string rather than a
        // missing key. Group 5 is the exception — PHP drops trailing groups that did not
        // participate, and it cannot match an empty string because it is at least `{}`.
        return [
            $m[0],
            \strlen($m[1]),
            $m[2],
            $m[3] === '' ? null : $m[3],
            $m[4],
            $m[5] ?? null,
        ];
    }

    /**
     * Merges the two places attributes can be written into one set.
     *
     * The braces win over a bare shorthand of the same name, because they are the more
     * explicit of the two.
     */
    public static function attributes(?string $braces, string $shorthand = ''): DirectiveAttributes
    {
        $values = DirectiveAttributes::parse($shorthand);

        if ($braces !== null && $braces !== '') {
            $values = $values->mergedWith(DirectiveAttributes::parse(\substr($braces, 1, -1)));
        }

        return $values;
    }
}
