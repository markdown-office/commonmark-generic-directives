<?php

declare(strict_types=1);

namespace MarkdownOffice\CommonMark\Directive;

use League\CommonMark\Util\RegexHelper;

/**
 * How the default renderer writes an author's attributes.
 *
 * Two ways, because the same syntax means one thing in the official Attributes
 * extension and another here, and a document that uses both should not have to mean two.
 */
enum AttributeNaming: string
{
    /**
     * As the author wrote them: `{type=info}` becomes `type="info"`.
     *
     * What the official Attributes extension writes for the same syntax, and what this
     * package writes unless it is told otherwise. An event handler is refused, as it is
     * there: an attribute whose name starts with `on` is dropped rather than written.
     */
    case AsWritten = 'as-written';

    /**
     * Into the `data-` namespace: `{type=info}` becomes `data-type="info"`.
     *
     * Nothing an author writes can become a live attribute, because no live attribute
     * has a name in that namespace.
     */
    case Namespaced = 'namespaced';

    /**
     * The name the attribute is written under.
     */
    public function attributeName(string $attribute): string
    {
        return match ($this) {
            self::AsWritten => $attribute,
            self::Namespaced => \str_starts_with($attribute, 'data-') ? $attribute : 'data-' . $attribute,
        };
    }

    /**
     * Whether the attribute may be written onto the element at all.
     */
    public function allows(string $attribute): bool
    {
        // The namespaced mode has nothing to refuse: `onclick` lands on `data-onclick`,
        // which no browser runs, and the prefix makes every name one HTML accepts.
        if ($this === self::Namespaced) {
            return true;
        }

        // A name HTML does not accept is not a name the browser reads as an attribute,
        // so writing it would say one thing and mean another. The official Attributes
        // extension drops those too.
        if (\preg_match('/^' . RegexHelper::PARTIAL_ATTRIBUTENAME . '$/', $attribute) !== 1) {
            return false;
        }

        // An event handler is a script the moment it lands on a page that runs scripts,
        // and every HTML event handler starts with `on`. The rule is a prefix rather than
        // a list of names — which is why an attribute that merely starts with those two
        // letters goes with it.
        return !\str_starts_with(\strtolower($attribute), 'on');
    }
}
