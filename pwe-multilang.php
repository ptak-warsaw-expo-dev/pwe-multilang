<?php
/*
 * Plugin Name: PWE Multilang
 * Plugin URI: https://github.com/ptak-warsaw-expo-dev/pwe-multilang
 * Description: Generate pages and forms for multiple languages in WordPress. This plugin is designed to work with the WPML and Gravity Forms, which provides multilingual support for WordPress sites.
 * Version: 1.2.0
 * Author: Piotr Krupniewski
 * Author URI: https://github.com/PiotrKrupniewski
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/ptak-warsaw-expo-dev/pwe-multilang/releases/latest
 * Text Domain: pwe-multilang
 * Requires PHP: 8.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('PWE_MULTILANG_VERSION', '1.2.0');
define('PWE_MULTILANG_FILE', __FILE__);
define('PWE_MULTILANG_PATH', plugin_dir_path(__FILE__));
define('PWE_MULTILANG_URL', plugin_dir_url(__FILE__));

require_once PWE_MULTILANG_PATH . 'core/module-registry.php';
require_once PWE_MULTILANG_PATH . 'core/functions/language-wpml-catalog.php';
require_once PWE_MULTILANG_PATH . 'core/functions/gravity-forms.php';
require_once PWE_MULTILANG_PATH . 'modules/forms/core/year-resolver.php';
require_once PWE_MULTILANG_PATH . 'core/site-group/site-group.php';
require_once PWE_MULTILANG_PATH . 'modules/forms/core/sync/form-operation-lock.php';
require_once PWE_MULTILANG_PATH . 'core/wpml/additional-languages.php';
require_once PWE_MULTILANG_PATH . 'core/wpml/class-wpml-language-manager.php';
require_once PWE_MULTILANG_PATH . 'core/sync-mail-report.php';
require_once PWE_MULTILANG_PATH . 'core/sync-orchestrator.php';

final class PWE_Multilang
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;
        $is_admin = is_admin();

        require_once PWE_MULTILANG_PATH . 'core/plugin-update-checker.php';

        add_action('init', [self::class, 'boot_updater']);

        if ($is_admin) {
            require_once PWE_MULTILANG_PATH . 'core/admin/ui/admin-ui.php';
            require_once PWE_MULTILANG_PATH . 'core/admin/access.php';
            require_once PWE_MULTILANG_PATH . 'core/admin/admin-page.php';
        }

        PWE_Multilang_Module_Registry::load_modules($is_admin);

        if ($is_admin) {
            PWE_Multilang_Admin::init();
        }

        PWE_Multilang_Module_Registry::boot_modules($is_admin);
        PWE_Multilang_Sync_Orchestrator::init();
    }

    public static function boot_updater(): void
    {
        new PWE_Multilang_Updater();
    }
}

PWE_Multilang::boot();
