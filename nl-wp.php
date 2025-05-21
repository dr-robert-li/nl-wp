<?php
/**
 * Plugin Name: NLWeb for WordPress
 * Plugin URI: https://github.com/microsoft/NLWeb
 * Description: Adds NLWeb endpoints to WordPress for natural language interaction with your website content. Includes MCP compatibility for AI assistants.
 * Version: 1.0.0
 * Author: NLWeb Project
 * Author URI: https://github.com/microsoft/NLWeb
 * License: MIT
 * Text Domain: nl-wp
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('NLWP_VERSION', '1.0.0');
define('NLWP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NLWP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NLWP_ADMIN_URL', admin_url('admin.php?page=nl-wp'));

// Load dependencies
require_once NLWP_PLUGIN_DIR . 'includes/class-nl-wp.php';
require_once NLWP_PLUGIN_DIR . 'includes/class-nl-wp-loader.php';
require_once NLWP_PLUGIN_DIR . 'includes/class-nl-wp-api.php';
require_once NLWP_PLUGIN_DIR . 'includes/class-nl-wp-mcp.php';
require_once NLWP_PLUGIN_DIR . 'includes/class-nl-wp-milvus.php';

// Load admin-specific functionality
if (is_admin()) {
    require_once NLWP_PLUGIN_DIR . 'admin/class-nl-wp-admin.php';
}

/**
 * Begins execution of the plugin.
 */
function run_nl_wp() {
    $plugin = new NL_WP();
    $plugin->run();
}

// Run the plugin
run_nl_wp();