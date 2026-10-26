<?php

declare(strict_types=1);

use MarkdownOffice\CommonMark\Directive\AttributeNaming;

/**
 * The naming policy, on its own rather than through a rendered document.
 *
 * The rendered form is checked in {@see \MarkdownOffice\CommonMark\Directive\Tests\Feature\DirectiveRenderingTest};
 * what is checked here is the rule behind it.
 */
describe('the attribute naming', function () {
    it('puts a name into the data- namespace', function () {
        expect(AttributeNaming::Namespaced->attributeName('type'))->toBe('data-type');
    });

    it('leaves a name that is already namespaced alone', function () {
        expect(AttributeNaming::Namespaced->attributeName('data-type'))->toBe('data-type');
    });

    it('writes a name as it is when the attributes are written as the author wrote them', function () {
        expect(AttributeNaming::AsWritten->attributeName('type'))->toBe('type');
    });

    it('refuses nothing in the data- namespace', function () {
        // `onclick` lands on `data-onclick` there, which no browser runs.
        expect(AttributeNaming::Namespaced->allows('onclick'))->toBeTrue();
    });

    it('refuses an event handler when the attributes are written as the author wrote them', function () {
        expect(AttributeNaming::AsWritten->allows('onclick'))->toBeFalse()
            ->and(AttributeNaming::AsWritten->allows('ONCLICK'))->toBeFalse()
            ->and(AttributeNaming::AsWritten->allows('type'))->toBeTrue();
    });
});
