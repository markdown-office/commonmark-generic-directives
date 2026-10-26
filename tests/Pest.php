<?php

declare(strict_types=1);

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\MarkdownParser;
use MarkdownOffice\CommonMark\Directive\AttributeNaming;
use MarkdownOffice\CommonMark\Directive\Directive\DirectiveRegistry;
use MarkdownOffice\CommonMark\Directive\Directive\DirectiveSyntax;
use MarkdownOffice\CommonMark\Directive\GenericDirectiveExtension;
use MarkdownOffice\CommonMark\Directive\Node\DirectiveBlock;
use MarkdownOffice\CommonMark\Directive\Node\DirectiveInline;
use MarkdownOffice\CommonMark\Directive\Tests\Support\BrokenFixture;

/**
 * The environment the tests parse with.
 *
 * Deliberately minimal: CommonMark core plus this extension and nothing else, so a test
 * that fails does so because of this package rather than because of what else happened to
 * be installed.
 */
function environment(?DirectiveRegistry $registry = null, AttributeNaming $naming = AttributeNaming::AsWritten): Environment
{
    $environment = new Environment();
    $environment->addExtension(new CommonMarkCoreExtension());
    $environment->addExtension(new GenericDirectiveExtension($registry, $naming));

    return $environment;
}

function render(string $markdown, ?DirectiveRegistry $registry = null, AttributeNaming $naming = AttributeNaming::AsWritten): string
{
    return (string) (new MarkdownConverter(environment($registry, $naming)))->convert($markdown);
}

/**
 * The AST, for assertions about structure rather than output.
 */
function parse(string $markdown, ?DirectiveRegistry $registry = null): Document
{
    return (new MarkdownParser(environment($registry)))->parse($markdown);
}

/**
 * Every directive node in the document, in document order.
 *
 * @return list<DirectiveBlock|DirectiveInline>
 */
function directives(Document $document): array
{
    $found = [];

    $walk = static function ($node) use (&$walk, &$found): void {
        if ($node instanceof DirectiveBlock || $node instanceof DirectiveInline) {
            $found[] = $node;
        }

        foreach ($node->children() as $child) {
            $walk($child);
        }
    };

    foreach ($document->children() as $child) {
        $walk($child);
    }

    return $found;
}

/**
 * The first directive of the given name.
 *
 * Throws rather than returning null when there is none. A test that says "the badge
 * directive is in this document" should fail at that line, not three assertions later
 * where the null has become a method call on nothing.
 */
function directive(Document $document, string $name): DirectiveBlock|DirectiveInline
{
    foreach (directives($document) as $found) {
        if ($found->getName() === $name) {
            return $found;
        }
    }

    throw new BrokenFixture(sprintf(
        'No directive named "%s". Found: %s.',
        $name,
        implode(', ', array_map(
            static fn (DirectiveBlock|DirectiveInline $node): string => $node->getName() ?? '(no name)',
            directives($document)
        )) ?: 'none'
    ));
}

/**
 * Whether a document contains a directive with this name.
 */
function hasDirective(Document $document, string $name): bool
{
    foreach (directives($document) as $found) {
        if ($found->getName() === $name) {
            return true;
        }
    }

    return false;
}

/**
 * The plain text of a node's children, which is what a consumer sees.
 */
function textOf(DirectiveBlock|DirectiveInline $node): string
{
    $text = '';

    $walk = static function ($child) use (&$walk, &$text): void {
        if ($child instanceof Text) {
            $text .= $child->getLiteral();
        }

        foreach ($child->children() as $nested) {
            $walk($nested);
        }
    };

    foreach ($node->children() as $child) {
        $walk($child);
    }

    return $text;
}
/**
 * A path in the repository root, for the files the tests read.
 *
 * The tests check claims the repository makes about itself — the README, composer.json,
 * phpstan.neon.dist — and each of those is named by a relative path from wherever the
 * test file happens to sit. Resolving it here means the layout is described once, and a
 * file that moves is a one-line change rather than one per test that reads it.
 */
function repoPath(string $file): string
{
    return \dirname(__DIR__) . '/' . $file;
}

/**
 * A Pest dataset named for the page line each example sits on.
 *
 * Pest passes an associative dataset to the test as named arguments, so a row that
 * carried `line` would have to be declared as a parameter whether or not the test wanted
 * it — and the alternative is a failure that prints the whole Markdown of the example
 * that broke. This spends the line on the test name instead, which is where it is worth
 * having: a red row then says which line of README.md to go and look at.
 *
 * The naming comes along because an example may claim the output of a naming that is
 * not the default one.
 *
 * @param list<array{markdown: string, html: string, line: int, naming: AttributeNaming}> $examples
 *
 * @return array<string, array<string, string|AttributeNaming>>
 */
function pageDataset(string $page, array $examples): array
{
    $rows = [];

    foreach ($examples as $example) {
        $rows[\basename($page) . ':' . $example['line']] = [
            'markdown' => $example['markdown'],
            'html' => $example['html'],
            'naming' => $example['naming'],
        ];
    }

    return $rows;
}

/**
 * A block pattern match, with the "did not match" case made loud.
 *
 * @return array{0: string, 1: int, 2: string|null, 3: string|null, 4: string, 5: string|null}
 */
function blockMatch(string $line): array
{
    $match = DirectiveSyntax::matchBlock($line);

    if ($match === null) {
        throw new BrokenFixture(sprintf('"%s" is not a block directive.', $line));
    }

    return $match;
}

/**
 * The two fields of this package's composer.json that the README makes a claim about.
 *
 * The shape is validated rather than cast, so the promise in the docblock is one the
 * function actually keeps — a renamed package or a moved field fails here instead of
 * producing a claim about a package that no longer exists.
 *
 * @return array{name: string, require: array<string, string>}
 */
function composerIdentity(string $path): array
{
    $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

    $name = \is_array($decoded) ? ($decoded['name'] ?? null) : null;
    $require = \is_array($decoded) ? ($decoded['require'] ?? null) : null;

    if (!\is_string($name) || !\is_array($require) || \array_is_list($require)) {
        throw new BrokenFixture(sprintf('"%s" does not have the expected shape.', $path));
    }

    foreach ($require as $key => $version) {
        if (!\is_string($key) || !\is_string($version)) {
            throw new BrokenFixture(sprintf('"%s" has a non-string requirement.', $path));
        }
    }

    /** @var array<string, string> $requirements */
    $requirements = $require;

    return ['name' => $name, 'require' => $requirements];
}
