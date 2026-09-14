<?php
/**
 * Plugin Name:       Mai Publish Requirements
 * Plugin URI:        https://bizbudding.com
 * Description:       Rules that run when a post is published, each deciding whether to stop the publish or just warn. Registered in code; nothing is enforced until a site opts in.
 * Version:           0.2.0
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

define( 'MAI_PUBLISH_REQUIREMENTS_VERSION', '0.2.0' );
define( 'MAI_PUBLISH_REQUIREMENTS_FILE', __FILE__ );

require_once __DIR__ . '/vendor/autoload.php';

add_action( 'plugins_loaded', [ \Mai\PublishRequirements\Plugin::class, 'init' ] );
