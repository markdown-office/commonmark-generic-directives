<?php

declare(strict_types=1);

namespace MarkdownOffice\CommonMark\Directive\Directive;

use Countable;
use MarkdownOffice\CommonMark\Directive\DirectiveType;
use MarkdownOffice\CommonMark\Directive\Exception\UnknownDirective;

/**
 * The set of directive names this extension will recognise.
 *
 * An empty registry is permissive: any well-formed directive parses, which is what the
 * RFC describes and what makes the extension usable as a general-purpose one.
 *
 * A registry with entries is strict: a name that is not in it raises
 * {@see UnknownDirective}. That is the mode worth running in when a machine is writing the
 * Markdown, because the alternative to an error is a document that renders without the
 * block it asked for and says nothing about why.
 */
final class DirectiveRegistry implements Countable
{
    /** @var array<string, array<string, true>> lowercased name, then type value or `*` */
    private array $entries = [];

    /**
     * @param list<string> $names names allowed in every shape, or `name:type` to allow
     *                            one only as that shape
     */
    public function __construct(array $names = [])
    {
        $this->allowMany($names);
    }

    /**
     * Registers `name` for every shape, or `name:type` for that shape alone.
     *
     * A bare name means every shape, which is what a reader expects from `col` and what
     * makes the common case a one-word registration. `badge:text` means that one shape,
     * which is only worth writing when the distinction matters.
     */
    public function allow(string $entry): self
    {
        if (\str_contains($entry, ':')) {
            [$entry, $suffix] = \explode(':', $entry, 2);
            $this->entries[\strtolower($entry)][DirectiveType::from($suffix)->value] = true;

            return $this;
        }

        $this->entries[\strtolower($entry)]['*'] = true;

        return $this;
    }

    /**
     * @param list<string> $entries
     */
    public function allowMany(array $entries): self
    {
        foreach ($entries as $entry) {
            $this->allow($entry);
        }

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    public function count(): int
    {
        return \count($this->entries);
    }

    public function has(string $name, DirectiveType $type): bool
    {
        $shapes = $this->entries[\strtolower($name)] ?? null;

        if ($shapes === null) {
            return false;
        }

        return isset($shapes['*']) || isset($shapes[$type->value]);
    }

    /**
     * @throws UnknownDirective
     */
    public function assertKnown(string $name, DirectiveType $type): void
    {
        if ($this->isEmpty() || $this->has($name, $type)) {
            return;
        }

        throw UnknownDirective::for($name, $type, $this->describe());
    }

    /**
     * Every accepted spelling, sorted, for an error message.
     *
     * @return list<string>
     */
    public function describe(): array
    {
        $described = [];

        foreach ($this->entries as $name => $shapes) {
            if (isset($shapes['*'])) {
                $described[] = $name;
                continue;
            }

            foreach (\array_keys($shapes) as $type) {
                $described[] = $name . ':' . $type;
            }
        }

        \sort($described);

        return $described;
    }
}