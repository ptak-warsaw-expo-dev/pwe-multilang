<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Wystarczy podać adres jednej wersji językowej. Moduł odnajdzie całą
 * połączoną grupę tłumaczeń WPML i ustawi w niej ten sam shortcode.
 */
return [
    '/rejestracja/' => '[pwe-elements-auto-switch-page-registration-visitors]',
    '/zostan-wystawca/' => '[pwe-elements-auto-switch-page-registration-exhibitors]',
    '/krok2/' => '[pwe-elements-auto-switch-page-step2]',
    '/potwierdzenie-rejestracji/' => '[pwe-elements-auto-switch-page-confirmation-visitors-registration]',
    '/potwierdzenie-rejestracji-wystawcy/' => '[pwe-elements-auto-switch-page-confirmation-exhibitors-registration]',
    '/potwierdzenie-vip/' => '[pwe-elements-auto-switch-page-potential-exhibitors]',
    '/kontakt/' => '[pwe-elements-auto-switch-page-contact]',
    '/call-center/' => '[pwe-elements-auto-switch-page-call-center]',
    '/ceremonia-medalowa/' => '[pwe-elements-auto-switch-page-medal-ceremony]',
    '/generator-wystawcow/' => '[pwe-elements-auto-switch-page-exhibitor-visitor-generator]',
    '/generator-obsluga-stoiska/' => '[pwe-elements-auto-switch-page-exhibitor-worker-generator]',
    '/identyfikatory/' => '[pwe-elements-auto-switch-page-badge-local]',
    '/formularze/' => '[pwe-elements-auto-switch-page-forms]',
];
