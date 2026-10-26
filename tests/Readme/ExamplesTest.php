<?php

declare(strict_types=1);

use MarkdownOffice\CommonMark\Directive\AttributeNaming;
use MarkdownOffice\CommonMark\Directive\Tests\Support\Readme;

/**
 * Every worked example in README.md, executed.
 *
 * An example that stops matching the library fails here rather than misleading the next
 * reader, which is the whole point. See {@see Readme} for the convention.
 */
/** The page the examples are read from. */
$readme = repoPath('README.md');

describe('the worked examples in the README', function () use ($readme) {
    it('has at least one example to check', function () use ($readme) {
        expect(Readme::examples($readme))->not->toBeEmpty();
    });

    it('converts exactly as the page says it does', function (string $markdown, string $html, AttributeNaming $naming) {
        expect(render($markdown, null, $naming))->toBe($html . "\n");
    })->with(pageDataset('README.md', Readme::examples($readme)));

    it('does not claim an example the page does not contain', function () use ($readme) {
        $page = (string) file_get_contents($readme);

        // A page can show the same example twice by accident; the counts are not the
        // check. What matters is that every paired example is a real pair.
        expect(\substr_count($page, '```markdown'))->toBeGreaterThanOrEqual(
            \count(Readme::examples($readme))
        );
    });
});
