# Warn or block, and a code-only registry

**Goal:** Let a rule say how serious a failure is, per post, and drop the settings screen so every decision lives in code.

**Why:** Nancy asked for "either a warning, or it just won't publish" (Basecamp 10251050282). Today every rule blocks, by construction. Separately, rule configuration is shaped differently for every rule (which taxonomy, how many words, which byte thresholds), so a settings screen for it would be a form builder. Post types and severity were the only uniform properties, and once severity can vary per post, even those are better expressed in code.

## Shape

```php
enum Severity: string { case Warn = 'warn'; case Block = 'block'; }

final readonly class Result {
    public function __construct( public Severity $severity, public string $message ) {}
}

interface RuleInterface {
    public function id(): string;
    public function post_types(): array;
    public function check( Context $context ): ?Result;  // null = passes
}

abstract class Rule implements RuleInterface {
    public function post_types(): array { return [ 'post' ]; }
}
```

A rule is usually two methods. Binary rules take a Severity in the constructor and hand it back unchanged. Rules with thresholds decide per result, which is the case a site setting could never express: warn at five embeds, block at eight.

## Decisions

**No settings screen, and no saved option.** `Settings.php`, `AdminSettings.php`, the option, its sanitiser and its uninstall cleanup all go. `Rules::for_post_type()` asks the rule. Both existing filters stay: `mai_publish_requirements_rules` to register, `mai_publish_requirements_rule_post_types` to move one.

**No severity override, at any level.** The rule is the only authority. A site that wants a different level registers the rule with a different Severity, or writes its own. A ceiling setting was considered and rejected: it flattens a rule's thresholds into one behaviour, which is the opposite of the point.

**Nothing is registered by default.** The plugin ships rule classes as building blocks and registers none of them. `FeaturedImage` becomes opt-in with a Severity argument.

⚠️ This is a breaking change for any site relying on the featured-image rule being on. eurweb is the only one. It needs the filter adding before this ships, or its gate silently stops enforcing.

**Blocks run on a publish transition. Warnings run on any save of a live post.** Blocking an update would unpublish someone's live post, which is never wanted. A warning is most useful exactly when somebody edits a published post up to 500 KB, and that save is never examined today.

**Warnings reach the block editor through the REST response.** `rest_pre_insert` can only return the prepared object or a `WP_Error` that fails the save, so there is no "saved, but note this". Warnings are computed after the insert, attached to the post's REST response as a read-only field, and rendered by a small editor script through `core/notices`. The non-REST path keeps the existing per-user transient notice.

## Tasks

1. `Severity` enum and `Result`. No behaviour yet.
2. `Context`: capture `post_content` from `$prepared` in `from_rest` (core has normalised it there; the raw request param can be a string or `['raw' => …]`), add `content()` and `term_slugs( string $taxonomy )`. Terms arrive under the taxonomy's `rest_base` as ids on REST, in `tax_input`/`post_category` on save, and fall back to `wp_get_object_terms()`.
3. `RuleInterface` returns `?Result`, gains `post_types()`, loses `label()` and `default_post_types()`. Add the abstract `Rule`.
4. `FeaturedImage` takes a Severity, stops being registered by default.
5. Delete `Settings.php` and `AdminSettings.php`, simplify `Rules`, clean the legacy option in `uninstall.php`.
6. `Gate`: evaluate returns `Result[]`, partition once into blocks and warnings, keep both existing block paths, add the warning paths.
7. Warning transport: REST field plus editor script.
8. Ship `CategoryRequired`, `SingleCategory` and `MinimumLength` as further building blocks.

Tests exist for Context, the featured-image rule, the gate, and rules/settings. The settings half goes; everything else gets extended rather than replaced.
