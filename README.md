# Mai Publish Requirements

Rules that run when a post is published, each deciding for itself whether to stop the publish or just say something.

## What it does

The plugin is the plumbing. It ships no active rules: a site registers the ones it wants, in code, and chooses how hard each one bites.

A rule returns one of two severities.

- **Block** refuses the publish. In the block editor the save is aborted with an inline error. On Quick Edit, bulk edit and the classic editor, which cannot surface one, the post is kept as **Pending** and the reason appears as an admin notice.
- **Warn** lets the post through and says something. In the block editor it arrives as a warning notice after the save. Elsewhere it is an admin notice.

**Blocks run only on the publish transition**, a post moving into `publish` or `future` from a non-live status. Editing a post that is already live is never blocked or unpublished, because refusing an update would take down a post over a rule it may have been failing for months.

**Warnings run on any save that leaves the post live**, including updates. That is when they earn their keep: a post grows past a size limit or loses its excerpt long after it was first published, and the publish transition has been and gone.

Neither runs on a draft.

## Registering rules

Nothing is enforced until a site asks for it. One filter, in a theme or a site plugin:

```php
use Mai\PublishRequirements\Severity;
use Mai\PublishRequirements\Rules\FeaturedImage;
use Mai\PublishRequirements\Rules\TermCount;

add_filter( 'mai_publish_requirements_rules', function ( array $rules ): array {
	// Refuse to publish without a featured image.
	$rules[] = new FeaturedImage( Severity::Block );

	// Exactly one category, or the publish is refused. min and max together
	// make a range, so 1 and 1 means exactly one.
	$rules[] = new TermCount( Severity::Block, 'category', min: 1, max: 1 );

	// Tag sprawl is untidy rather than broken, so this warns and the post
	// still publishes. No min, so a post with no tags is fine.
	$rules[] = new TermCount( Severity::Warn, 'post_tag', max: 3 );

	return $rules;
} );
```

Every applicable rule runs on the same save. Their messages are joined into one line per severity, so the example above can produce an error saying what must be fixed and a warning saying what could be, from a single publish.

**Severity is always the first argument**, so the positional order is the same for every rule. Everything after it is that rule's own configuration, and named arguments are the clearer way to pass it:

```php
// Severity left at the rule's default, only the interesting value given.
$rules[] = new MinimumLength( words: 30 );

// Severity changed, everything else default.
$rules[] = new ExcerptRequired( Severity::Block );

// Both, positionally.
$rules[] = new TitleLength( Severity::Warn, 70 );

// Or name the one you mean and skip the rest.
$rules[] = new TermCount( taxonomy: 'series', min: 2 );
```

## Shipped rules

| Rule | Default severity | Arguments |
|---|---|---|
| `FeaturedImage` | Block | `Severity` |
| `TermCount` | Block | `Severity`, `$taxonomy`, `$min`, `$max` |
| `MinimumLength` | Warn | `Severity`, `$words` |
| `ExcerptRequired` | Warn | `Severity` |
| `TitleLength` | Warn | `Severity`, `$characters` |
| `ImageAltText` | Warn | `Severity` |

Most warn by default. A rule that blocks is saying the post is broken; a rule that warns is saying it could be better, which is the more common case.

### TermCount

One rule for every "how many terms" question, because requiring a category, allowing only one, and capping tags are the same check with different numbers.

```php
// At least one category. The site's default category does not count, because
// it is what a post gets when nobody chose.
new TermCount( min: 1 );

// At most one. Useful where a section is a place rather than a label:
// breadcrumbs and archive headers have room for one answer.
new TermCount( max: 1 );

// Exactly one.
new TermCount( min: 1, max: 1 );

// Between one and five tags, as a warning.
new TermCount( Severity::Warn, 'post_tag', min: 1, max: 5 );

// Any taxonomy, including custom ones. Two or more required here.
new TermCount( taxonomy: 'series', min: 2 );
```

