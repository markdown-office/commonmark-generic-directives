# CommonMark Generic Directives

A [league/commonmark](https://commonmark.thephpleague.com/) extension implementing the
[Generic Directives RFC](https://talk.commonmark.org/t/generic-directives-plugins-syntax/444):
container (`:::`), leaf (`::`) and text (`:`) directives.

One syntax, three shapes, and nothing to register to make it parse.

```markdown
Ship it :badge[PRODUCTION]{type=success} today.

::image{src="chart.png" width=400}

:::callout{type=info title="Hosting Advantage"}
Runs on shared hosting.
:::
```

```html
<p>Ship it <span data-directive="badge" data-type="success">PRODUCTION</span> today.</p>
<div data-directive="image" data-src="chart.png" data-width="400"></div>
<div data-directive="callout" data-type="info" data-title="Hosting Advantage"><p>Runs on shared hosting.</p></div>
```

## Installation

```bash
composer require markdown-office/commonmark-generic-directives
```

Requires PHP 8.2 or newer and `league/commonmark` 2.10 or newer.

## Usage

```php
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use MarkdownOffice\CommonMark\Directive\GenericDirectiveExtension;

$environment = new Environment();
$environment->addExtension(new CommonMarkCoreExtension());
$environment->addExtension(new GenericDirectiveExtension());

$html = (new MarkdownConverter($environment))->convert($markdown);
```

The default renderer preserves everything rather than choosing for you: the element carries
the directive's name, and every attribute is reproduced. What a directive *means* is yours to
decide — register your own renderer over it.

```html
<span data-directive="badge" data-type="success">PRODUCTION</span>
```

The container above is checked against the opening example; see
[Tests](#tests) for how.

### Writing your own renderer

```php
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use MarkdownOffice\CommonMark\Directive\Node\DirectiveBlock;

final class CalloutRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        \assert($node instanceof DirectiveBlock);

        $type = $node->getAttributes()->getString('type', 'note');

        return sprintf(
            '<aside class="callout callout--%s">%s</aside>',
            htmlspecialchars($type, ENT_QUOTES),
            $childRenderer->renderNodes($node->children())
        );
    }
}

$environment->addRenderer(DirectiveBlock::class, new CalloutRenderer(), 10);
```

## The three shapes

| Shape | Written | Contains | Name |
|---|---|---|---|
| Text | `:name[label]{attrs}` | nothing — it sits in a paragraph | `DirectiveInline` |
| Leaf | `::name[label]{attrs}` | nothing — it is one line | `DirectiveBlock` |
| Container | `:::name{attrs}` … `:::` | any Markdown | `DirectiveBlock` |

The colon count is the whole difference. A container opens with three or more colons and
closes on a line of **at least as many**, so a body can show a nested three-colon
container by opening its own with four.

```markdown
:::col{width=50%}
:::note
Nested containers keep their own fences.
:::
Back in the outer one.
:::
```

```html
<div data-directive="col" data-width="50%"><div data-directive="note"><p>Nested containers keep their own fences.</p></div>
<p>Back in the outer one.</p></div>
```

The RFC lets the parts of a block directive be spaced out for readability — `:: name`,
`:::eval [label] {.python}`. An inline directive does not get that: `: ` is how prose
introduces a clause.

A container may be written without a name, and then it is a div carrying the attributes that
followed it.

```markdown
::: {.myClass}
some markdown
:::
```

```html
<div data-class="myClass"><p>some markdown</p></div>
```

The opening line may also end in colons, which mean nothing — as long as something else is on
the line, because `:::` on its own is how a container closes.

```markdown
:::::::::::: SPOILER ::::::::::::
We're going to spoil it in three
easy steps:

1. ready
2. steady
3. go
::::::::::::::::::::::::::::::::::
```

```html
<div data-directive="SPOILER"><p>We're going to spoil it in three
easy steps:</p>
<ol>
<li>ready</li>
<li>steady</li>
<li>go</li>
</ol></div>
```

## Attributes

```markdown
{width=50%}              bare value, runs to whitespace
{title="a b c"}          double-quoted, so spaces survive
{title='a "b" c'}        single-quoted, so double quotes survive
{wide}                   no value at all — present and true
{#lead .terse}           # sets id, . appends to class
```

`#id` and `.class` may also be written bare after the name — `:::note#lead.terse` means
the same thing — and several `.class` entries accumulate rather than replace each other.

Values are read with typed accessors rather than by hand:

```php
$attributes = $node->getAttributes();

$attributes->getString('title', 'Untitled');   // string|null
$attributes->getInt('width', 400);             // int|null
$attributes->getFloat('ratio');                // float|null
$attributes->getBool('open', false);           // bool
$attributes->getList('tags');                  // list<string>
$attributes->has('title');                     // bool
$attributes->toArray();                        // array<string, string|true>
```

## Unknown names: permissive or strict

With no registry the extension is permissive and parses any well-formed directive, which
is what the RFC describes. Given a registry it is strict, and an unregistered name raises
`UnknownDirective` **naming the alternatives**:

```php
use MarkdownOffice\CommonMark\Directive\Directive\DirectiveRegistry;

$environment->addExtension(new GenericDirectiveExtension(
    new DirectiveRegistry(['col', 'callout', 'badge:text'])
));

// :::columns → UnknownDirective:
//   Unknown container directive "columns". Registered: badge:text, callout, col.
```

Use strict mode when a machine writes the Markdown. The alternative to an error is a
document that renders without the block it asked for and says nothing about why — which is
the worst outcome available, because nothing in the output looks wrong.

A bare name allows every shape; `name:type` allows one. Names match case-insensitively,
because a document cannot be asked to care about case.

## The AST

```php
$node->getName();        // 'callout', or null for a container written without one
$node->getType();        // DirectiveType::Container | Leaf | Text
$node->getLabel();       // 'Chart', or null when there is none
$node->getAttributes();  // DirectiveAttributes
$node->isContainer();    // bool
(string) $node;          // back to source form
```

`__toString()` round-trips: `::image[Chart]{src="a.png"}` in, the same string out.

## What is deliberately not a directive

These are left as literal text, which is the recoverable outcome:

- `Note: prose` — a colon before a space
- `3:2`, `10:30` — a colon between digits
- `status:error` — a word before the colon
- `    :::a` — four spaces of indent, which is code in CommonMark
- anything inside a fenced or indented code block

That last one is structural rather than a rule this package applies: CommonMark does not
look for new block starts inside a block that is not a container, so a `:::` inside a
fence is code before this extension is consulted.

## The default renderer's HTML is namespaced

Author attributes are written into the `data-` namespace:

```markdown
A :badge[X]{type=success onclick="steal()"} b
```

```html
<p>A <span data-directive="badge" data-type="success" data-onclick="steal()">X</span> b</p>
```

That is not tidiness. An `onclick` written into a page that later runs scripts *is* a
script, and deciding which attribute names are dangerous by keeping a list means the list
is only ever correct until someone invents an event. Namespacing needs no list to maintain.

`data-directive` is reserved and carries the directive's real name; an author attribute
that would land on it is dropped rather than renamed. A consumer that wants real
attributes reads the node rather than this markup.

## Tests

```bash
composer test          # the whole suite
composer test:unit     # the value objects
composer test:feature  # parsing and rendering, end to end
composer check         # validate, audit, static analysis, tests
```

The suite is **100% covered** and analysed at **PHPStan level 9**. Every example on this page
is executed by `tests/Readme/ExamplesTest.php` and every claim in the prose by
`tests/Readme/ClaimsTest.php`, so neither can drift from the code without the build failing.

## Licence

MIT. See [LICENSE](./LICENSE).
