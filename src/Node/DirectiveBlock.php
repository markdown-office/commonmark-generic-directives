<?php

declare(strict_types=1);

namespace MarkdownOffice\CommonMark\Directive\Node;

use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Node;
use MarkdownOffice\CommonMark\Directive\Directive\DirectiveAttributes;
use MarkdownOffice\CommonMark\Directive\DirectiveType;
use Stringable;

/**
 * A `::leaf` or `:::container` directive.
 *
 * Both shapes share one node because they share everything a consumer needs to ask:
 * the name, the attributes, and the type. Splitting them would mean every renderer and
 * every consumer branches on the class instead of reading one property.
 *
 * A leaf has no block children; a container holds whatever Markdown its fence encloses.
 *
 * The name is null for a container written without one, which the RFC has fall back to a
 * div — `::: {.myClass}`. It is null rather than the string `div`, because the div is the
 * element the RFC falls back to and not a directive the document named.
 */
final class DirectiveBlock extends AbstractBlock implements Stringable
{
    private readonly DirectiveAttributes $attributes;

    public function __construct(
        private readonly ?string $name,
        private readonly DirectiveType $type,
        ?DirectiveAttributes $attributes = null,
        private readonly ?string $label = null,
    ) {
        parent::__construct();

        $this->attributes = $attributes ?? DirectiveAttributes::none();
    }

    /**
     * How many nested containers inside this one have not been closed yet.
     *
     * The parser consults this before honouring a closing fence, and it is the only way
     * it can know. CommonMark asks each open container whether it continues, outermost
     * first, and closes *everything* from the one that answers "finished" upwards — so
     * without this, the `:::` closing an inner container would close its parent as well.
     *
     * Mutable for that reason alone. It is parse state rather than document content, and
     * nothing that renders reads it.
     */
    private int $openNested = 0;

    public function openNestedContainer(): void
    {
        $this->openNested++;
    }

    public function closeNestedContainer(): void
    {
        if ($this->openNested > 0) {
            $this->openNested--;
        }
    }

    public function hasOpenNestedContainer(): bool
    {
        return $this->openNested > 0;
    }

    /**
     * The nearest directive container at or above `$node`, or null when there is none.
     *
     * The two ends of a nested container have to agree on which container that is: the one
     * that counts a child and the one that stops waiting for it. A nested container is not
     * always a *direct* child of the container it counts — a blockquote or a list item can
     * sit between the two — and the fence that has to be honoured belongs to the outermost,
     * so both ends walk rather than one of them asking for a parent.
     */
    public static function enclosing(Node $node): ?self
    {
        for (; $node !== null; $node = $node->parent()) {
            if ($node instanceof self) {
                return $node;
            }
        }

        return null;
    }

    /**
     * The directive's name, or null when it was written without one.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    public function getType(): DirectiveType
    {
        return $this->type;
    }

    public function getAttributes(): DirectiveAttributes
    {
        return $this->attributes;
    }

    /**
     * The text between `[` and `]`, if any.
     *
     * Both shapes take one — the RFC's container syntax has an `[inline-content]` — but a
     * container's own Markdown belongs in its children, so a label on one is a title the
     * author wrote rather than something to render in place of a body.
     */
    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function isContainer(): bool
    {
        return $this->type === DirectiveType::Container;
    }

    /**
     * Back to source form, so a document can be rewritten without losing the directive.
     *
     * Neither the fence length nor the RFC's non-significant trailing colons are
     * reproduced. A container written with four colons closes with three in practice, and
     * `:::name` is the form a reader is meant to recognise.
     */
    public function __toString(): string
    {
    $label = $this->label === null || $this->label === '' ? '' : '[' . $this->label . ']';

        return \str_repeat(':', $this->type->minimumColons()) . $this->name . $label . $this->attributes;
    }
}
