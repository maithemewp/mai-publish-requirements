<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements;

defined( 'ABSPATH' ) || exit;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Tag-based updater (no setBranch): sites only see an update when a GitHub
 * release/tag exists, so wiring this pre-release is inert until we tag.
 */
final class Updater {

	/**
	 * Builds the update checker. Called from Plugin::init on plugins_loaded.
	 */
	public function register(): void {
		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		$updater = PucFactory::buildUpdateChecker(
			'https://github.com/maithemewp/mai-publish-requirements/',
			MAI_PUBLISH_REQUIREMENTS_FILE,
			'mai-publish-requirements'
		);

		if ( defined( 'MAI_GITHUB_API_TOKEN' ) ) {
			$updater->setAuthentication( MAI_GITHUB_API_TOKEN );
		}

		// Show an icon on the Updates screen. Prefer mai-engine's shared icon when
		// present (consistent across Mai sites); otherwise fall back to the icons
		// bundled with this plugin so it's branded on any site — this plugin has
		// no mai-engine dependency and can run anywhere.
		$icons = function_exists( 'mai_get_updater_icons' ) ? (array) mai_get_updater_icons() : [];

		if ( ! $icons ) {
			$icons = [
				'1x' => plugins_url( 'assets/img/icon-128x128.png', MAI_PUBLISH_REQUIREMENTS_FILE ),
				'2x' => plugins_url( 'assets/img/icon-256x256.png', MAI_PUBLISH_REQUIREMENTS_FILE ),
			];
		}

		$updater->addResultFilter(
			static function ( $info ) use ( $icons ) {
				$info->icons = $icons;
				return $info;
			}
		);
	}
}
