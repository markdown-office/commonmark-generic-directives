<?php

declare(strict_types=1);

use MarkdownOffice\CommonMark\Directive\AttributeNaming;
use MarkdownOffice\CommonMark\Directive\Directive\DirectiveRegistry;
use MarkdownOffice\CommonMark\Directive\Exception\UnknownDirective;
use MarkdownOffice\CommonMark\Directive\Node\DirectiveBlock;
use MarkdownOffice\CommonMark\Directive\Node\DirectiveInline;
use MarkdownOffice\CommonMark\Directive\Renderer\DirectiveRenderer;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use MarkdownOffice\CommonMark\Directive\GenericDirectiveExtension;

/**
 * Rendering a directive is handing author-supplied strings to an HTML writer, so the
 * interesting cases here are the ones where that could go wrong.
 */
describe('writing a directive as HTML', function () {
    // The closing half of a container, for the tests below that each open with a
    // different attribute list and then need the same body and the same fence. Kept as
    // one name so the fence cannot end up spelled two ways across them.
    $body = "\nx\n:::";
    it('carries the name on the element', function () {
        expect(render(":::callout\nx\n:::"))->toContain('<div data-directive="callout">');
    });

    it('uses a span for an inline directive and a div for a block', function () {
        expect(render('A :badge[X] b'))->toContain('<span data-directive="badge"')
            ->and(render(":::a\nx\n:::"))->toContain('<div data-directive="a"');
    });

    it('writes the attributes under the name the author gave them', function () {
        expect(render(":::t{type=info n=2 flag}\nx\n:::"))
            ->toContain('type="info" n="2" flag');
    });

    it('writes the id and class shorthands as id and class', function () {
        expect(trim(render('::t{#lead .terse}')))
            ->toBe('<div data-directive="t" id="lead" class="terse"></div>');
    });

    it('writes a named container with a shorthand and an attribute', function () {
        // `::: thing {#mything lang=en}` — the shape the issue asks for.
        expect(trim(render(":::thing{#mything lang=en}\nbody\n:::")))
            ->toBe('<div data-directive="thing" id="mything" lang="en"><p>body</p></div>');
    });

    it('renders the body', function () {
        expect(render(":::a\n**bold**\n:::"))->toContain('<strong>bold</strong>');
    });

    it('renders a leaf label as its content', function () {
        expect(render('::image[Revenue chart]'))->toContain('>Revenue chart<');
    });

    it('escapes a label rather than writing it as markup', function () {
        expect(render('::image[<script>alert(1)</script>]'))->not->toContain('<script>');
    });

    it('escapes an attribute value rather than writing it as markup', function () use ($body) {
        expect(render(':::t{title="<script>alert(1)</script>"}' . $body))
            ->not->toContain('<script>');
    });

    it('escapes a quoted attribute value in the body of a text directive', function () {
        expect(render('A :t[x]{v="<b>y</b>"} b'))->not->toContain('<b>y</b>');
    });

    it('refuses to let an author overwrite the directive name', function () use ($body) {
        // Otherwise `:::a{data-directive="b"}` renders as an element that claims to be
        // something it is not, and the markup is what a consumer sees first.
        expect(render(':::a{data-directive="b"}' . $body))
            ->toBe('<div data-directive="a"><p>x</p></div>' . "\n");
    });

    it('refuses an event handler so it cannot become a script', function () use ($body) {
        // The document is authored data, and this is the one place where an
        // author-supplied string would otherwise reach a live page as code.
        $html = render(':::t{onclick="steal()"}' . $body);

        expect($html)->not->toContain('onclick')
            ->and($html)->not->toMatch('/<[^>]*\son[a-z]+=/');
    });

    it('refuses an event handler whatever its case', function () use ($body) {
        expect(render(':::t{ONCLICK="steal()"}' . $body))->not->toContain('ONCLICK');
    });

    it('refuses an attribute that merely starts with the letters of on', function () use ($body) {
        // The rule is a prefix rather than a list of event names, so `only` goes with
        // `onclick`. The official Attributes extension refuses it too.
        expect(render(':::t{only=1}' . $body))->not->toContain('only');
    });

    it('drops an attribute whose name HTML does not accept', function () {
        // A name that starts with a digit is not a name a browser reads as an
        // attribute. The official Attributes extension drops those too.
        expect(trim(render('::t{12=twelve}')))->toBe('<div data-directive="t"></div>');
    });

    it('renders a directive with no attributes and no body as an empty element', function () {
        expect(trim(render('::a')))->toBe('<div data-directive="a"></div>');
    });

    it('leaves the name off an element written without one', function () {
        expect(render("::: {.myClass}\nbody\n:::"))->toBe('<div class="myClass"><p>body</p></div>' . "\n");
    });

    it('does not let an author attribute name an element that has no name', function () {
        // The key stays reserved whether or not the node has a name to put on it, so
        // `data-directive` never appears on something the document did not name.
        expect(render("::: {directive=fake}\nbody\n:::"))->not->toContain('data-directive');
    });

    it('can be replaced by a consumer that knows what the directive means', function () {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GenericDirectiveExtension());
        $environment->addRenderer(DirectiveBlock::class, new class implements NodeRendererInterface {
            public function render(
                \League\CommonMark\Node\Node $node,
                ChildNodeRendererInterface $childRenderer
            ): string {
                \assert($node instanceof DirectiveBlock);

                return '<aside>' . $childRenderer->renderNodes($node->children()) . '</aside>';
            }
        }, 10);

        expect((string) (new MarkdownConverter($environment))->convert(":::a\nx\n:::"))
            ->toBe('<aside><p>x</p></aside>' . "\n");
    });

    it('can be used on its own, and writes the attributes as the author wrote them', function () {
        // The renderer is public API, so its own default is worth a test: the extension
        // always passes a naming, and nothing else reaches this constructor. The
        // extension is asked for the namespace here, so the plain output below is the
        // renderer's own default rather than the extension's.
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GenericDirectiveExtension(naming: AttributeNaming::Namespaced));
        $environment->addRenderer(DirectiveBlock::class, new DirectiveRenderer());

        expect((string) (new MarkdownConverter($environment))->convert('::t{type=info}'))
            ->toBe('<div data-directive="t" type="info"></div>' . "\n");
    });
});