Leave a bound out and it is not checked, so `new TermCount()` with neither never complains.

Register one per taxonomy. Each instance is a separate rule with its own severity and its own id (`term_count_category`, `term_count_post_tag`), so the post-types filter can move one without moving the others.

Messages use the taxonomy's own labels, so a custom taxonomy reads as itself rather than as its slug.

### The other rules

```php
// A floor on length, aimed at the empty placeholder published by accident
// rather than at short posts written on purpose.
new MinimumLength( words: 50 );

// Archives and share cards fall back to a truncated body without one, which
// lands mid-sentence more often than not.
new ExcerptRequired();

// 60 characters is where Google truncates a result and where a card layout
// usually wraps to a third line.
new TitleLength( characters: 60 );

// Every <img> in the body carries an alt attribute. An EMPTY alt passes:
// alt="" is the correct way to mark a decorative image.
new ImageAltText();
```

## Writing a rule

Extend `Rule` and write two methods, `id()` and `check()`. Return `null` to pass. `post_types()` defaults to `[ 'post' ]`; override it to widen or move that.

```php
use Mai\PublishRequirements\Context;
use Mai\PublishRequirements\Result;
use Mai\PublishRequirements\Rules\Rule;
use Mai\PublishRequirements\Severity;

final class PostSize extends Rule {

	/**
	 * No Severity argument here, deliberately: this rule decides its own, and a
	 * constructor severity would be a promise it could not keep.
	 */
	public function __construct(
		private readonly int $warnKb = 100,
		private readonly int $blockKb = 400,
	) {}

	/**
	 * Stable, and unique among the rules a site registers. It is the error code
	 * and the key the post-types filter matches on.
	 */
	public function id(): string {
		return 'post_size';
	}

	/**
	 * Two thresholds, because the failure is gradual: a heavy post is slow, a
	 * huge one stops rendering usefully at all. Returning null means it passed.
	 */
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

Severity is decided per result, not per rule, which is why this one can warn at 100 KB and block at 400. There is deliberately no setting that overrules a rule, because any such setting would flatten exactly that gradient into one behaviour.

A rule with nothing to judge takes its severity as the first constructor argument instead, and hands it straight back. `FeaturedImage` is the example: the image is there or it is not, so how serious that is belongs to the site rather than to the rule.

The message is an imperative **fragment**, not a sentence. Several are joined together, so "set a featured image" reads correctly where "You must set a featured image." would not.

`Context` normalises a post being saved, whichever path it came through:

| Method | Answers |
|---|---|
| `post_id`, `post_type`, `new_status`, `old_status` | the save itself |
| `is_publish_transition()` | is this post going live now |
| `is_live_save()` | will this post be live after this save |
| `featured_image_id()` | the image being set, or the stored one |
| `content()` | the body being saved, or the stored one |
| `title()`, `excerpt()` | the same, for those fields |
| `term_slugs( $taxonomy )` | terms being assigned, or the stored ones |

## Configuration

Rules are configured where they are registered, through constructor arguments. Thresholds, taxonomies and word counts are shaped differently for every rule, so there is no settings screen for them.

Where a rule applies is filterable without subclassing:

```php
add_filter( 'mai_publish_requirements_rule_post_types', function ( array $types, string $rule_id ): array {
	// Also require a featured image on pages, not just posts.
	if ( 'featured_image' === $rule_id ) {
		$types[] = 'page';
	}

	// TermCount ids carry their taxonomy, so several registered instances stay
	// separately addressable here.
	if ( 'term_count_post_tag' === $rule_id ) {
		$types = [ 'post', 'review' ];
	}

	return $types;
}, 10, 2 );
```

## Requirements

- WordPress 6.0+
- PHP 8.2+

## Updates

Tag-based updates via [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) against `maithemewp/mai-publish-requirements`. Sites update when a new GitHub release/tag is published.
