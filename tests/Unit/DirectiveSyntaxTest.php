<?php

declare(strict_types=1);

use MarkdownOffice\CommonMark\Directive\Directive\DirectiveSyntax;
use MarkdownOffice\CommonMark\Directive\DirectiveType;

describe('the block pattern', function () {
    it('matches a container', function () {
        $match = blockMatch(':::callout');

        expect($match[1])->toBe(3)
            ->and($match[2])->toBe('callout');
    });

    it('matches a leaf', function () {
        $match = blockMatch('::image');

        expect($match[1])->toBe(2)
            ->and($match[2])->toBe('image');
    });

    it('allows more colons than the minimum', function () {
        expect(blockMatch('::::a')[1])->toBe(4)
            ->and(blockMatch('::::::a')[1])->toBe(6);
    });

    it('rejects a single colon, which is the inline shape', function () {
        expect(DirectiveSyntax::matchBlock(':badge[x]'))->toBeNull();
    });

    it('rejects a fence with no name', function () {
        expect(DirectiveSyntax::matchBlock(':::'))->toBeNull()
            ->and(DirectiveSyntax::matchBlock('::'))->toBeNull();
    });

    it('rejects a name that does not start with a letter', function () {
        expect(DirectiveSyntax::matchBlock('::1bad'))->toBeNull()
            ->and(DirectiveSyntax::matchBlock(':: bad'))->toBeNull();
    });

    it('reads a name with dashes and digits', function () {
        expect(blockMatch('::col-2')[2])->toBe('col-2');
    });

    it('captures a label', function () {
        expect(blockMatch('::image[Chart]')[3])->toBe('Chart');
    });

    it('reports an absent label as null rather than an empty string', function () {
        expect(blockMatch('::image')[3])->toBeNull();
    });

    it('captures attributes', function () {
        expect(blockMatch('::image{src="a.png" width=400}')[5])->toBe('{src="a.png" width=400}');
    });

    it('reads a label and attributes together', function () {
        $match = blockMatch('::image[Chart]{src="a.png"}');

        expect($match[3])->toBe('Chart')
            ->and($match[5])->toBe('{src="a.png"}');
    });

    it('allows trailing whitespace, which an editor leaves behind', function () {
        expect(DirectiveSyntax::matchBlock(":::a  \t"))->not->toBeNull();
    });

    it('rejects content after the directive, which is not a directive line', function () {
        expect(DirectiveSyntax::matchBlock(':::a trailing words'))->toBeNull();
    });

    it('rejects an unclosed label rather than guessing where it ends', function () {
        expect(DirectiveSyntax::matchBlock('::image[Chart'))->toBeNull();
    });

    it('reads bare shorthands after the name', function () {
        expect(blockMatch(':::note#lead.terse')[4])->toBe('#lead.terse');
    });
});

describe('the closing fence', function () {
    it('closes a three-colon container', function () {
        expect(\preg_match(DirectiveSyntax::closingPattern(3), ':::'))->toBe(1);
    });

    it('closes with more colons than it opened with', function () {
        expect(\preg_match(DirectiveSyntax::closingPattern(3), ':::::'))->toBe(1);
    });

    it('does not close with fewer colons than it opened with', function () {
        expect(\preg_match(DirectiveSyntax::closingPattern(4), ':::'))->toBe(0);
    });

    it('allows trailing whitespace', function () {
        expect(\preg_match(DirectiveSyntax::closingPattern(3), ':::   '))->toBe(1);
    });

    it('is not a fence with anything else on the line', function () {
        expect(\preg_match(DirectiveSyntax::closingPattern(3), ':::a'))->toBe(0);
    });
});

describe('merging the two places attributes can be written', function () {
    it('reads attributes written bare', function () {
        expect(DirectiveSyntax::attributes(null, '#lead.terse')->toArray())
            ->toBe(['id' => 'lead', 'class' => 'terse']);
    });

    it('reads attributes written in braces', function () {
        expect(DirectiveSyntax::attributes('{type=ok}')->toArray())->toBe(['type' => 'ok']);
    });

    it('reads both at once', function () {
        expect(DirectiveSyntax::attributes('{title="x"}', '#lead')->toArray())
            ->toBe(['id' => 'lead', 'title' => 'x']);
    });

    it('lets the braces win over a bare shorthand of the same name', function () {
        expect(DirectiveSyntax::attributes('{id=fromBraces}', '#fromBare')->getString('id'))
            ->toBe('fromBraces');
    });

    it('has no attributes when given neither', function () {
        expect(DirectiveSyntax::attributes(null)->toArray())->toBe([]);
    });
});

describe('the three shapes', function () {
    it('tells them apart by colon count alone', function () {
        expect(DirectiveType::fromColons(1))->toBe(DirectiveType::Text)
            ->and(DirectiveType::fromColons(2))->toBe(DirectiveType::Leaf)
            ->and(DirectiveType::fromColons(3))->toBe(DirectiveType::Container)
            ->and(DirectiveType::fromColons(9))->toBe(DirectiveType::Container);
    });

    it('knows which shapes are blocks', function () {
        expect(DirectiveType::Text->isBlock())->toBeFalse()
            ->and(DirectiveType::Leaf->isBlock())->toBeTrue()
            ->and(DirectiveType::Container->isBlock())->toBeTrue();
    });

    it('knows the shortest fence for each shape', function () {
        expect(DirectiveType::Text->minimumColons())->toBe(1)
            ->and(DirectiveType::Leaf->minimumColons())->toBe(2)
            ->and(DirectiveType::Container->minimumColons())->toBe(3);
    });
});