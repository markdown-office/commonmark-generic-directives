<?php

declare(strict_types=1);

use MarkdownOffice\CommonMark\Directive\Directive\DirectiveSyntax;

/*
 * Claims that were wrong, kept so they cannot come back.
 *
 * A comment that says something the code does not do is worse than no comment: it is read
 * as a contract by whoever comes next, and nothing in the suite reads prose. The inline
 * parser's claim about the case of a name was one, and the comment that replaced it was
 * another — and a correction is only worth something if the wrong words cannot come back.
 * Each phrase below is one of them; add the next when it is corrected, which is the whole
 * maintenance cost of the list.
 */

/**
 * The files holding a phrase, so a claim that has been corrected cannot come back.
 *
 * Its own walk because it has to skip this file: the list of phrases below is written
 * here, so this file necessarily holds all of them.
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

    // The README as well, because a claim about the case of a name is one a reader acts
    // on. The CHANGELOG is not scanned: it is allowed to name what was wrong, since an
    // entry recording the change is history rather than a claim about the code.
    if (holdsClaim((string) \file_get_contents(repoPath('README.md')), $claim)) {
        $found[] = 'README.md';
    }

    \sort($found);

    return $found;
}

/**
 * Whether a text holds a phrase, whatever case it is written in.
 *
 * Folding the case is what makes this a guard rather than a lookup: the sentence a claim
 * lives in begins with a capital often enough that a case-sensitive `str_contains()`
 * misses it, and a guard that can be walked past by starting a sentence is not a guard.
 */
function holdsClaim(string $text, string $claim): bool
{
    return \str_contains(\mb_strtolower(spelledOut($text)), \mb_strtolower($claim));
}

/**
 * A source with its comment markers and line breaks flattened away.
 *
 * A phrase to ban is written here as the claim reads, not as it happens to be wrapped: a
 * sentence a `//` comment puts over two lines is one phrase, and matching the raw bytes
 * for it would find nothing — so the guard would pass against the very words it was
 * added to forbid.
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
    // phrase here when you correct one, so it cannot come back.
    expect(sourcesWithClaim($claim))->toBe([]);
})->with([
    // The inline parser said a directive name was case-sensitive, and that `Note` and
    // `note` were different things. The registry folds the case — the README says so —
    // so the two have always been one directive.
    'a directive name is case-sensitive' => 'Directive names are case-sensitive',
    'two spellings of a name are different directives' => '`Note` and `note` being different things',
    // The comment that replaced it said the pattern was case-sensitive, and that this
    // was what kept the two parsers in step. It is not: a name is a character class, so
    // the pattern takes either case and the flag has nothing to fold. What keeps the two
    // parsers in step is the `DirectiveSyntax::NAME` they share.
    'the pattern decides the case of a name' => 'pattern itself is case-sensitive',
    'the flag is what keeps the two parsers in step' => 'the two parsers can never come to accept different names',
]);

it('takes the same name in the block and the inline form', function () {
    // The claim the inline parser's comment makes: the two parsers agree because they
    // share `DirectiveSyntax::NAME`, so a name written in either case is one name.
    foreach (['note', 'Note', 'NOTE', 'col-2'] as $name) {
        $block = DirectiveSyntax::matchBlock('::' . $name);

        expect(\preg_match(DirectiveSyntax::INLINE_PATTERN, ':' . $name, $matches))->toBe(1)
            ->and($matches[1] ?? null)->toBe($block[2] ?? null);
    }
});
