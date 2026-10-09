# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this package adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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