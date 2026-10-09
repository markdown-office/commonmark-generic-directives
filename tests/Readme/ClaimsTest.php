<?php

declare(strict_types=1);

use MarkdownOffice\CommonMark\Directive\Directive\DirectiveAttributes;
use MarkdownOffice\CommonMark\Directive\Directive\DirectiveRegistry;
use MarkdownOffice\CommonMark\Directive\DirectiveType;
use MarkdownOffice\CommonMark\Directive\Exception\UnknownDirective;
use MarkdownOffice\CommonMark\Directive\Node\DirectiveBlock;
use MarkdownOffice\CommonMark\Directive\Node\DirectiveInline;
use MarkdownOffice\CommonMark\Directive\Tests\Support\Readme;

/** The page every claim here is checked against. */
$readme = __DIR__ . '/../../README.md';

/**
 * The claims on the page that are prose rather than a worked example.
 *
 * {@see \MarkdownOffice\CommonMark\Directive\Tests\Readme\ExamplesTest} checks every
 * `markdown`/`html` pair. This covers the rest — the API listing, the error message, the
 * things the page says are *not* directives — so a sentence that becomes untrue is a
 * failing test rather than a surprised reader.
 */
describe('the README', function () use ($readme) {
    it('names the package the composer file declares', function () use ($readme) {
        $composer = composerIdentity(__DIR__ . '/../../composer.json');

        expect($composer['name'])->toBe('markdown-office/commonmark-generic-directives')
            ->and($composer['require']['php'] ?? null)->toBe('^8.2')
            ->and((string) file_get_contents($readme))
            ->toContain('composer require ' . $composer['name']);
    });
});

describe('the API the README lists', function () {
    it('behaves the way the API listing says for both node kinds', function () {
        $readme = (string) file_get_contents(__DIR__ . '/../../README.md');

        foreach (['getName', 'getType', 'getLabel', 'getAttributes', 'isContainer'] as $method) {
            expect($readme)->toContain($method);
        }

        // Every name on the page is called here, which is the claim worth checking:
        // `method_exists` on a literal is decided before the suite runs.
        $block = directive(parse("::l[a]{b=1}"), 'l');
        $inline = directive(parse('A :t[a]{b=1} b'), 't');

        foreach ([$block, $inline] as $node) {
            expect($node->getName())->toBeString()
                ->and($node->getType())->toBeInstanceOf(DirectiveType::class)
                ->and($node->getLabel())->toBeString()
                ->and($node->getAttributes())->toBeInstanceOf(DirectiveAttributes::class);
        }

        expect($block->isContainer())->toBeFalse()
            ->and($inline->isContainer())->toBeFalse();
    });

    it('has every typed accessor the README promises, with the behaviour it describes', function () {
        $attributes = DirectiveAttributes::parse(
            'width=400 ratio=0.5 on=yes off=no flag tags="a b" wide missing'
        );

        expect($attributes->getString('title', 'Untitled'))->toBe('Untitled')
            ->and($attributes->getInt('width', 400))->toBe(400)
            ->and($attributes->getFloat('ratio'))->toBe(0.5)
            ->and($attributes->getBool('open', false))->toBeFalse()
            ->and($attributes->getBool('on'))->toBeTrue()
            ->and($attributes->getBool('off'))->toBeFalse()
            ->and($attributes->getBool('flag'))->toBeTrue()
            ->and($attributes->getList('tags'))->toBe(['a', 'b'])
            ->and($attributes->has('width'))->toBeTrue()
            ->and($attributes->has('title'))->toBeFalse()
            ->and($attributes->getString('wide', 'none'))->toBe('none')
            ->and($attributes->toArray())->toHaveKey('width');
    });

    it('round-trips through __toString as the README says', function () {
        $source = '::image[Chart]{src="a.png"}';

        expect((string) directive(parse($source), 'image'))->toBe($source);
    });
});

describe('the attribute forms the README lists', function () {
    it('reads each one it prints', function () {
        expect(DirectiveAttributes::parse('width=50%')->toArray())->toBe(['width' => '50%'])
            ->and(DirectiveAttributes::parse('title="a b c"')->toArray())->toBe(['title' => 'a b c'])
            ->and(DirectiveAttributes::parse('title=\'a "b" c\'')->toArray())->toBe(['title' => 'a "b" c'])
            ->and(DirectiveAttributes::parse('wide')->toArray())->toBe(['wide' => true])
            ->and(DirectiveAttributes::parse('#lead .terse')->toArray())
            ->toBe(['id' => 'lead', 'class' => 'terse']);
    });

    it('treats the bare shorthand as the same thing it prints', function () {
        $inBraces = directive(parse('A :t{#lead .terse} b'), 't')->getAttributes()->toArray();
        $bare = directive(parse('A :t#lead.terse b'), 't')->getAttributes()->toArray();

        expect($bare)->toBe($inBraces);
    });

    it('accumulates classes rather than replacing them', function () {
        expect(DirectiveAttributes::parse('.a.b.c')->getString('class'))->toBe('a b c');
    });
});

