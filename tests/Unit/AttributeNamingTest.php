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
    it('writes a name as it is when the attributes are written as the author wrote them', function () {
        expect(AttributeNaming::AsWritten->attributeName('type'))->toBe('type');
    });

    it('puts a name into the data- namespace', function () {
        expect(AttributeNaming::Namespaced->attributeName('type'))->toBe('data-type');
    });

    it('leaves a name that is already namespaced alone', function () {
        expect(AttributeNaming::Namespaced->attributeName('data-type'))->toBe('data-type');
    });

    it('refuses an event handler when the attributes are written as the author wrote them', function () {
        expect(AttributeNaming::AsWritten->allows('onclick'))->toBeFalse()
            ->and(AttributeNaming::AsWritten->allows('ONCLICK'))->toBeFalse()
            ->and(AttributeNaming::AsWritten->allows('only'))->toBeFalse()
            ->and(AttributeNaming::AsWritten->allows('type'))->toBeTrue();
    });

    it('refuses a name HTML does not accept when the attributes are written as the author wrote them', function () {
        expect(AttributeNaming::AsWritten->allows('12'))->toBeFalse()
            ->and(AttributeNaming::AsWritten->allows('1a'))->toBeFalse()
            ->and(AttributeNaming::AsWritten->allows('xml:lang'))->toBeTrue();
    });

    it('refuses nothing in the data- namespace', function () {
        // `onclick` lands on `data-onclick` there, which no browser runs, and the
        // prefix makes every name one HTML accepts.
        expect(AttributeNaming::Namespaced->allows('onclick'))->toBeTrue()
            ->and(AttributeNaming::Namespaced->allows('12'))->toBeTrue();
    });
});
