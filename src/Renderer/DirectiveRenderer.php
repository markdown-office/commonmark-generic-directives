<?php

declare(strict_types=1);

namespace MarkdownOffice\CommonMark\Directive\Renderer;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;
use MarkdownOffice\CommonMark\Directive\Node\DirectiveBlock;
use MarkdownOffice\CommonMark\Directive\Node\DirectiveInline;

/**
 * Writes a directive as HTML.
 *
 * The default is deliberately information-preserving rather than pretty: the element name
 * carries the directive name, and every attribute is reproduced, so nothing a consumer
 * might have wanted is lost. What a directive actually *means* is the consumer's
 * business — a consumer that wants `<aside class="callout">` registers its own renderer
 * over this one rather than asking this one to guess.
 *
 * Author attributes are written into the `data-` namespace. That is not tidiness: an
 * attribute named `onclick` written into a page that later runs scripts is a script, and
 * deciding which names are dangerous by keeping a list means the list is only ever
 * correct until someone invents an event. Namespacing needs no list to maintain. A
 * consumer that wants real attributes reads the node rather than this markup.
 */
final class DirectiveRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        \assert($node instanceof DirectiveBlock || $node instanceof DirectiveInline);

        $inner = $childRenderer->renderNodes($node->children());
        $label = $node->getLabel();

        // A leaf has no children by definition, so its label is the content.
        if ($inner === '' && $label !== null && $label !== '') {
            $inner = Xml::escape($label);
        }

        // First, so that it reads as the element's identity rather than as one more
        // attribute. The loop below skips it, which is what keeps it first *and* first.
        $element = ['data-directive' => $node->getName()];

        foreach ($node->getAttributes() as $name => $value) {
            $key = \str_starts_with($name, 'data-') ? $name : 'data-' . $name;

            // An author attribute landing on the key above is dropped rather than
            // renamed: a second attribute differing only by one `data-` would mean
            // nothing to whoever reads the output, and renaming it would pretend the
            // author had asked for that name.
            if ($key === 'data-directive') {
                continue;
            }

            $element[$key] = $value;
        }

        return (string) new HtmlElement(
            $node instanceof DirectiveInline ? 'span' : 'div',
            $element,
            $inner
        );
    }
}