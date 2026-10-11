<?php

declare(strict_types=1);

namespace MarkdownOffice\CommonMark\Directive\Directive;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Stringable;
use Traversable;

/**
 * The `{…}` of a directive, parsed once and carried on the node.
 *
 * Four forms are accepted, matching the Generic Directives RFC:
 *
 * ```
 * {width=50%}          bare value, runs to whitespace
 * {title="a b c"}      double-quoted, so spaces survive
 * {title='a "b" c'}    single-quoted, so double quotes survive
 * {wide}               no value at all — present and true
 * ```
 *
 * `#id` and `.class` are shorthands: they set `id`, and append to a space-separated
 * `class`, so several of them accumulate rather than overwrite each other.
 *
 * @implements IteratorAggregate<string, string|true>
 */
final class DirectiveAttributes implements Countable, IteratorAggregate, JsonSerializable, Stringable
{
    /**
     * @param array<string, string|true> $values
     */
    private function __construct(private readonly array $values = [])
    {
    }

    /**
     * @param array<string, string|true> $values
     */
    public static function of(array $values = []): self
    {
        return new self($values);
    }

    public static function none(): self
    {
        return new self();
    }

    /**
     * Parses the text between the braces. The braces themselves are not expected.
     *
     * Unparseable input is skipped rather than rejected, so a malformed attribute never
     * costs the author the whole directive.
     */
    public static function parse(string $input): self
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

        return new self($values);
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

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->values);
    }

    /**
     * @return string|true|null the raw value, `true` for a bare attribute, or null when absent
     */
    public function get(string $name): string|true|null
    {
        return $this->values[$name] ?? null;
    }

    public function getString(string $name, ?string $default = null): ?string
    {
        $value = $this->get($name);

        if ($value === null) {
            return $default;
        }

        // A bare `{wide}` asked for the attribute's presence, not its text, so there is
        // no text to give back and the default is the honest answer.
        return $value === true ? $default : $value;
    }

    public function getBool(string $name, bool $default = false): bool
    {
        $value = $this->get($name);

        if ($value === null) {
            return $default;
        }

        if ($value === true) {
            return true;
        }

        return \filter_var($value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public function getInt(string $name, ?int $default = null): ?int
    {
        $value = $this->getString($name);

        return $value !== null && \preg_match('/^[+-]?\d+$/', $value) === 1 ? (int) $value : $default;
    }

    public function getFloat(string $name, ?float $default = null): ?float
    {
        $value = $this->getString($name);

        return $value !== null && \is_numeric($value) ? (float) $value : $default;
    }

    /**
     * @return list<string>
     */
    public function getList(string $name): array
    {
        $value = $this->getString($name);

        if ($value === null) {
            return [];
        }

        return \array_values(\array_filter(\preg_split('/[\s,]+/', $value) ?: [], static fn (string $part): bool => $part !== ''));
    }

    /**
     * Returns a set where `$other`'s values win, except that `class` accumulates.
     *
     * Needed because a directive can be written attributes in two places at once —
     * `:::note#lead.terse{title="x"}` — and a `.class` shorthand should add to a class
     * attribute rather than replace it.
     */
    public function mergedWith(self $other): self
    {
        $values = \array_merge($this->values, $other->values);

        $thisClass = $this->values['class'] ?? null;
        $otherClass = $other->values['class'] ?? null;

        if (\is_string($thisClass) && \is_string($otherClass)) {
            $values['class'] = \trim($thisClass . ' ' . $otherClass);
        }

        return new self($values);
    }

    /**
     * @return array<string, string|true>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    public function count(): int
    {
        return \count($this->values);
    }

    /**
     * @return Traversable<string, string|true>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->values);
    }

    /**
     * @return array<string, string|true>
     */
    public function jsonSerialize(): array
    {
        return $this->values;
    }

    /**
     * Round-trips back to source form, so a document can be rewritten without losing it.
     *
     * The shorthands are emitted first because that is how they are conventionally
     * written, and because `#` and `.` are what an author reaching for this by hand
     * would reach for again.
     */
    public function __toString(): string
    {
        $pairs = [];

        $id = $this->getString('id');

        if ($id !== null) {
            $pairs[] = '#' . $id;
        }

        foreach (self::classNames($this->getString('class')) as $name) {
            $pairs[] = '.' . $name;
        }

        foreach ($this->values as $name => $value) {
            // `id` and `class` were written above in the shorthand form; writing them
            // again here would print each of them twice, once in each shape.
            if ($name === 'id' || $name === 'class') {
                continue;
            }

            $pairs[] = $value === true
                ? $name
                : $name . '="' . \str_replace('"', '\\"', $value) . '"';
        }

        return $pairs === [] ? '' : '{' . implode(' ', $pairs) . '}';
    }

    /**
     * @return list<string>
     */
    private static function classNames(?string $class): array
    {
        if ($class === null) {
            return [];
        }

        // Split on whitespace alone, not on commas as `getList()` does: a comma is a
        // legal character in a class name, and treating it as a separator here would
        // rewrite the author's name on the way back out.
        return \array_values(\array_filter(
            \preg_split('/\s+/', $class) ?: [],
            static fn (string $part): bool => $part !== '',
        ));
    }
}
