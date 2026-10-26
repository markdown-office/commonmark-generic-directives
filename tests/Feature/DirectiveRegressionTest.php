<?php

declare(strict_types=1);

use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use MarkdownOffice\CommonMark\Directive\Node\DirectiveInline;
use MarkdownOffice\CommonMark\Directive\Node\DirectiveBlock;

/**
 * The cases that only showed up once the thing ran against real Markdown.
 *
 * Each of these was a bug at some point. They are here rather than folded into the
 * general tests because the reason each one matters is not obvious from the assertion.
 */
describe('a leaf never eats the line after it', function () {
    it('leaves the next paragraph alone', function () {
        expect(trim(render("::image\nafter")))
            ->toBe('<div data-directive="image"></div>' . "\n" . '<p>after</p>');
    });

    it('leaves a heading alone', function () {
        expect(trim(render("::image\n# H")))
            ->toBe('<div data-directive="image"></div>' . "\n" . '<h1>H</h1>');
    });

    it('leaves another leaf alone', function () {
        expect(trim(render("::a\n::b")))
            ->toBe('<div data-directive="a"></div>' . "\n" . '<div data-directive="b"></div>');
    });

    it('behaves the same whether or not a blank line separates them', function () {
        expect(trim(render("::a\n\nafter")))->toBe(trim(render("::a\nafter")));
    });
});

describe('a container does eat the lines it should', function () {
    it('takes everything up to its fence, blank lines included', function () {
        expect(trim(render(":::a\none\n\ntwo\n:::")))
            ->toBe('<div data-directive="a"><p>one</p>' . "\n" . '<p>two</p></div>');
    });

    it('still stops at its fence rather than running to the end', function () {
        expect(trim(render(":::a\nin\n:::\nout")))->toEndWith('<p>out</p>');
    });

    it('takes a nested leaf', function () {
        expect(render(":::a\n::b{x=1}\n:::"))->toContain('<div data-directive="b" x="1">');
    });

    it('takes a fenced code block verbatim', function () {
        expect(render(":::a\n```\n:::not\n```\n:::"))
            ->toContain('<pre><code>:::not');
    });
});

describe('a container leaves the document alone after it closes', function () {
    it('closes the last container without eating what follows', function () {
        $document = parse(":::a\nin\n:::\nafter");

        expect(directive($document, 'a')->children())->toHaveCount(1);
    });

    it('survives two containers in a row', function () {
        expect(trim(render(":::a\n1\n:::\n:::b\n2\n:::")))
            ->toBe('<div data-directive="a"><p>1</p></div>' . "\n" . '<div data-directive="b"><p>2</p></div>');
    });

    it('survives a container between two paragraphs', function () {
        expect(trim(render("before\n\n:::a\nin\n:::\n\nafter")))
            ->toBe('<p>before</p>' . "\n" . '<div data-directive="a"><p>in</p></div>' . "\n" . '<p>after</p>');
    });
});

describe('nesting depth', function () {
    it('keeps a deep chain of siblings in the right order', function () {
        $document = parse(":::a\n:::b\n:::c\nx\n:::\n:::\n:::");

        $names = array_map(
            static fn (DirectiveBlock|DirectiveInline $node): ?string => $node->getName(),
            directives($document)
        );

        expect($names)->toBe(['a', 'b', 'c']);
    });

    it('gives the outermost container every sibling and body', function () {
        $outer = directive(parse(":::a\n:::b\n1\n:::\n2\n:::c\n3\n:::\n4\n:::"), 'a');

        expect($outer->children())->toHaveCount(4);
    });

    it('reports no container as still open once parsing has finished', function () {
        // The counter is parse state; if it leaked it would make the second, identical
        // container in a document refuse to close.
        $outer = directive(parse(":::a\n:::b\nx\n:::\ny\n:::\n\n:::a\nz\n:::"), 'a');

        expect($outer)->toBeInstanceOf(DirectiveBlock::class);

        \assert($outer instanceof DirectiveBlock);

        expect($outer->hasOpenNestedContainer())->toBeFalse();
    });
});

describe('a container nested through another block', function () {
    // A blockquote between the two is enough: the container that waits for the nested one
    // is an ancestor rather than a parent, so anything that asks only for a parent leaves
    // the count permanently one high and the outer fence is never honoured again.
    $nested = ":::a\n> :::b\n> x\n> :::\n:::\nAFTER";

    it('closes the outer one, which is what its own fence says to do', function () use ($nested) {
        expect(trim(render($nested)))->toBe(
            '<div data-directive="a"><blockquote>' . "\n"
            . '<div data-directive="b"><p>x</p></div>' . "\n"
            . '</blockquote></div>' . "\n"
            . '<p>AFTER</p>'
        );
    });

    it('leaves no container waiting for a child once parsing has finished', function () use ($nested) {
        $outer = directive(parse($nested), 'a');

        \assert($outer instanceof DirectiveBlock);

        expect($outer->hasOpenNestedContainer())->toBeFalse()
            ->and($outer->children())->toHaveCount(1);
    });

    it('counts and un-counts the same container at any depth', function () {
        $deep = ":::a\n> > :::b\n> > x\n> > :::\n:::\nAFTER";
        $outer = directive(parse($deep), 'a');

        \assert($outer instanceof DirectiveBlock);

        expect($outer->hasOpenNestedContainer())->toBeFalse()
            ->and(trim(render($deep)))->toEndWith('<p>AFTER</p>');
    });
});

describe('the shape of what was built', function () {
    it('gives a container block children and a leaf none', function () {
        $document = parse(":::a\nbody\n:::\n\n::b");

        expect(directive($document, 'a')->children())->not->toBeEmpty()
            ->and(directive($document, 'b')->children())->toBeEmpty();
    });

    it('parses the body of a container as a block, not as literal text', function () {
        $found = directive(parse(":::a\n# Heading\n:::"), 'a');

        expect($found->lastChild())->toBeInstanceOf(Heading::class);
    });

    it('keeps a text directive inside the block it was written in', function () {
        $document = parse("# Title with :badge[OK]");

        expect(directive($document, 'badge')->parent())->toBeInstanceOf(Heading::class);
    });

    it('exposes the label both as a string and as text', function () {
        $found = directive(parse('A :badge[X] b'), 'badge');

        expect($found->getLabel())->toBe('X')
            ->and(textOf($found))->toBe('X');
    });

    it('has no text child when there is no label', function () {
        expect(directive(parse('A :icon{x=1} b'), 'icon')->children())->toBeEmpty();
    });
});
