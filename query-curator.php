<?php
/**
 * Plugin Name: Query Curator
 * Plugin URI: https://github.com/Nicscott01/query-curator
 * Description: Build and save curated post queries with drag-and-drop ordering.
 * Version: 1.0.2
 * Author: Nicolas Scott
 * Author URI: https://crearewebsolutions.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: query-curator
 *
 * @package QueryCurator
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'QC_VERSION', '1.0.2' );
define( 'QC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'QC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include files.
require_once QC_PLUGIN_DIR . 'includes/helpers.php';
require_once QC_PLUGIN_DIR . 'includes/class-post-type.php';
require_once QC_PLUGIN_DIR . 'includes/class-meta-boxes.php';
require_once QC_PLUGIN_DIR . 'includes/class-ajax-handler.php';

/**
 * Initialize the plugin.
 *
 * @return void
 */
function qc_init() {
	new Query_Curator_Post_Type();
	new Query_Curator_Meta_Boxes();
	new Query_Curator_Ajax_Handler();
}
add_action( 'plugins_loaded', 'qc_init' );
