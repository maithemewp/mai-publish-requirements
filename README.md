# Mai Publish Requirements

Rules that run when a post is published, each deciding for itself whether to stop the publish or just say something.

## What it does

The plugin is the plumbing. It ships no active rules: a site registers the ones it wants, in code, and chooses how hard each one bites.

A rule returns one of two severities.

- **Block** refuses the publish. In the block editor the save is aborted with an inline error. On Quick Edit, bulk edit and the classic editor, which cannot surface one, the post is kept as **Pending** and the reason appears as an admin notice.
- **Warn** lets the post through and says something. In the block editor it arrives as a warning notice after the save. Elsewhere it is an admin notice.

Blocks run only on the **publish transition**, a post moving into `publish` or `future` from a non-live status. Editing a post that is already live is never blocked or unpublished.

## Registering rules

Nothing happens until a site asks for it:

```php
use Mai\PublishRequirements\Severity;
use Mai\PublishRequirements\Rules\CategoryRequired;
use Mai\PublishRequirements\Rules\FeaturedImage;
use Mai\PublishRequirements\Rules\MinimumLength;

add_filter( 'mai_publish_requirements_rules', function ( array $rules ): array {
	$rules[] = new FeaturedImage( Severity::Block );
	$rules[] = new CategoryRequired();
	$rules[] = new MinimumLength( words: 30 );

	return $rules;
} );
```

Every applicable rule runs on the same save and their messages are joined into one line per severity.

## Shipped rules

| Rule | Default severity | Arguments |
|---|---|---|
| `FeaturedImage` | Block | `Severity` |
| `CategoryRequired` | Block | `Severity`, `$taxonomy` |
| `SingleCategory` | Warn | `Severity`, `$taxonomy` |
| `MinimumLength` | Warn | `$words`, `$severity` |

`CategoryRequired` ignores the site's default category, since that is what a post gets when nobody chose. `SingleCategory` says nothing when there are none, because that is `CategoryRequired`'s question.

## Writing a rule

Extend `Rule` and write two methods. Return `null` to pass.

```php
use Mai\PublishRequirements\Context;
use Mai\PublishRequirements\Result;
use Mai\PublishRequirements\Rules\Rule;
use Mai\PublishRequirements\Severity;

final class PostSize extends Rule {

	public function __construct(
		private readonly int $warnKb = 100,
		private readonly int $blockKb = 400,
	) {}

	public function id(): string {
		return 'post_size';
	}

	public function check( Context $context ): ?Result {
		$kb = (int) round( strlen( $context->content() ) / 1024 );

		return match ( true ) {
			$kb >= $this->blockKb => new Result( Severity::Block, sprintf( 'split this post up (%d KB)', $kb ) ),
			$kb >= $this->warnKb  => new Result( Severity::Warn, sprintf( 'this post is %d KB and will load slowly', $kb ) ),
			default               => null,
		};
	}
}
```

Severity is decided per result, not per rule, which is why a rule can warn at one threshold and block at another. There is deliberately no setting that overrules a rule: it would flatten exactly that.

The message is an imperative **fragment**, not a sentence. Several are joined together, so "set a featured image" reads correctly where "You must set a featured image." would not.

`Context` normalises a post being saved, whichever path it came through:

| Method | Answers |
|---|---|
| `post_id`, `post_type`, `new_status`, `old_status` | the save itself |
| `is_publish_transition()` | is this post going live now |
| `featured_image_id()` | the image being set, or the stored one |
| `content()` | the body being saved, or the stored one |
| `term_slugs( $taxonomy )` | terms being assigned, or the stored ones |

## Configuration

Rules are configured where they are registered, through constructor arguments. Thresholds, taxonomies and word counts are shaped differently for every rule, so there is no settings screen for them.

Where a rule applies is filterable without subclassing:

```php
add_filter( 'mai_publish_requirements_rule_post_types', function ( array $types, string $rule_id ): array {
	if ( 'featured_image' === $rule_id ) {
		$types[] = 'page';
	}

	return $types;
}, 10, 2 );
```

## Requirements

- WordPress 6.0+
- PHP 8.2+

## Updates

Tag-based updates via [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) against `maithemewp/mai-publish-requirements`. Sites update when a new GitHub release/tag is published.
