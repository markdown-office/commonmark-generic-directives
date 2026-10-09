<?php

declare(strict_types=1);

namespace MarkdownOffice\CommonMark\Directive\Node;

use League\CommonMark\Node\Block\AbstractBlock;
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
 */
final class DirectiveBlock extends AbstractBlock implements Stringable
{
    private readonly DirectiveAttributes $attributes;

    public function __construct(
        private readonly string $name,
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

    public function getName(): string
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
     * Leaf directives take one; containers do not, since their body is Markdown and
     * belongs in the children.
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
 * The body's own fence length is not reproduced. A container written with four colons
 * closes with three in practice, and `:::name` is the form a reader is meant to
 * recognise.
 */
public function __toString(): string
{
    $label = $this->label === null || $this->label === '' ? '' : '[' . $this->label . ']';

    return \str_repeat(':', $this->type->minimumColons()) . $this->name . $label . $this->attributes;
}
}