describe('writing the attributes into the data- namespace', function () {
    it('reproduces them under a namespaced name', function () {
        expect(trim(render('::t{type=info n=2 flag}', null, AttributeNaming::Namespaced)))
            ->toBe('<div data-directive="t" data-type="info" data-n="2" data-flag></div>');
    });

    it('writes the id and class shorthands as data-id and data-class', function () {
        expect(trim(render('::t{#lead .terse}', null, AttributeNaming::Namespaced)))
            ->toBe('<div data-directive="t" data-id="lead" data-class="terse"></div>');
    });

    it('keeps the directive name where it is', function () {
        // The name says what the element is, and the author said nothing about it.
        expect(render('::t{type=info}', null, AttributeNaming::Namespaced))
            ->toContain('data-directive="t"');
    });

    it('namespaces an event handler so it cannot become a script', function () {
        expect(render('::t{onclick="steal()"}', null, AttributeNaming::Namespaced))
            ->toContain('data-onclick="steal()"');
    });

    it('keeps an attribute that merely starts with the letters of on', function () {
        // A blocklist of event names would drop `only` along with `onclick`.
        expect(render('::t{only=1}', null, AttributeNaming::Namespaced))
            ->toContain('data-only="1"');
    });

    it('keeps the reserved key reserved', function () {
        expect(trim(render('::a{data-directive="b"}', null, AttributeNaming::Namespaced)))
            ->toBe('<div data-directive="a"></div>');
    });

    it('renders an attribute whose name is all digits, which PHP gave an int key', function () {
        // `1` and `12` are legal attribute names here and come back out of the array as
        // integers, so the loop over them has to be a string one or the render is a fatal.
        expect(trim(render('::t{0=zero 12=twelve 1a=ok}', null, AttributeNaming::Namespaced)))
            ->toBe('<div data-directive="t" data-0="zero" data-12="twelve" data-1a="ok"></div>');
    });

    it('does not renumber an attribute name that PHP gave an int key', function () {
        expect(trim(render('::t{12=twelve}', null, AttributeNaming::Namespaced)))
            ->toBe('<div data-directive="t" data-12="twelve"></div>');
    });
});

