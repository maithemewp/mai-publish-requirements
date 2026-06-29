<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap. Wires the gate, settings cache coherence, the admin
 * settings page, and the updater. Called once on plugins_loaded.
 */
final class Plugin {

	/**
	 * Boots all plugin components.
	 */
	public static function init(): void {
		( new Gate() )->register();
		( new Updater() )->register();

		// Keep the Settings memo coherent whenever the option is saved or created.
		add_action( 'update_option_' . Settings::OPTION_NAME, [ Settings::class, 'flush_cache' ], 10, 0 );
		add_action( 'add_option_' . Settings::OPTION_NAME, [ Settings::class, 'flush_cache' ], 10, 0 );

		if ( is_admin() ) {
			( new AdminSettings() )->register();
		}
	}
}
