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

        for ($i = 0; $i < $length;) {
            // Skip the whitespace between attributes.
            if (\ctype_space($input[$i])) {
                $i++;
                continue;
            }

            if ($input[$i] === '#' || $input[$i] === '.') {
                $shortcut = $input[$i];
                $i++;
                $name = self::readIdentifier($input, $i, $length);

                if ($name === '') {
                    continue;
                }

                if ($shortcut === '#') {
                    $values['id'] = $name;
                } else {
                    // Several `.class` entries accumulate rather than replace.
                    $existing = $values['class'] ?? null;
                    $values['class'] = \is_string($existing) && $existing !== ''
                        ? $existing . ' ' . $name
                        : $name;
                }

                continue;
            }

            $name = self::readIdentifier($input, $i, $length);

            if ($name === '') {
                // Not an attribute start; step over the byte so the loop terminates.
                $i++;
                continue;
            }

            // `{wide}` and `{wide=}` are both "present"; only a real value is stored.
            if ($i >= $length || $input[$i] !== '=') {
                $values[$name] = true;
                continue;
            }

            $i++; // step over `=`

            if ($i < $length && ($input[$i] === '"' || $input[$i] === "'")) {
                $quote = $input[$i];
                $i++;
                $value = '';

                while ($i < $length && $input[$i] !== $quote) {
                    $value .= $input[$i];
                    $i++;
                }

                if ($i < $length) {
                    $i++; // step over the closing quote
                }

                $values[$name] = $value;
                continue;
            }

            $value = '';

            // A bare value runs to the next space or to a brace. The brace matters when a
            // shorthand and an attribute block arrive in one string, which is what
            // `#lead.terse{type=ok}` is.
            while ($i < $length && !\ctype_space($input[$i]) && $input[$i] !== '{' && $input[$i] !== '}') {
                $value .= $input[$i];
                $i++;
            }

            $values[$name] = $value;
        }

        return new self($values);
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

        $class = $this->getString('class');

        if ($class !== null) {
            foreach (\preg_split('/\s+/', $class) ?: [] as $name) {
                if ($name !== '') {
                    $pairs[] = '.' . $name;
                }
            }
        }

        foreach ($this->values as $name => $value) {
            if ($name === 'id' || $name === 'class') {
                continue;
            }

            if ($value === true) {
                $pairs[] = $name;
                continue;
            }

            $pairs[] = $name . '="' . \str_replace('"', '\\"', $value) . '"';
        }

        return $pairs === [] ? '' : '{' . implode(' ', $pairs) . '}';
    }
}