<?php

declare(strict_types=1);

namespace MarkdownOffice\CommonMark\Directive\Node;

use League\CommonMark\Node\Inline\AbstractInline;
use League\CommonMark\Node\Inline\Text;
use MarkdownOffice\CommonMark\Directive\Directive\DirectiveAttributes;
use MarkdownOffice\CommonMark\Directive\DirectiveType;
use Stringable;

/**
 * A `:text` directive sitting inside a paragraph.
 *
 * The label is carried twice: as the string the author wrote between the brackets, and as
 * the inline Markdown it is parsed into. {@see self::getLabel()} is the string and the
 * children are the parsed form.
 *
 * A node built by hand keeps its label as text; the parser is what parses it — see
 * {@see DirectiveInlineParser}.
 */
final class DirectiveInline extends AbstractInline implements Stringable
{
    private readonly DirectiveAttributes $attributes;

    public function __construct(
        private readonly string $name,
        ?DirectiveAttributes $attributes = null,
        private readonly ?string $label = null,
    ) {
        parent::__construct();

        $this->attributes = $attributes ?? DirectiveAttributes::none();

        if ($label !== null && $label !== '') {
            $this->appendChild(new Text($label));
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): DirectiveType
    {
        return DirectiveType::Text;
    }

    public function getAttributes(): DirectiveAttributes
    {
        return $this->attributes;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * Always false, and here so a consumer does not have to branch on the node class to
     * ask whether a directive holds blocks.
     */
    public function isContainer(): bool
    {
        return false;
    }

    public function __toString(): string
    {
        $label = $this->label === null || $this->label === '' ? '' : '[' . $this->label . ']';

        return ':' . $this->name . $label . $this->attributes;
    }
}
