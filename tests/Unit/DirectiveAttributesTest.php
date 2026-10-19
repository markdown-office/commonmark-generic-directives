<?php

declare(strict_types=1);

use MarkdownOffice\CommonMark\Directive\Directive\DirectiveAttributes;

describe('parsing values', function () {
    it('reads a bare value that runs to whitespace', function () {
        expect(DirectiveAttributes::parse('width=50%')->toArray())->toBe(['width' => '50%']);
    });

    it('keeps whitespace inside a double-quoted value', function () {
        expect(DirectiveAttributes::parse('title="a b  c"')->toArray())->toBe(['title' => 'a b  c']);
    });

    it('keeps double quotes inside a single-quoted value', function () {
        expect(DirectiveAttributes::parse('x=\'say "hi"\'')->toArray())->toBe(['x' => 'say "hi"']);
    });

    it('treats an attribute with no value as present', function () {
        expect(DirectiveAttributes::parse('wide')->toArray())->toBe(['wide' => true]);
    });

    it('treats an empty value as present rather than as text', function () {
        expect(DirectiveAttributes::parse('wide=')->toArray())->toBe(['wide' => '']);
    });

    it('reads several attributes', function () {
        expect(DirectiveAttributes::parse('a=1 b=two c')->toArray())
            ->toBe(['a' => '1', 'b' => 'two', 'c' => true]);
    });

    it('keeps a dash and a colon inside a name', function () {
        expect(DirectiveAttributes::parse('data-x=1 xml:lang="en"')->toArray())
            ->toBe(['data-x' => '1', 'xml:lang' => 'en']);
    });

    it('reads an empty block as no attributes', function () {
        expect(DirectiveAttributes::parse('')->toArray())->toBe([]);
    });

    it('tolerates whitespace of any kind between attributes', function () {
        expect(DirectiveAttributes::parse("a=1\t b=2\nc=3")->toArray())
            ->toBe(['a' => '1', 'b' => '2', 'c' => '3']);
    });

    it('reads a value that contains an equals sign', function () {
        expect(DirectiveAttributes::parse('expr=a=b')->toArray())->toBe(['expr' => 'a=b']);
    });

    it('keeps an unterminated quoted value rather than discarding it', function () {
        expect(DirectiveAttributes::parse('title="never closed')->toArray())
            ->toBe(['title' => 'never closed']);
    });
});

describe('the # and . shorthands', function () {
    it('reads # as an id', function () {
        expect(DirectiveAttributes::parse('#lead')->toArray())->toBe(['id' => 'lead']);
    });

    it('reads . as a class', function () {
        expect(DirectiveAttributes::parse('.terse')->toArray())->toBe(['class' => 'terse']);
    });

    it('accumulates several classes rather than replacing them', function () {
        expect(DirectiveAttributes::parse('.a.b.c')->getString('class'))->toBe('a b c');
    });

    it('stops an id at the dot so #lead.terse is an id and a class', function () {
        expect(DirectiveAttributes::parse('#lead.terse')->toArray())
            ->toBe(['id' => 'lead', 'class' => 'terse']);
    });

    it('mixes shorthands and attributes', function () {
        expect(DirectiveAttributes::parse('#lead.terse{type=ok}')->toArray())->toBe([
            'id' => 'lead',
            'class' => 'terse',
            'type' => 'ok',
        ]);
    });

    it('skips a shorthand with no name after it', function () {
        expect(DirectiveAttributes::parse('# .a')->toArray())->toBe(['class' => 'a']);
    });
});

describe('reading typed values', function () {
    it('reads a string', function () {
        expect(DirectiveAttributes::parse('type=info')->getString('type'))->toBe('info');
    });

    it('returns the default for a missing string', function () {
        expect(DirectiveAttributes::parse('a=1')->getString('type', 'note'))->toBe('note');
    });

    it('returns the default for a present-but-valueless attribute', function () {
        expect(DirectiveAttributes::parse('type')->getString('type', 'note'))->toBe('note');
    });

    it('reads an integer', function () {
        expect(DirectiveAttributes::parse('width=400')->getInt('width'))->toBe(400);
    });

    it('returns the default for an integer that is not one', function () {
        expect(DirectiveAttributes::parse('width=wide')->getInt('width', 7))->toBe(7);
    });

    it('reads a negative integer', function () {
        expect(DirectiveAttributes::parse('offset=-2')->getInt('offset'))->toBe(-2);
    });

    it('reads a float', function () {
        expect(DirectiveAttributes::parse('ratio=0.5')->getFloat('ratio'))->toBe(0.5);
    });

    it('reads a boolean that was written as a word', function () {
        expect(DirectiveAttributes::parse('on=yes off=no')->getBool('on'))->toBeTrue();
        expect(DirectiveAttributes::parse('on=yes off=no')->getBool('off'))->toBeFalse();
    });

    it('reads a bare attribute as true', function () {
        expect(DirectiveAttributes::parse('flag')->getBool('flag'))->toBeTrue();
    });

    it('returns the default for a missing boolean', function () {
        expect(DirectiveAttributes::parse('a=1')->getBool('on', true))->toBeTrue()
            ->and(DirectiveAttributes::parse('a=1')->getBool('on'))->toBeFalse();
    });

    it('returns the default for a boolean that is neither word nor number', function () {
        expect(DirectiveAttributes::parse('x=perhaps')->getBool('x', true))->toBeTrue();
        expect(DirectiveAttributes::parse('x=perhaps')->getBool('x', false))->toBeFalse();
    });

    it('splits a list on spaces and commas', function () {
        expect(DirectiveAttributes::parse('tags="a b,c  d"')->getList('tags'))
            ->toBe(['a', 'b', 'c', 'd']);
    });

    it('returns an empty list for a missing attribute', function () {
        expect(DirectiveAttributes::parse('a=1')->getList('tags'))->toBe([]);
    });
});

