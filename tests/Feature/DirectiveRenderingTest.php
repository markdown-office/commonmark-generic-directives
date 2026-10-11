<?php

declare(strict_types=1);

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

    it('reproduces the attributes in the data- namespace', function () {
        expect(render(":::t{type=info n=2 flag}\nx\n:::"))
            ->toContain('data-type="info" data-n="2" data-flag');
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

    it('namespaces an event handler so it cannot become a script', function () use ($body) {
        // The document is authored data, and this is the one place where an
        // author-supplied string would otherwise reach a live page as code.
        $html = render(':::t{onclick="steal()"}' . $body);

        expect($html)->toContain('data-onclick="steal()"')
            ->and($html)->not->toMatch('/<[^>]*\sonclick=/');
    });

    it('keeps an attribute that merely starts with the letters of on', function () use ($body) {
        // A blocklist of event names would drop `only` along with `onclick`.
        expect(render(':::t{only=1}' . $body))->toContain('data-only="1"');
    });

    it('renders a directive with no attributes and no body as an empty element', function () {
        expect(trim(render('::a')))->toBe('<div data-directive="a"></div>');
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
