<?php
/*
 * Plugin Name: PWE Multilang
 * Plugin URI: https://github.com/ptak-warsaw-expo-dev/pwe-multilang
 * Description: Generate pages and forms for multiple languages in WordPress. This plugin is designed to work with the WPML and Gravity Forms, which provides multilingual support for WordPress sites.
 * Version: 1.0.1
 * Author: Piotr Krupniewski
 * Author URI: https://github.com/PiotrKrupniewski
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/ptak-warsaw-expo-dev/pwe-multilang/releases/latest
 * Text Domain: pwe-multilang
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PWE_MULTILANG_PATH', plugin_dir_path(__FILE__));
define('PWE_MULTILANG_FILE', __FILE__);

// Load required plugin classes.
require_once PWE_MULTILANG_PATH . 'includes/class-pwe-multilang.php';

// Autoapdater
require_once PWE_MULTILANG_PATH . 'includes/class-pwe-qr-updater.php';

// Boot the plugin.
PWE_Multilang::get_instance();