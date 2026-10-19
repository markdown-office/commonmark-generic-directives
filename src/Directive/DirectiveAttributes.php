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
        return new self(AttributeParser::parse($input));
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
     *
     * `array_replace()` rather than `array_merge()` because `array_merge()` renumbers
     * integer keys. An attribute named `12` reached here as an integer — PHP casts a
     * numeric-string key on the way in and no array keeps it a string — and renumbering
     * would write it out under a name the author never wrote.
     */
    public function mergedWith(self $other): self
    {
        $values = \array_replace($this->values, $other->values);

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
                : $name . '=' . self::quoted($value);
        }

        return $pairs === [] ? '' : '{' . implode(' ', $pairs) . '}';
    }

    /**
     * A value in a form {@see \MarkdownOffice\CommonMark\Directive\Directive\AttributeParser} reads back.
     *
     * The parser has no escape for a quote inside a quoted value, so the writer takes the
     * quote the value does not contain rather than the one it would rather use. A value
     * carrying both is the one thing the syntax cannot express and the one thing this
     * round trip cannot preserve.
     */
    private static function quoted(string $value): string
    {
        $quote = \str_contains($value, '"') ? "'" : '"';

        return $quote . $value . $quote;
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