describe('the agreement with the official Attributes extension', function () {
    it('writes the same attributes for the same syntax', function () {
        expect(attributesOf(render('::t{#mything .cls lang=en}')))
            ->toBe(attributesOf(renderWithAttributesExtension("body\n{#mything .cls lang=en}")));
    });

    it('refuses an event handler, as the official Attributes extension does', function () {
        expect(attributesOf(render('::t{onclick="steal()"}')))
            ->toBe(attributesOf(renderWithAttributesExtension("body\n{onclick=\"steal()\"}")));
    });

    it('drops a name HTML does not accept, as the official Attributes extension does', function () {
        expect(attributesOf(render('::t{12=twelve}')))
            ->toBe(attributesOf(renderWithAttributesExtension("body\n{12=twelve}")));
    });
});

describe('the boundaries the inline parser refuses to cross', function () {
    it('does not treat the second colon of a fence as a directive', function () {
        // `::` reaches the inline parser when it is not at the start of a line, and taking
        // it would leave the two parsers disagreeing about the same characters.
        expect(render(':::a:::b' . "\n\nx"))
            ->not->toContain('data-directive="b"');
    });

    it('treats a container opening on the line after a leaf as its own directive', function () {
        // It is a new line, so it is a new block: `::a` closes at the end of its own line
        // and `:::b` opens a container that runs to the end of the document.
        expect(trim(render("::a\n:::b\nx\n:::")))
            ->toBe('<div data-directive="a"></div>' . "\n" . '<div data-directive="b"><p>x</p></div>');
    });

    it('matches at the very start of a paragraph', function () {
        // The guard is "no word character before the colon", and at position zero there is
        // nothing before it, which must not read as a refusal.
        expect(render(':badge[X]'))->toContain('data-directive="badge"');
    });

    it('does not match directly after a digit', function () {
        expect(render('1:badge[X]'))->not->toContain('data-directive');
    });

    it('does not match directly after a closing bracket', function () {
        // A link or emphasis ends in a non-word character, and `:badge[x]` straight after
        // one is punctuation rather than a directive.
        expect(render('**:bold**[X] :badge[Y]'))->toContain('data-directive="badge"');
    });
});

describe('the exception it throws', function () {
    it('lists nothing when nothing is registered', function () {
        // `assertKnown` never reaches this with an empty registry — that is the permissive
        // mode — so it is asserted directly rather than through the parse path.
        $message = UnknownDirective::for('x', \MarkdownOffice\CommonMark\Directive\DirectiveType::Text, [])->getMessage();

        expect($message)->toContain('No directives are registered.');
    });
});

describe('the registry counting what it knows', function () {
    it('counts registered names', function () {
        expect(new DirectiveRegistry(['a', 'b']))->toHaveCount(2);
    });

    it('counts a name twice only once', function () {
        expect(new DirectiveRegistry(['a', 'a']))->toHaveCount(1);
    });
});

describe('constructing attributes directly', function () {
    it('accepts a plain array', function () {
        expect(\MarkdownOffice\CommonMark\Directive\Directive\DirectiveAttributes::of(['a' => '1'])->toArray())
            ->toBe(['a' => '1']);
    });

    it('is empty when given nothing', function () {
        expect(\MarkdownOffice\CommonMark\Directive\Directive\DirectiveAttributes::of())->toHaveCount(0);
    });
});

describe('a directive with nothing in it', function () {
    it('renders an inline one with no label and no attributes', function () {
        expect(render('A :b c'))->toContain('<span data-directive="b"></span>');
    });

    it('writes itself back to source with neither', function () {
        $found = directive(parse('A :b c'), 'b');

        expect((string) $found)->toBe(':b')
            ->and($found)->toBeInstanceOf(DirectiveInline::class);
    });
});
