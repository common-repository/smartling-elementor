<?php

use KPS3\Smartling\Elementor\Bootloader;
use Smartling\MonologWrapper\MonologWrapper;

/**
 * @link https://www.kps3.com
 * @since 1.0.0
 * @package smartling-elementor
 * @wordpress-plugin
 * Author: Smartling
 * Author URI: https://www.smartling.com
 * Plugin Name: Smartling-elementor
 * Version: 2.12.1
 * Description: Extend Smartling Connector functionality to support elementor. Initial development by KPS3, maintained by Smartling
 * SupportedSmartlingConnectorVersions: 2.7-2.12
 * SupportedElementorVersions: 3.4-3.4
 * SupportedElementorProVersions: 3.4-3.4
 * Elementor tested up to: 3.4.4
 * Elementor Pro tested up to: 3.4.4
 */

if (!class_exists(Bootloader::class)) {
    require_once plugin_dir_path(__FILE__) . 'src/Bootloader.php';
}

if (!function_exists('deactivate_plugins') || !function_exists('get_plugins')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

/**
 * Execute ONLY for admin pages
 */
if ((defined('DOING_CRON') && true === DOING_CRON) || is_admin()) {
    add_action('smartling_before_init', static function ($di) {
        try {
            Bootloader::boot(__FILE__, $di);
        } catch (\Error $e) {
            deactivate_plugins('Smartling-elementor', false, true);
            Bootloader::displayErrorMessage('Smartling-elementor unable to start and was deactivated: ' . $e->getMessage());
            $logger = MonologWrapper::getLogger('smartling-elementor');
            $logger->error('Smartling-Elementor unable to start: ' . $e->getMessage());
        }
    });
}

if (!is_callable('smartling_elementor_json_string')) {
    function smartling_elementor_json_string($value)
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                if (is_array($v)) {
                    $value[$k] = smartling_elementor_json_string($v);
                } else {
                    $value[$k] = json_encode($v, JSON_THROW_ON_ERROR);
                }
            }
        } else {
            $value = json_encode($value, JSON_THROW_ON_ERROR);
        }

        return $value;
    }
}
