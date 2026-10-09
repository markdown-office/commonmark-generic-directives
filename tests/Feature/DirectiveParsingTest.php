<?php

declare(strict_types=1);

use MarkdownOffice\CommonMark\Directive\Directive\DirectiveRegistry;
use MarkdownOffice\CommonMark\Directive\DirectiveType;
use MarkdownOffice\CommonMark\Directive\Exception\UnknownDirective;
use MarkdownOffice\CommonMark\Directive\Node\DirectiveBlock;
use MarkdownOffice\CommonMark\Directive\Node\DirectiveInline;

describe('a container directive', function () {
    it('is recognised and keeps its name and attributes', function () {
        $found = directive(parse(":::callout{type=info title=\"Hosting\"}\nbody\n:::"), 'callout');

        expect($found)->toBeInstanceOf(DirectiveBlock::class)
            ->and($found->getType())->toBe(DirectiveType::Container)
            ->and($found->getAttributes()->getString('type'))->toBe('info')
            ->and($found->getAttributes()->getString('title'))->toBe('Hosting');
    });

    it('parses its body as Markdown', function () {
        $found = directive(parse(":::callout\nSome **bold** text.\n:::"), 'callout');

        expect(textOf($found))->toBe('Some bold text.');
    });

    it('holds a list as readily as a paragraph', function () {
        expect(render(":::col\n- one\n- two\n:::"))
            ->toContain('<li>one</li>');
    });

    it('is empty when it has no body', function () {
        $found = directive(parse(":::empty\n:::"), 'empty');

        expect($found->children())->toHaveCount(0);
    });

    it('stops at its own fence', function () {
        expect(trim(render(":::a\ninside\n:::\n\nafter")))
            ->toBe('<div data-directive="a"><p>inside</p></div>' . "\n" . '<p>after</p>');
    });

    it('closes at the end of the document when its fence is missing', function () {
        // Better a container that runs to the end than an error: the content is what the
        // author wrote, and the fence is usually what they forgot.
        expect(render(":::a\nstill open"))->toContain('still open');
    });
});

describe('nesting', function () {
    it('nests a container inside a container of the same fence length', function () {
        $html = render(":::outer\n:::inner\ndeep\n:::\nback\n:::");

        expect(trim($html))->toBe(
            '<div data-directive="outer"><div data-directive="inner"><p>deep</p></div>' . "\n"
            . '<p>back</p></div>'
        );
    });

    it('keeps the content after an inner container inside the outer one', function () {
        $document = parse(":::outer\n:::inner\ndeep\n:::\nback\n:::");

        $outer = directive($document, 'outer');

        expect($outer->children())->toHaveCount(2);
    });

    it('nests three deep', function () {
        expect(trim(render(":::a\n:::b\n:::c\nx\n:::\n:::\n:::")))
            ->toBe('<div data-directive="a"><div data-directive="b"><div data-directive="c"><p>x</p></div></div></div>');
    });

    it('nests siblings', function () {
        expect(render(":::outer\n:::one\na\n:::\n:::two\nb\n:::\n:::"))
            ->toContain('<div data-directive="one">')
            ->toContain('<div data-directive="two">');
    });

    it('closes the outer one again once the inner one is closed', function () {
        // The failure this guards against is the outer container consuming the inner's
        // closing fence, which would drop everything after it out of the outer block.
        $outer = directive(parse(":::outer\n:::inner\ndeep\n:::\nback\n:::"), 'outer');

        expect(textOf($outer))->toContain('back');
    });

    it('lets a container opened with four colons contain a three-colon fence', function () {
        expect(render("::::outer\n:::inner\nx\n:::\n::::"))
            ->toContain('<div data-directive="outer">')
            ->toContain('<div data-directive="inner">');
    });
});

describe('a leaf directive', function () {
    it('is recognised as a block', function () {
        $found = directive(parse('::image{src="a.png"}'), 'image');

        expect($found)->toBeInstanceOf(DirectiveBlock::class)
            ->and($found->getType())->toBe(DirectiveType::Leaf);

        \assert($found instanceof DirectiveBlock);

        expect($found->isContainer())->toBeFalse();
    });

    it('has no body, so the line after it is a separate block', function () {
        $html = render("::image{src=\"a.png\"}\nafter");

        expect(trim($html))->toBe(
            '<div data-directive="image" data-src="a.png"></div>' . "\n" . '<p>after</p>'
        );
    });

    it('carries a label', function () {
        expect(directive(parse('::image[Revenue chart]'), 'image')->getLabel())->toBe('Revenue chart');
    });

    it('does not swallow the next line', function () {
        $found = directive(parse("::image\nstill its own paragraph"), 'image');

        expect($found->children())->toHaveCount(0);
    });
});

