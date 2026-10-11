<?php

declare(strict_types=1);

namespace MarkdownOffice\CommonMark\Directive\Tests\Support;

/**
 * Pulls the worked examples out of README.md.
 *
 * The rule is doctest's: a fenced Markdown block immediately followed by a fenced HTML
 * block is a claim about what the first one converts to. Every such pair is executed, so
 * an example that stops being true fails the build rather than misleading the next
 * reader.
 *
 * The alternative — transcribing each example into a test by hand — is how the claim and
 * the code drift apart in the first place, because the test keeps passing when the page
 * changes. That is a failure this file exists to make impossible.
 */
final class Readme
{
    /**
     * @return list<array{markdown: string, html: string, line: int}>
     */
    public static function examples(string $path): array
    {
        $lines = \file($path, \FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            throw new BrokenFixture(\sprintf('Cannot read "%s".', $path));
        }

        $examples = [];
        $markdown = null;
        $markdownLine = 0;

        foreach ($lines as $number => $line) {
            if ($markdown === null) {
                if (\str_starts_with(\trim($line), '```markdown')) {
                    $markdown = [];
                    $markdownLine = $number + 1;
                }

                continue;
            }

            if (\str_starts_with(\trim($line), '```')) {
                // The block after a Markdown one, if it is HTML, is the claim.
                $expected = self::htmlFollowing($lines, $number + 1);

                if ($expected !== null) {
                    $examples[] = [
                        'markdown' => \implode("\n", $markdown),
                        'html' => $expected,
                        'line' => $markdownLine,
                    ];
                }

                $markdown = null;

                continue;
            }

            $markdown[] = $line;
        }

        return $examples;
    }

    /**
     * The body of the HTML block at or after `$from`, or null when there is not one.
     *
     * A blank line between the two fences is allowed; anything else is not. Prose sitting
     * between a Markdown example and an HTML one means the page has moved on to talk
     * about something, and pairing them anyway would assert a conversion nobody wrote.
     *
     * @param list<string> $lines
     */
    private static function htmlFollowing(array $lines, int $from): ?string
    {
        $total = \count($lines);

        for ($next = $from; $next < $total; $next++) {
            if (\str_starts_with(\trim($lines[$next]), '```html')) {
                for ($body = $next + 1; $body < $total; $body++) {
                    if (\str_starts_with(\trim($lines[$body]), '```')) {
                        return \implode("\n", \array_slice($lines, $next + 1, $body - $next - 1));
                    }
                }
            }

            // An unterminated ```html lands here: the inner loop ran out without finding
            // its closing fence, so there is no body and this is not a claim.
            if (\trim($lines[$next]) !== '') {
                return null;
            }
        }

        return null;
    }
}