describe('the registry behaviour the README describes', function () {
    it('raises the message the README quotes', function () {
        render(":::columns\n:::\n", new DirectiveRegistry(['col', 'callout', 'badge:text']));
    })->throws(
        UnknownDirective::class,
        'Unknown container directive "columns". Registered: badge:text, callout, col.'
    );

    it('matches names case-insensitively, as the README says', function () {
        expect(render(":::COL\n:::\n", new DirectiveRegistry(['col'])))
            ->toContain('data-directive="COL"');
    });

    it('allows a bare name in every shape and a suffixed one in only that shape', function () {
        $registry = new DirectiveRegistry(['col', 'badge:text']);

        expect($registry->has('col', DirectiveType::Container))->toBeTrue()
            ->and($registry->has('col', DirectiveType::Text))->toBeTrue()
            ->and($registry->has('badge', DirectiveType::Text))->toBeTrue()
            ->and($registry->has('badge', DirectiveType::Container))->toBeFalse();
    });

    it('is permissive with no registry, as the README says', function () {
        expect(render(":::anything\n:::\n", new DirectiveRegistry()))
            ->toContain('data-directive="anything"');
    });
});

describe('the shapes the README tabulates', function () {
    it('gives each shape the node class it claims', function () {
        $document = parse(":t[x]\n\n::l{a=1}\n\n:::c\nbody\n:::");

        $byName = [];

        foreach (directives($document) as $node) {
            $byName[$node->getName()] = $node;
        }

        expect($byName['t'])->toBeInstanceOf(DirectiveInline::class)
            ->and($byName['l'])->toBeInstanceOf(DirectiveBlock::class)
            ->and($byName['c'])->toBeInstanceOf(DirectiveBlock::class)
            ->and($byName['l']->getLabel())->toBeNull()
            ->and($byName['c']->getLabel())->toBeNull();
    });

    it('closes a container on at least as many colons as it opened with', function () {
        expect(render("::::outer\n:::\nstill inside\n::::"))->toContain('still inside');
    });
});

describe('the things the README says are not directives', function () {
    it('leaves each one alone, for the reason it gives', function () {
        expect(render('Note: prose'))->not->toContain('data-directive')
            ->and(render('3:2 and 10:30'))->not->toContain('data-directive')
            ->and(render('status:error'))->not->toContain('data-directive')
            ->and(render("    :::a"))->not->toContain('data-directive')
            ->and(render("```\n:::\n```"))->not->toContain('data-directive');
    });

    it('says the fence protection is structural, and it is', function () {
        // CommonMark does not look for block starts inside a non-container block, so the
        // extension never gets a say. Raising or lowering its priority changes nothing.
        expect(render("```\n:::a\n```"))->not->toContain('data-directive');
    });
});

describe('the HTML claims the README makes', function () {
    it('namespaces author attributes', function () {
        expect(render('A :t{onclick="x()"} b'))->toContain('data-onclick="x()"');
    });

    it('drops an attribute that would land on data-directive', function () {
        expect(render(":::a{data-directive=\"b\"}\nx\n:::"))
            ->toBe('<div data-directive="a"><p>x</p></div>' . "\n");
    });
});

describe('the gates the README quotes', function () {
    it('still holds', function () {
        // The page makes two quantitative claims about itself. Cheap to check, and a
        // claim that rots is worse than no claim.
        $page = (string) file_get_contents(__DIR__ . '/../../README.md');

        expect($page)->toContain('100% covered')
            ->and($page)->toContain('PHPStan level 9')
            ->and((string) file_get_contents(__DIR__ . '/../../phpstan.neon.dist'))
            ->toContain('level: 9');
    });
});

describe('the README extractor', function () {
    it('finds the paired examples it claims to find', function () {
        expect(Readme::examples(__DIR__ . '/../../README.md'))->toHaveCount(3);
    });

    it('ignores a markdown block with no html block after it', function () {
        $path = \tempnam(\sys_get_temp_dir(), 'readme') . '.md';
        \file_put_contents($path, "```markdown\n:badge[X]\n```\n");

        expect(Readme::examples($path))->toBeEmpty();

        @\unlink($path);
    });

    it('does not pair across a prose line', function () {
        $path = \tempnam(\sys_get_temp_dir(), 'readme') . '.md';
        \file_put_contents($path, "```markdown\n:badge[X]\n```\n\nSome prose.\n\n```html\n<p>x</p>\n```\n");

        expect(Readme::examples($path))->toBeEmpty();

        @\unlink($path);
    });

    it('reports the line an example starts on, so a failure points at the page', function () {
        foreach (Readme::examples(__DIR__ . '/../../README.md') as $example) {
            expect($example['line'])->toBeGreaterThan(0);
        }
    });
});