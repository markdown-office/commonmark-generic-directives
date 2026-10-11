<?php

declare(strict_types=1);

namespace MarkdownOffice\CommonMark\Directive\Directive;

/**
 * Reads the `{…}` of a directive into name/value pairs.
 *
 * Its own class because this is a grammar and {@see DirectiveAttributes} is the value
 * object it produces: one holds a parsed set and answers questions about it, the other
 * turns text into one. Keeping them apart is what lets a change to what counts as an
 * attribute name land here without touching the accessors.
 *
 * The position is threaded through by reference rather than returned, because every
 * piece below reports the same way — by having consumed bytes, or by none — and that
 * question is cheaper to answer from the position than to wrap every call in a status.
 */
final class AttributeParser
{
    /**
     * The attributes in the text between the braces. The braces themselves are not
     * expected.
     *
     * Unparseable input is skipped rather than rejected, so a malformed attribute never
     * costs the author the whole directive.
     *
     * @return array<string, string|true>
     */
    public static function parse(string $input): array
    {
        $values = [];
        $length = \strlen($input);
        $i = 0;

        while ($i < $length) {
            $before = $i;
            self::parseOne($input, $i, $length, $values);

            // A byte that starts no attribute at all is stepped over here, or the loop
            // would spin on it forever. Every other path moves `$i` itself.
            if ($i === $before) {
                $i++;
            }
        }

        return $values;
    }

    /**
     * Consumes one attribute, or nothing at all.
     *
     * `$i` is left wherever the piece stopped rather than returned alongside a status:
     * the pieces differ only in whether they consumed bytes, so the position is the
     * whole of the answer.
     *
     * @param array<string, string|true> $values
     */
    private static function parseOne(string $input, int &$i, int $length, array &$values): void
    {
        if (\ctype_space($input[$i])) {
            $i++;

            return;
        }

        if ($input[$i] === '#' || $input[$i] === '.') {
            self::parseShorthand($input, $i, $length, $values);

            return;
        }

        self::parsePair($input, $i, $length, $values);
    }

    /**
     * @param array<string, string|true> $values
     */
    private static function parseShorthand(string $input, int &$i, int $length, array &$values): void
    {
        $shortcut = $input[$i];
        $i++;
        $name = self::readIdentifier($input, $i, $length);

        // `{#.}` is a shorthand with no name behind it. The `#` is already stepped over,
        // so there is nothing left to skip here and the loop will move on.
        if ($name === '') {
            return;
        }

        if ($shortcut === '#') {
            $values['id'] = $name;

            return;
        }

        // Several `.class` entries accumulate rather than replace.
        $existing = $values['class'] ?? null;
        $values['class'] = \is_string($existing) && $existing !== ''
            ? $existing . ' ' . $name
            : $name;
    }

    /**
     * @param array<string, string|true> $values
     */
    private static function parsePair(string $input, int &$i, int $length, array &$values): void
    {
        $name = self::readIdentifier($input, $i, $length);

        if ($name === '') {
            return;
        }

        // `{wide}` and `{wide=}` are both "present"; only a real value is stored.
        if ($i >= $length || $input[$i] !== '=') {
            $values[$name] = true;

            return;
        }

        $i++; // step over `=`

        $values[$name] = self::readValue($input, $i, $length);
    }

    private static function readValue(string $input, int &$i, int $length): string
    {
        // The `??` matters: a `{name=}` runs out of input here, and reading past the end
        // of the string is a warning rather than an empty string.
        $quote = $input[$i] ?? '';

        if ($quote === '"' || $quote === "'") {
            return self::readQuoted($input, $i, $length, $quote);
        }

        return self::readBare($input, $i, $length);
    }

    private static function readQuoted(string $input, int &$i, int $length, string $quote): string
    {
        $i++; // step over the opening quote
        $value = '';

        while ($i < $length && $input[$i] !== $quote) {
            $value .= $input[$i];
            $i++;
        }

        // An unterminated quote ends with the input rather than swallowing the rest of
        // the document, so this is conditional rather than a step taken on faith.
        if ($i < $length) {
            $i++; // step over the closing quote
        }

        return $value;
    }

    private static function readBare(string $input, int &$i, int $length): string
    {
        $value = '';

        // A bare value runs to the next space or to a brace. The brace matters when a
        // shorthand and an attribute block arrive in one string, which is what
        // `#lead.terse{type=ok}` is.
        while ($i < $length && !\ctype_space($input[$i]) && $input[$i] !== '{' && $input[$i] !== '}') {
            $value .= $input[$i];
            $i++;
        }

        return $value;
    }

    /**
     * Reads an identifier, advancing `$i` past it.
     *
     * A character scan rather than a regex: the identifier charset is small and fixed,
     * and this runs once per attribute on every directive in a document.
     */
    private static function readIdentifier(string $input, int &$i, int $length): string
    {
        $name = '';

        while ($i < $length) {
            $char = $input[$i];

            if (\ctype_alnum($char) || $char === '_') {
                $name .= $char;
                $i++;
                continue;
            }

            // `-` and `:` continue a name but never start one, so `data-x` and `xml:lang`
            // read as one key. `.` deliberately does not: it is the separator between a
            // shorthand and the one after it, and letting a name swallow it would turn
            // `#lead.terse` into an id called `lead.terse`.
            if ($name !== '' && ($char === ':' || $char === '-')) {
                $name .= $char;
                $i++;
                continue;
            }

            break;
        }

        return $name;
    }
}
