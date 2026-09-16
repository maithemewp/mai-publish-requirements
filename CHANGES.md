# Changelog

## Unreleased

**New severity: Confirm.** In the block editor, a Confirm result asks "Publish anyway?" before the post goes live, using core's `ConfirmDialog`. **Publish anyway** carries on with the save. **Cancel** stops it, puts the post's status back so Save draft returns, and core shows "Publishing failed. You chose not to publish yet." It asks only on the publish transition. Updates to a live post, Quick Edit, bulk edit, the classic editor and the REST API treat Confirm exactly like Warn. The editor learns what to ask from a new route, `POST /mai-publish-requirements/v1/check`, which evaluates the unsaved post for anyone who can edit it and saves nothing. If that request fails, the post publishes and the after-save warning still shows.

**Block editor warnings are full sentences.** The `mai_publish_warnings` REST field carried the bare rule fragments, so the editor's notice read "use fewer embeds (30 in this post)", lowercase, with no sentence around it. It now carries the same sentence as the admin notice.

**Warnings read "We recommend you …"** instead of "You may also want to …". That matches the Confirm dialog word for word, less its closing "Publish anyway?", so the warning after a save reads the way the question before it did.

**New rule: `EmbedCount`.** A ceiling on embeds, 25 by default, warning by default. It counts Embed blocks of any provider, iframes (YouTube, Vimeo and Spotify embed code), pasted social embed code (Bluesky, Twitter/X, Instagram, TikTok, Threads), `[embed]` shortcodes, and, in classic content, a link alone on its line that a registered oEmbed provider claims. Plain blockquotes and ordinary links are not embeds. An Embed block's own URL is not counted twice.

## 0.2.0

**Breaking. Nothing is registered by default.** A site now opts into every rule through `mai_publish_requirements_rules` and chooses its severity there. A site relying on the featured-image rule being on gets no enforcement until it adds that filter.

**Rules can warn instead of block.** `check()` returns a `Result` carrying a `Severity` rather than a bare string. A warning says something and lets the post publish; a block behaves exactly as before. Severity is decided per result, so one rule can warn at one threshold and block at another.

**The settings screen and its option are gone.** Rules are configured by constructor arguments where they are registered. Post types stay filterable.

**New rules shipped as building blocks:** `TermCount`, `ContentLength`, `Excerpt`, `TitleLength`, `ImageAltText`. Named for their subject, plus a dimension when something is measured; neither the bound nor the severity appears in a name. None are active until registered, and most warn rather than block.

**`TermCount` replaces the separate category rules.** Requiring a category, allowing only one, and capping tags are the same check with different numbers, so it takes `$min` and `$max` and works on any taxonomy. Its id carries the taxonomy (`term_count_category`), so several registered instances stay separately addressable.

**Severity is always the first constructor argument**, so the positional order is the same for every rule. Named arguments are the clearer way to pass the rest.

**Warnings run on updates, not just the publish transition.** A post grows past a size limit or loses its excerpt long after it first went live, and the transition has been and gone by then. Blocks still only apply on the transition, so an update can never unpublish a live post.

**New:** an abstract `Rule` base class, `Context::is_live_save()`, and `Context::content()`, `title()`, `excerpt()` and `term_slugs()`.

## 0.1.0 (6/29/26)
* Added: Initial release — require a featured image before a post can be published, enforced across the block editor (inline error), Quick Edit, bulk edit, and the classic editor (kept as Pending). Per-post-type rule seam with a settings page for choosing which post types are gated.