describe('a text directive', function () {
    it('is recognised inside a paragraph', function () {
        $found = directive(parse('Ship it :badge[PRODUCTION]{type=success} today.'), 'badge');

        expect($found)->toBeInstanceOf(DirectiveInline::class)
            ->and($found->getType())->toBe(DirectiveType::Text)
            ->and($found->getLabel())->toBe('PRODUCTION')
            ->and($found->getAttributes()->getString('type'))->toBe('success');
    });

    it('is recognised in a heading, which is where a badge usually goes', function () {
        expect(hasDirective(parse('# Status :badge[OK]{type=ok}'), 'badge'))->toBeTrue();
    });

    it('is recognised inside a list item and a table cell', function () {
        expect(hasDirective(parse('- item :badge[X]'), 'badge'))->toBeTrue();
        expect(hasDirective(parse(":::col\n- item :badge[X]\n:::"), 'badge'))->toBeTrue();
    });

    it('works without a label', function () {
        $found = directive(parse('A :icon{size=2} here'), 'icon');

        expect($found->getLabel())->toBeNull()
            ->and($found->getAttributes()->getInt('size'))->toBe(2);
    });

    it('works without attributes', function () {
        expect(directive(parse('A :kbd[Ctrl] here'), 'kbd')->getAttributes())->toHaveCount(0);
    });

    it('keeps a bare attribute as true', function () {
        expect(directive(parse('A :flag{focus} here'), 'flag')->getAttributes()->getBool('focus'))->toBeTrue();
    });

    it('reads the shorthand forms', function () {
        $found = directive(parse('A :badge[X]#lead.terse{type=ok} here'), 'badge');

        expect($found->getAttributes()->getString('id'))->toBe('lead')
            ->and($found->getAttributes()->getString('class'))->toBe('terse')
            ->and($found->getAttributes()->getString('type'))->toBe('ok');
    });
});

describe('what is deliberately not a directive', function () {
    it('leaves a colon before a space alone', function () {
        expect(trim(render('Note: this is prose.')))->toBe('<p>Note: this is prose.</p>');
    });

    it('leaves a colon between digits alone', function () {
        expect(render('The ratio is 3:2 and the time is 10:30.'))->not->toContain('data-directive');
    });

    it('leaves a word before the colon alone', function () {
        expect(render('See status:error for details.'))->not->toContain('data-directive');
    });

    it('leaves a URL alone', function () {
        expect(render('See https://example.test/a/b for more.'))->not->toContain('data-directive');
    });

    it('leaves a colon in a fenced code block alone', function () {
        expect(render("```\n:badge[X]\n```"))->not->toContain('data-directive');
    });

    it('leaves a colon in an indented code block alone', function () {
        expect(render("    :badge[X]"))->not->toContain('data-directive');
    });

    it('leaves a four-space-indented block directive alone', function () {
        expect(render("    :::callout\n    x\n    :::"))->not->toContain('data-directive');
    });

    it('leaves a directive name starting with a digit alone', function () {
        expect(render('::2fast'))->not->toContain('data-directive');
    });

    it('leaves a container whose fence never opened alone', function () {
        expect(render('just ::: text'))->not->toContain('data-directive');
    });
});

describe('a registry that knows the names', function () {
    it('accepts a registered name', function () {
        expect(render(":::col{x=1}\n:::\n", new DirectiveRegistry(['col'])))->toContain('data-directive="col"');
    });

    it('rejects an unregistered name rather than dropping it', function () {
        render(":::columns{x=1}\n:::\n", new DirectiveRegistry(['col']));
    })->throws(UnknownDirective::class, 'Unknown container directive "columns"');

    it('rejects an unregistered text directive', function () {
        render('a :wat[x] b', new DirectiveRegistry(['col']));
    })->throws(UnknownDirective::class, 'Unknown text directive "wat"');

    it('rejects a name registered only for another shape', function () {
        render(":::badge\n:::\n", new DirectiveRegistry(['badge:text']));
    })->throws(UnknownDirective::class, 'Unknown container directive "badge"');

    it('accepts the same name in the shape it was registered for', function () {
        expect(render("::badge\n", new DirectiveRegistry(['badge:leaf'])))->toContain('data-directive="badge"');
    });

    it('matches whatever case the document used', function () {
        expect(render(":::COL\n:::\n", new DirectiveRegistry(['col'])))->toContain('data-directive="COL"');
    });

    it('accepts anything when no names are registered', function () {
        expect(render(":::whatever\n:::\n", new DirectiveRegistry()))->toContain('data-directive="whatever"');
    });
});

describe('the AST the parsers build', function () {
    it('exposes name, type, attributes and label on a block', function () {
        $found = directive(parse('::image[Chart]{src="a.png"}'), 'image');

        expect($found->getName())->toBe('image')
            ->and($found->getType())->toBe(DirectiveType::Leaf)
            ->and($found->getLabel())->toBe('Chart')
            ->and($found->getAttributes()->toArray())->toBe(['src' => 'a.png']);
    });

    it('writes itself back to source', function () {
        expect((string) directive(parse('::image[Chart]{src="a.png"}'), 'image'))
            ->toBe('::image[Chart]{src="a.png"}');
    });

    it('writes a text directive back to source', function () {
        expect((string) directive(parse('A :badge[X]{type=ok} b'), 'badge'))
            ->toBe(':badge[X]{type="ok"}');
    });

    it('gives a container no label', function () {
        expect(directive(parse(":::a\n:::"), 'a')->getLabel())->toBeNull();
    });
});