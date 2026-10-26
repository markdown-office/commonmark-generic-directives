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
    // Three tests ask about this one document: what it renders to, how its children are
    // shaped, and whether the outer fence is left alone by the inner one. Written once
    // so that a change to the fixture cannot quietly mean something different in one of
    // them.
    $nested = ":::outer\n:::inner\ndeep\n:::\nback\n:::";

    it('nests a container inside a container of the same fence length', function () use ($nested) {
        $html = render($nested);

        expect(trim($html))->toBe(
            '<div data-directive="outer"><div data-directive="inner"><p>deep</p></div>' . "\n"
            . '<p>back</p></div>'
        );
    });

    it('keeps the content after an inner container inside the outer one', function () use ($nested) {
        $document = parse($nested);

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

    it('closes the outer one again once the inner one is closed', function () use ($nested) {
        // The failure this guards against is the outer container consuming the inner's
        // closing fence, which would drop everything after it out of the outer block.
        $outer = directive(parse($nested), 'outer');

        expect(textOf($outer))->toContain('back');
    });

    it('lets a container opened with four colons contain a three-colon fence', function () {
        expect(render("::::outer\n:::inner\nx\n:::\n::::"))
            ->toContain('<div data-directive="outer">')
            ->toContain('<div data-directive="inner">');
    });
});

describe('a container with no name', function () {
    it('is recognised, and has no name to read', function () {
        $found = directives(parse("::: {.myClass}\nsome markdown\n:::"))[0];

        expect($found)->toBeInstanceOf(DirectiveBlock::class)
            ->and($found->getName())->toBeNull()
            ->and($found->getType())->toBe(DirectiveType::Container)
            ->and($found->getAttributes()->getString('class'))->toBe('myClass');
    });

    it('parses its body as Markdown, because it is a container like any other', function () {
        expect(render("::: {.myClass}\nsome **markdown**\n:::"))
            ->toBe('<div class="myClass"><p>some <strong>markdown</strong></p></div>' . "\n");
    });

    it('is not something a registry can be missing a name for', function () {
        expect(render("::: {.myClass}\nbody\n:::", new DirectiveRegistry(['col'])))
            ->toBe('<div class="myClass"><p>body</p></div>' . "\n");
    });

    it('closes on a bare :::, the line it would have opened had it a name', function () {
        $found = directives(parse("::: {.myClass}\nbody\n:::"))[0];

        expect($found->children())->toHaveCount(1)
            ->and(textOf($found))->toBe('body');
    });

    it('leaves a bare ::: on a line of its own as text', function () {
        // Nothing to close, so nothing is claimed: a fence that also opened a container
        // would need a second fence to end it.
        expect(render(":::\nbody\n:::"))->toBe("<p>:::\nbody\n:::</p>\n");
    });

    it('closes a named container on the same bare ::: without opening a second one', function () {
        $found = directives(parse(":::a\nbody\n:::\nafter\n"));

        expect($found)->toHaveCount(1)
            ->and(textOf($found[0]))->toBe('body');
    });
});

describe('colons at the end of the opening line', function () {
    it('are read as nothing at all, as the RFC says they are', function () {
        $found = directives(parse(
            ":::::::::::: SPOILER :::::::::::::\nWe're going to spoil it\n"
            . \str_repeat(':', 34)
        ))[0];

        expect($found->getName())->toBe('SPOILER')
            ->and($found->getType())->toBe(DirectiveType::Container)
            ->and(textOf($found))->toBe("We're going to spoil it");
    });

    it('do not count towards the fence the container closes on', function () {
        // Four colons opened it and the trailing four are decorative, so the RFC's rule that
        // a closing line is longer than the opening line still says three is too few.
        expect(trim(render(":::: a ::::\nbody\n\n:::\nstill inside")))
            ->toBe('<div data-directive="a"><p>body</p>' . "\n" . '<p>:::' . "\n" . 'still inside</p></div>')
            ->and(trim(render(":::: a ::::\nbody\n\n::::\nafter")))
            ->toBe('<div data-directive="a"><p>body</p></div>' . "\n" . '<p>after</p>');
    });

    it('may be written after the attributes rather than after the name', function () {
        expect(trim(render(":::note{type=info}:::\nbody\n:::")))
            ->toBe('<div data-directive="note" type="info"><p>body</p></div>');
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
            '<div data-directive="image" src="a.png"></div>' . "\n" . '<p>after</p>'
        );
    });

    it('carries a label', function () {
        expect(directive(parse('::image[Revenue chart]'), 'image')->getLabel())->toBe('Revenue chart');
    });

    it('takes the name the RFC lets be spaced away from the fence', function () {
        expect(trim(render(':: image[Revenue chart]')))->toBe('<div data-directive="image">Revenue chart</div>');
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

    it('leaves a colon before a space alone even with no word in front of it', function () {
        // The space is the rule, not the word in front of it: this is the case only the
        // space can reject, which is why a block directive may have one and this may not.
        expect(render(': note here'))->not->toContain('data-directive');
    });

    it('leaves a colon between digits alone', function () {
        expect(render('The ratio is 3:2 and the time is 10:30.'))->not->toContain('data-directive');
    });

    it('leaves a word before the colon alone', function () {
        expect(render('See status:error for details.'))->not->toContain('data-directive');
    });

    it('leaves a fence tail in a paragraph alone', function () {
        // A leaf or container fence that lost its opening line leaves its tail behind, and
        // the second colon of that tail is the only position left that could start a text
        // directive. The pattern refuses the first of the pair, so the guard has to refuse
        // the second — which is the character immediately before, not one further back.
        expect(render('text x::badge[y] more'))->not->toContain('data-directive')
            ->and(render('text :::badge[y] more'))->not->toContain('data-directive');
    });

    it('takes a directive after a colon in prose, because a colon before a space is not a fence', function () {
        // The other side of the same guard. `Note: :badge[x]` is the same shape as the URL and
        // the `status:error` above, and refusing it would be refusing the author's prose over
        // a two-character difference from the fence tail it does refuse.
        expect(render('Note: :badge[x]'))->toContain('data-directive="badge"');
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
    // The same leaf directive twice over: one test for what the node exposes, one for
    // whether it writes itself back out as the source it was read from.
    $leaf = '::image[Chart]{src="a.png"}';

    it('exposes name, type, attributes and label on a block', function () use ($leaf) {
        $found = directive(parse($leaf), 'image');

        expect($found->getName())->toBe('image')
            ->and($found->getType())->toBe(DirectiveType::Leaf)
            ->and($found->getLabel())->toBe('Chart')
            ->and($found->getAttributes()->toArray())->toBe(['src' => 'a.png']);
    });

    it('writes itself back to source', function () use ($leaf) {
        expect((string) directive(parse($leaf), 'image'))
            ->toBe($leaf);
    });

    it('writes a text directive back to source', function () {
        expect((string) directive(parse('A :badge[X]{type=ok} b'), 'badge'))
            ->toBe(':badge[X]{type="ok"}');
    });

    it('gives a container no label', function () {
        expect(directive(parse(":::a\n:::"), 'a')->getLabel())->toBeNull();
    });
});
