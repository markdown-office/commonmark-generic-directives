<?php

declare(strict_types=1);

namespace MarkdownOffice\CommonMark\Directive\Directive;

use MarkdownOffice\CommonMark\Directive\DirectiveType;

/**
 * The one place the directive grammar is written down as a pattern.
 *
 * The block and the inline parser both need "colons, then a name, then an optional label,
 * then optional shorthands, then optional attributes", and the two must never come to accept
 * different names. What the RFC allows a block directive and not an inline one is written
 * here as well — see {@see self::INLINE_BODY}, which is where the differences are argued.
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
     *
     * The name is required and nothing may separate it from its colon, where the block form
     * lets the RFC's spaces be interspersed: an inline directive sits inside prose, where `: `
     * is how a sentence introduces one — `Note: prose` — and a space that made every such
     * line a directive would cost far more than the block form's tolerance earns. A block
     * directive has no such exposure, since a line beginning with two colons means nothing
     * else.
     *
     * A name is the only thing the inline form has, which is also why it cannot have the
     * block form's other two tolerances: there is nowhere for a container's body or for
     * `:::: SPOILER :::::` to be written.
     */
    public const INLINE_BODY = ':(?!:)(' . self::NAME . ')' . self::LABEL . self::SHORTHAND . self::ATTRIBUTES;

    /** The whole of a text directive. */
    public const INLINE_PATTERN = '/' . self::INLINE_BODY . '/';

    /**
     * The whole of a block directive on one line.
     *
     * Group 1 is the colon run that opened it, 2 the name, 3 the label without brackets, 4
     * the bare shorthands, 5 the attributes with braces. Group 1 stops where the spaces
     * before the rest begin, so a trailing run never counts towards the fence the container
     * closes on — see {@see self::closingPattern()}.
     *
     * Every gap takes spaces, because the RFC says the parts of a block directive "may be
     * interspersed for readability" — `:: name`, `:::eval [label] {.python}`.
     *
     * The name is optional, since the RFC has a container with none: `::: {.myClass}` falls
     * back to a div. The lookahead is what keeps a line of colons from opening one instead
     * of closing the container it is inside — everything after the fence has to begin with
     * something that is neither a colon nor a space, and a name, label, shorthand or
     * attribute block always does. {@see self::matchBlock()} narrows the missing name to a
     * container, which is the only shape the RFC gives one to.
     *
     * The trailing run is the RFC's non-significant `:::: SPOILER :::::`, written last
     * because that is where it puts it, and at least two colons long because one colon is
     * the inline marker rather than a fence.
     */
    public const BLOCK_PATTERN = '/\A(:{2,})[ \t]*(?=[^ \t:])(' . self::NAME . ')?[ \t]*'
        . self::LABEL . '[ \t]*' . self::SHORTHAND . '[ \t]*' . self::ATTRIBUTES
        . '(?:[ \t]*:{2,})?[ \t]*\z/';

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
     * @return array{0: string, 1: int, 2: string|null, 3: string|null, 4: string, 5: string|null}|null
     */
    public static function matchBlock(string $line): ?array
    {
        if (\preg_match(self::BLOCK_PATTERN, $line, $m) !== 1) {
            return null;
        }

        // The RFC grants the missing name to a container, which has a body to become a div
        // around. A leaf has nothing to put in one, so it stays literal text — an empty div
        // in place of what the author wrote is not the recoverable outcome. This cannot be
        // expressed in the pattern: the colon run is one group, and the rule is about how
        // long it is rather than about whether it matched.
        if ($m[2] === '' && \strlen($m[1]) < DirectiveType::Container->minimumColons()) {
            return null;
        }

        // Groups 2, 3 and 4 are optional yet always present once the pattern matched: an
        // optional group that did not participate holds an empty string rather than a
        // missing key. Group 5 is the exception — PHP drops trailing groups that did not
        // participate, and it cannot match an empty string because it is at least `{}`.
        return [
            $m[0],
            \strlen($m[1]),
            $m[2] === '' ? null : $m[2],
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
