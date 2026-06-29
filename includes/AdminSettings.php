<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements;

defined( 'ABSPATH' ) || exit;

/**
 * Settings → Publish Requirements.
 *
 * One section per rule, each with a checkbox list of public post types. The
 * saved option is shaped `[ rule_id => [ post_type, ... ] ]`, which scales to
 * multiple rules with no migration.
 */
final class AdminSettings {

	private const MENU_SLUG = 'mai-publish-requirements';

	/**
	 * Wires the admin page, the Settings API registration, and the action link.
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( MAI_PUBLISH_REQUIREMENTS_FILE ), [ $this, 'action_links' ] );
	}

	/**
	 * Adds the options page under Settings.
	 */
	public function add_page(): void {
		add_options_page(
			__( 'Publish Requirements', 'mai-publish-requirements' ),
			__( 'Publish Requirements', 'mai-publish-requirements' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Registers the single array option plus a section and field per rule.
	 */
	public function register_settings(): void {
		register_setting(
			self::MENU_SLUG,
			Settings::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize' ],
				'default'           => [],
			]
		);

		add_settings_section(
			'rules',
			__( 'Rules', 'mai-publish-requirements' ),
			[ $this, 'render_section_intro' ],
			self::MENU_SLUG
		);

		foreach ( Rules::all() as $rule ) {
			add_settings_field(
				$rule->id(),
				esc_html( $rule->label() ),
				[ $this, 'render_rule_field' ],
				self::MENU_SLUG,
				'rules',
				[ 'rule' => $rule ]
			);
		}
	}

	/**
	 * Sanitizes posted checkboxes into `[ rule_id => [valid post types] ]`.
	 *
	 * @param mixed $input Raw posted value.
	 * @return array<string,string[]>
	 */
	public function sanitize( $input ): array {
		$input       = is_array( $input ) ? $input : [];
		$valid_types = array_keys( $this->post_type_choices() );
		$out         = [];

		foreach ( Rules::all() as $rule ) {
			$selected         = isset( $input[ $rule->id() ] ) && is_array( $input[ $rule->id() ] ) ? $input[ $rule->id() ] : [];
			$selected         = array_map( 'sanitize_key', $selected );
			$out[ $rule->id() ] = array_values( array_intersect( $valid_types, $selected ) );
		}

		return $out;
	}

	/**
	 * Public, non-attachment post types as `name => singular label`.
	 *
	 * @return array<string,string>
	 */
	private function post_type_choices(): array {
		$choices = [];

		foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $type ) {
			if ( 'attachment' === $type->name ) {
				continue;
			}
			$choices[ $type->name ] = $type->labels->singular_name ?? $type->name;
		}

		return $choices;
	}

	/**
	 * Intro copy for the rules section.
	 */
	public function render_section_intro(): void {
		echo '<p>' . esc_html__( 'Choose which post types each rule applies to. A post type must pass every checked rule before it can be published.', 'mai-publish-requirements' ) . '</p>';
	}

	/**
	 * Renders the post-type checkboxes for one rule.
	 *
	 * @param array{rule: Rules\RuleInterface} $args
	 */
	public function render_rule_field( array $args ): void {
		$rule    = $args['rule'];
		$current = Settings::post_types_for_rule( $rule );

		foreach ( $this->post_type_choices() as $name => $label ) {
			printf(
				'<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="%1$s[%2$s][]" value="%3$s" %4$s> %5$s</label>',
				esc_attr( Settings::OPTION_NAME ),
				esc_attr( $rule->id() ),
				esc_attr( $name ),
				checked( in_array( $name, $current, true ), true, false ),
				esc_html( $label )
			);
		}
	}

	/**
	 * Renders the settings page wrapper + form.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Publish Requirements', 'mai-publish-requirements' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::MENU_SLUG );
				do_settings_sections( self::MENU_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Prepends a Settings link on the Plugins page row.
	 *
	 * @param string[] $links
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		$url = admin_url( 'options-general.php?page=' . self::MENU_SLUG );

		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Settings', 'mai-publish-requirements' ) )
		);

		return $links;
	}
}
