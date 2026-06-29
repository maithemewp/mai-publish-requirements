# Mai Publish Requirements

Require a featured image — and, over time, other per-post-type rules — before a post can be published.

## What it does

Enforces publish requirements only on the **publish transition** (a post moving into `publish`/`future` from a non-live status). Editing a post that is already live is never blocked or unpublished.

- **Block editor / REST** — the save is aborted with an inline error notice listing what's missing.
- **Quick Edit / bulk edit / classic editor** — the post is kept as **Pending** (these paths can't surface an inline error) and the reason is shown as an admin notice.

## Rules

v1 ships one rule: **Featured image required**. Rules are a seam — each rule is a small class that answers "does this post pass, and if not, what's the message?" Adding a rule later (e.g. *category required*, *single category*, *minimum length*) is a drop-in addition; no changes to the gate plumbing.

## Configuration

**Settings → Publish Requirements** — choose which post types each rule applies to.

For developers, applicability is also filterable:

```php
// Add a post type to the default list a rule applies to.
add_filter( 'mai_publish_requirements_rule_post_types', function ( array $types, string $rule_id ): array {
	if ( 'featured_image' === $rule_id ) {
		$types[] = 'page';
	}
	return $types;
}, 10, 2 );

// Register a custom rule.
add_filter( 'mai_publish_requirements_rules', function ( array $rules ): array {
	$rules[] = new My_Custom_Rule();
	return $rules;
} );
```

## Requirements

- WordPress 6.0+
- PHP 8.2+

## Updates

Tag-based updates via [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) against `maithemewp/mai-publish-requirements`. Sites update when a new GitHub release/tag is published.