describe('carrying attributes', function () {
    // Two attributes, written once: counting them, iterating them and merging them are
    // three views of the same object, so a change to what `parse()` accepts should have
    // to be made in one place.
    $two = 'a=1 b=2';

    it('reports whether an attribute is present', function () {
        expect(DirectiveAttributes::parse('a=1')->has('a'))->toBeTrue()
            ->and(DirectiveAttributes::parse('a=1')->has('b'))->toBeFalse();
    });

    it('counts its attributes', function () use ($two) {
        expect(DirectiveAttributes::parse($two))->toHaveCount(2);
    });

    it('can be iterated as name to value', function () use ($two) {
        expect(\iterator_to_array(DirectiveAttributes::parse($two)))
            ->toBe(['a' => '1', 'b' => '2']);
    });

    it('serialises to JSON as a plain object', function () {
        expect(\json_encode(DirectiveAttributes::parse('a=1 wide')))
            ->toBe('{"a":"1","wide":true}');
    });

    it('merges with another set, letting the other win', function () use ($two) {
        expect(DirectiveAttributes::parse($two)->mergedWith(DirectiveAttributes::parse('b=3 c=4'))->toArray())
            ->toBe(['a' => '1', 'b' => '3', 'c' => '4']);
    });

    it('accumulates class across a merge rather than replacing it', function () {
        expect(DirectiveAttributes::parse('.a')->mergedWith(DirectiveAttributes::parse('.b'))->getString('class'))
            ->toBe('a b');
    });

    it('keeps an attribute name that PHP cast to an integer', function () {
        // The merge is where the two spellings of a directive come together, so it is
        // where a numeric name would be renumbered into one the author never wrote.
        $merged = DirectiveAttributes::parse('12=twelve')->mergedWith(DirectiveAttributes::parse('3=three'));

        expect(\array_keys($merged->toArray()))->toBe([12, 3])
            ->and($merged->get('12'))->toBe('twelve')
            ->and($merged->get('3'))->toBe('three');
    });
});

describe('round-tripping to source', function () {
    it('writes an attribute with no value as a bare name', function () {
        expect((string) DirectiveAttributes::parse('wide'))->toBe('{wide}');
    });

    it('writes a value in quotes', function () {
        expect((string) DirectiveAttributes::parse('type=info'))->toBe('{type="info"}');
    });

    it('writes an id and classes as their shorthands', function () {
        expect((string) DirectiveAttributes::parse('#lead .a .b'))->toBe('{#lead .a .b}');
    });

    it('writes nothing for no attributes', function () {
        expect((string) DirectiveAttributes::none())->toBe('');
    });

    it('survives being parsed again', function () {
        $once = DirectiveAttributes::parse('#lead.a b=2');
        $twice = DirectiveAttributes::parse(\substr((string) $once, 1, -1));

        expect($twice->toArray())->toBe($once->toArray());
    });

    it('survives a value carrying the quote the writer would have preferred', function () {
        // The single-quoted form is what the README offers for a value containing a double
        // quote, so writing it back out has to pick the other quote rather than escape one
        // the parser has no escape for.
        $once = DirectiveAttributes::parse('title=\'a "b" c\'');
        $twice = DirectiveAttributes::parse(\substr((string) $once, 1, -1));

        expect((string) $once)->toBe('{title=\'a "b" c\'}')
            ->and($twice->toArray())->toBe($once->toArray());
    });

    it('survives a value carrying the quote it was written with', function () {
        $once = DirectiveAttributes::parse('title="it\'s here"');
        $twice = DirectiveAttributes::parse(\substr((string) $once, 1, -1));

        expect($twice->toArray())->toBe($once->toArray());
    });
});
