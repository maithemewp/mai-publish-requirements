# Changelog

## 0.2.0

**Breaking. Nothing is registered by default.** A site now opts into every rule through `mai_publish_requirements_rules` and chooses its severity there. A site relying on the featured-image rule being on gets no enforcement until it adds that filter.

**Rules can warn instead of block.** `check()` returns a `Result` carrying a `Severity` rather than a bare string. A warning says something and lets the post publish; a block behaves exactly as before. Severity is decided per result, so one rule can warn at one threshold and block at another.

**The settings screen and its option are gone.** Rules are configured by constructor arguments where they are registered. Post types stay filterable.

**New rules shipped as building blocks:** `CategoryRequired`, `SingleCategory`, `MinimumLength`, `ExcerptRequired`, `TitleLength`, `ImageAltText`. None are active until registered, and most warn rather than block.

**Severity is always the first constructor argument**, so the positional order is the same for every rule. Named arguments are the clearer way to pass the rest.

**New:** an abstract `Rule` base class, and `Context::content()`, `title()`, `excerpt()` and `term_slugs()`.

## 0.1.0 (6/29/26)
* Added: Initial release — require a featured image before a post can be published, enforced across the block editor (inline error), Quick Edit, bulk edit, and the classic editor (kept as Pending). Per-post-type rule seam with a settings page for choosing which post types are gated.
