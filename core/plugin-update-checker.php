<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Updater
{
    public function __construct()
    {
        $this->setup_updater();
    }

    /**
     * Setting the auto-update mechanism
     */
    private function setup_updater(): void
    {
        $checker_file = PWE_MULTILANG_PATH . 'core/plugin-update-checker/plugin-update-checker.php';

        if (!file_exists($checker_file)) {
            return;
        }

        require_once $checker_file;

        $update_checker = Puc_v4_Factory::buildUpdateChecker(
            'https://github.com/ptak-warsaw-expo-dev/pwe-multilang',
            PWE_MULTILANG_FILE,
            'pwe-multilang'
        );

        $github_key = PWE_System_Functions::get_database_meta_data('github_personal_access_token');
        
        if (is_string($github_key) && trim($github_key) !== '') {
            $update_checker->setAuthentication(trim($github_key));
        }

        $update_checker->getVcsApi()->enableReleaseAssets();
    }
}