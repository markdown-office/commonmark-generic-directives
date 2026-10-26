<?php

declare(strict_types=1);

namespace MarkdownOffice\CommonMark\Directive;

/**
 * How the default renderer writes an author's attributes.
 *
 * Two ways, because the same syntax means one thing in the official Attributes
 * extension and another here, and a document that uses both should not have to mean two.
 */
enum AttributeNaming
{
    /**
     * Into the `data-` namespace: `{type=info}` becomes `data-type="info"`.
     *
     * Nothing an author writes can become an event handler, because no event handler
     * has a name in that namespace.
     */
    case Namespaced;

    /**
     * As the author wrote them: `{type=info}` becomes `type="info"`.
     *
     * What the official Attributes extension writes for the same syntax. An event
     * handler is refused, as it is there: an attribute whose name starts with `on` is
     * dropped rather than written.
     */
    case AsWritten;

    /**
     * The name the attribute is written under.
     */
    public function attributeName(string $attribute): string
    {
        return match ($this) {
            self::Namespaced => \str_starts_with($attribute, 'data-') ? $attribute : 'data-' . $attribute,
            self::AsWritten => $attribute,
        };
    }

    /**
     * Whether the attribute may be written onto the element at all.
     */
    public function allows(string $attribute): bool
    {
        // The namespaced mode has nothing to refuse: `onclick` lands on `data-onclick`,
        // which no browser runs. The rule is a prefix rather than a list of event names
        // because every HTML event handler starts with `on` — but it also refuses an
        // attribute that merely starts with those two letters, which is what the
        // official Attributes extension does too.
        return $this === self::Namespaced || !\str_starts_with(\strtolower($attribute), 'on');
    }
}
