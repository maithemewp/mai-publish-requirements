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
	}
}
