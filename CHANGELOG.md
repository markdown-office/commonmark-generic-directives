# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this package adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- The `data-` namespace is an option. `AttributeNaming::AsWritten` writes an author's
  attributes under the names they gave them — `type="info"` rather than
  `data-type="info"` — which is what the official Attributes extension writes for the same
  syntax. An attribute whose name starts with `on` is dropped rather than written, as it
  is there. The namespace is still the default.

### Fixed

- A container nested inside another one through a blockquote could not be closed. The
  nested container counted itself against the outermost directive container, but told
  only its own parent when it closed, so the count stayed one high and the outer fence
  was never honoured again — the rest of the document was swallowed as its body.
- An attribute whose name is all digits was renamed. PHP casts a numeric-string array
  key to an integer, and `mergedWith()` merged with `array_merge()`, which renumbers
  those: `{12=twelve}` came back out as `data-0="twelve"`. Where the integer key then
  reached the renderer it was a fatal `TypeError` rather than an attribute.
- `__toString()` no longer writes a `\"` the parser cannot read back. A value holding a
  double quote came out as `a \` plus an extra `hi` attribute; the writer now picks the
  quote the value does not contain, which is the form the README documents for it.

## [0.2.0] - 2026-10-13

### Fixed

- A fence tail that leaked into a paragraph became a text directive. The guard read the
  character two positions back, which is the colon before a space, so `text x::badge[y] more`
  parsed as a directive and `Note: :badge[x] more` did not. Both are now what they look like:
  the tail is text, and the directive after a prose colon is a directive.

### Added

- The parts of a block directive may be spaced out for readability, as the RFC has them:
  `:: name`, `:::eval [label] {.python}`.
- A container may be written without a name. `::: {.myClass}` falls back to a div, which is
  what the RFC says a nameless directive does. A line of colons on its own still closes a
  container rather than opening one, so an opener has to have something else on its line.
- Non-significant colons at the end of the opening line, so
  `:::::::::::: SPOILER :::::::::::::` opens the container it looks like it opens. They do
  not count towards the fence it closes on.

### Changed

- `DirectiveBlock::getName()` returns `?string`. A container written without a name has
  none, and the RFC's fallback is a div rather than a directive called `div`.
- `DirectiveRegistry::assertKnown()` takes `?string` and accepts a nameless directive: a
  registry lists names, and there is no name for it to be missing.
- The default renderer leaves `data-directive` off a container with no name rather than
  writing it empty. The key stays reserved, so an author attribute still cannot name an
  element that has no name.

## [0.1.0] - 2026-10-11

### Added

- `GenericDirectiveExtension` — the Generic Directives RFC for league/commonmark 2.x:
  container (`:::`), leaf (`::`) and text (`:`) directives.
- `DirectiveSyntax` — the grammar in one place, so the block and inline parsers cannot
  come to accept different names.
- `DirectiveAttributes` — the `{…}` of a directive, with `key=value`, quoted values,
  bare booleans, `#id` and `.class` shorthands, and typed accessors.
- `DirectiveRegistry` — optional strict mode. Given the names that exist, an unknown one
  raises `UnknownDirective` naming the alternatives instead of being silently ignored.
- `DirectiveBlock` and `DirectiveInline` — the AST nodes, each carrying name, type,
  attributes and label, and each with a `__toString()` that round-trips to source.
- `DirectiveRenderer` — a default HTML renderer that reproduces every attribute in the
  `data-` namespace, so an author-supplied `onclick` cannot become a script.

### Notes

- A `:::` inside a fenced or indented code block stays code. That is structural —
  CommonMark does not look for new block starts inside a non-container block — and is
  not something this package arranges.
- A directive label is kept as text rather than parsed as inline Markdown, because
  CommonMark's inline engine offers no way for a parser to claim the rest of a run.
- A container closes on a line of at least as many colons as it opened with, so a body
  can show a nested container by opening its own with four.

[0.1.0]: https://github.com/markdown-office/commonmark-generic-directives/releases/tag/v0.1.0
