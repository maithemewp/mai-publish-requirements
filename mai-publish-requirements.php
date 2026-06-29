<?php
/**
 * Plugin Name:       Mai Publish Requirements
 * Plugin URI:        https://bizbudding.com
 * Description:       Require a featured image (and other per-post-type rules) before a post can be published. Enforced inline in the block editor and as a Pending demotion on non-REST paths.
 * Version:           0.1.0
 * Author:            BizBudding
 * Author URI:        https://bizbudding.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Text Domain:       mai-publish-requirements
 *
 * @package Mai\PublishRequirements
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

// Prevent double-loading when installed standalone AND bundled via Composer.
if ( defined( 'MAI_PUBLISH_REQUIREMENTS_VERSION' ) ) {
	return;
}

define( 'MAI_PUBLISH_REQUIREMENTS_VERSION', '0.1.0' );
define( 'MAI_PUBLISH_REQUIREMENTS_FILE', __FILE__ );
define( 'MAI_PUBLISH_REQUIREMENTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'MAI_PUBLISH_REQUIREMENTS_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/vendor/autoload.php';

add_action( 'plugins_loaded', [ \Mai\PublishRequirements\Plugin::class, 'init' ] );
