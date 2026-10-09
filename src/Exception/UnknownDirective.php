<?php

declare(strict_types=1);

namespace MarkdownOffice\CommonMark\Directive\Exception;

use MarkdownOffice\CommonMark\Directive\DirectiveType;
use RuntimeException;

/**
 * Thrown when a directive is well-formed but the extension was told which names exist,
 * and this is not one of them.
 *
 * A directive that is silently ignored is the worst outcome available here: the author
 * sees their content render without the block they asked for and has no way to tell
 * whether the name was misspelled, the type was wrong, or the feature is not built yet.
 * Naming the alternatives turns that into a one-line fix.
 */
final class UnknownDirective extends RuntimeException
{
    /**
     * @param list<string> $registered every name that would have been accepted, as `name`
     *                                  or `name:type` when the type narrows it further
     */
    public static function for(string $name, DirectiveType $type, array $registered): self
    {
        return new self(sprintf(
            'Unknown %s directive "%s". %s',
            $type->value,
            $name,
            $registered === []
                ? 'No directives are registered.'
                : 'Registered: ' . implode(', ', $registered) . '.'
        ));
    }
}