<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap. Wires the gate and the updater, and nothing else: there are
 * no settings to keep coherent and no admin screen, because which rules apply
 * and how hard they bite are decided in code. Called once on plugins_loaded.
 */
final class Plugin {

	public static function init(): void {
		( new Gate() )->register();
		( new Updater() )->register();
	}
}
