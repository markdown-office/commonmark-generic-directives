<?php

declare(strict_types=1);

use MarkdownOffice\CommonMark\Directive\Directive\DirectiveRegistry;
use MarkdownOffice\CommonMark\Directive\DirectiveType;
use MarkdownOffice\CommonMark\Directive\Exception\UnknownDirective;

describe('an empty registry', function () {
    it('is empty', function () {
        expect(new DirectiveRegistry())->toBeEmpty();
    });

    it('accepts anything, which is what the RFC describes', function () {
        $registry = new DirectiveRegistry();

        expect(fn () => $registry->assertKnown('whatever', DirectiveType::Container))
            ->not->toThrow(UnknownDirective::class)
            ->and(fn () => $registry->assertKnown('other', DirectiveType::Text))
            ->not->toThrow(UnknownDirective::class);
    });
});

describe('registration', function () {
    it('registers a name for every shape', function () {
        $registry = new DirectiveRegistry(['col']);

        expect($registry)->not->toBeEmpty();
        expect($registry->has('col', DirectiveType::Container))->toBeTrue()
            ->and($registry->has('col', DirectiveType::Leaf))->toBeTrue()
            ->and($registry->has('col', DirectiveType::Text))->toBeTrue();
    });

    it('narrows a name to one shape', function () {
        $registry = new DirectiveRegistry(['badge:leaf']);

        expect($registry->has('badge', DirectiveType::Leaf))->toBeTrue()
            ->and($registry->has('badge', DirectiveType::Container))->toBeFalse()
            ->and($registry->has('badge', DirectiveType::Text))->toBeFalse();
    });

    it('registers several at once', function () {
        $registry = (new DirectiveRegistry())->allowMany(['col', 'callout', 'badge:text']);

        expect($registry->has('col', DirectiveType::Container))->toBeTrue()
            ->and($registry->has('callout', DirectiveType::Container))->toBeTrue()
            ->and($registry->has('badge', DirectiveType::Text))->toBeTrue()
            ->and($registry->has('badge', DirectiveType::Container))->toBeFalse();
    });

    it('matches a name whatever its case, since a document cannot be asked to care', function () {
        expect((new DirectiveRegistry(['col']))->has('COL', DirectiveType::Container))->toBeTrue();
    });

    it('does not match a name that was never registered', function () {
        expect((new DirectiveRegistry(['col']))->has('callout', DirectiveType::Container))->toBeFalse();
    });
});

describe('rejecting an unknown name', function () {
    it('throws, naming the directive and its shape', function () {
        new DirectiveRegistry(['col'])->assertKnown('nope', DirectiveType::Container);
    })->throws(UnknownDirective::class, 'Unknown container directive "nope"');

    it('names the alternatives, because a message that only says "no" is not a fix', function () {
        new DirectiveRegistry(['col', 'callout', 'badge:text'])->assertKnown('nope', DirectiveType::Text);
    })->throws(UnknownDirective::class, 'Registered: badge:text, callout, col.');

    it('distinguishes a name registered for another shape from an unknown one', function () {
        new DirectiveRegistry(['badge:leaf'])->assertKnown('badge', DirectiveType::Text);
    })->throws(UnknownDirective::class, 'Unknown text directive "badge". Registered: badge:leaf.');

    it('accepts anything when nothing is registered', function () {
        // An empty registry is the permissive mode, and the reason a caller who has not
        // said what exists is not met with an error about what does not.
        expect(fn () => (new DirectiveRegistry())->assertKnown('x', DirectiveType::Text))
            ->not->toThrow(UnknownDirective::class);
    });

    it('describes an every-shape name without a suffix', function () {
        expect((new DirectiveRegistry(['col', 'note:text']))->describe())
            ->toBe(['col', 'note:text']);
    });
});