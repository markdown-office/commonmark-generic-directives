<?php

declare(strict_types=1);

namespace MarkdownOffice\CommonMark\Directive\Tests\Support;

/**
 * The arrangement a test was built on is not the arrangement it got.
 *
 * Every throw of this is a broken test rather than a wrong result: the page is not
 * where the test said it was, the document does not contain the directive the test is
 * about, the line handed to `blockMatch()` is not a directive, the composer file has
 * stopped having the shape the README claims. Nothing about the library is in question,
 * so saying so in the type is the difference between a reader going to fix the test and
 * a reader going to look for a bug in a parser that is fine.
 *
 * Extends `RuntimeException` so it stays catchable as one, for anyone who wants to
 * handle it that way.
 */
final class BrokenFixture extends \RuntimeException
{
}