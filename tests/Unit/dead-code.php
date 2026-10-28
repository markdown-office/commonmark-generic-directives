<?php

declare(strict_types=1);

use MarkdownOffice\CommonMark\Directive\Directive\DirectiveSyntax;

/*
 * Claims that were wrong, kept so they cannot come back.
 *
 * A comment that says something the code does not do is read as a contract, and nothing
 * in the suite reads prose. Each phrase below is one that was corrected.
 */

/**
 * The files holding a phrase.
 *
 * Its own walk because it has to skip this file: the phrases below are written here.
 *
 * @return list<string>
 */
function sourcesWithClaim(string $claim): array
{
    $found = [];

    foreach (['src', 'tests', 'tools'] as $area) {
        $directory = repoPath($area);

        if (!\is_dir($directory)) {
            continue;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            if ($file->getExtension() !== 'php' || !$file->isFile() || $file->getFilename() === \basename(__FILE__)) {
                continue;
            }

            if (holdsClaim((string) \file_get_contents($file->getPathname()), $claim)) {
                $found[] = $area . '/' . $file->getFilename();
            }
        }
    }

    // The README is scanned too; the CHANGELOG is not — an entry recording a change is history.
    if (holdsClaim((string) \file_get_contents(repoPath('README.md')), $claim)) {
        $found[] = 'README.md';
    }

    \sort($found);

    return $found;
}

/**
 * Whether a text holds a phrase, whatever case it is written in.
 *
 * A case-sensitive `str_contains()` misses a sentence that begins with a capital.
 */
function holdsClaim(string $text, string $claim): bool
{
    return \str_contains(\mb_strtolower(spelledOut($text)), \mb_strtolower($claim));
}

/**
 * A source with its comment markers and line breaks flattened away.
 *
 * A phrase is written as the claim reads, not as it happens to be wrapped, or the guard
 * would pass against the very words it forbids.
 */
function spelledOut(string $source): string
{
    $lines = [];

    foreach (\preg_split('/\R/', $source) ?: [] as $line) {
        $lines[] = \trim((string) \preg_replace('~^\s*(?://+|\*+|\#+)\s?~', '', $line));
    }

    return (string) \preg_replace('/\s+/', ' ', \implode(' ', $lines));
}

it('does not claim again what the code does not do', function (string $claim) {
    // Each of these was a comment asserting something the code does not do. Add the
    // phrase here when you correct one.
    expect(sourcesWithClaim($claim))->toBe([]);
})->with([
    // The inline parser said a name was case-sensitive, and that `Note` and `note` were
    // different things. The registry folds the case, so they have always been one.
    'a directive name is case-sensitive' => 'Directive names are case-sensitive',
    'two spellings of a name are different directives' => '`Note` and `note` being different things',
    // The comment that replaced it said the pattern was case-sensitive; what keeps the two
    // parsers in step is the `DirectiveSyntax::NAME` they share.
    'the pattern decides the case of a name' => 'pattern itself is case-sensitive',
    'the flag is what keeps the two parsers in step' => 'the two parsers can never come to accept different names',
]);

it('takes the same name in the block and the inline form', function () {
    // The claim the inline parser's comment makes: a name in either case is one name.
    foreach (['note', 'Note', 'NOTE', 'col-2'] as $name) {
        $block = DirectiveSyntax::matchBlock('::' . $name);

        expect(\preg_match(DirectiveSyntax::INLINE_PATTERN, ':' . $name, $matches))->toBe(1)
            ->and($matches[1] ?? null)->toBe($block[2] ?? null);
    }
});
