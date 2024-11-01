<?php

namespace KPS3\Smartling\Elementor;

use Smartling\Helpers\ArrayHelper;
use Smartling\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;

class Bootloader {
    private const PLUGIN_NAME = 'Plugin Name';
    private const SUPPORTED_ELEMENTOR_VERSIONS = 'SupportedElementorVersions';
    private const SUPPORTED_ELEMENTOR_PRO_VERSIONS = 'SupportedElementorProVersions';
    private const SUPPORTED_SMARTLING_CONNECTOR_VERSIONS = 'SupportedSmartlingConnectorVersions';

    private ContainerBuilder $di;

    public function __construct(ContainerBuilder $di)
    {
        $this->di = $di;
    }

    public static function displayErrorMessage(string $messageText = ''): void
    {
        if (!function_exists('add_action') || !function_exists('esc_html')) {
            throw new \RuntimeException('This code cannot run outside of WordPress');
        }
        add_action('all_admin_notices', static function () use ($messageText) {
            echo "<div class=\"error\"><p>$messageText</p></div>";
        });
    }

    private static function getPluginMeta(string $pluginFile, string $metaName): string
    {
        $pluginData = get_file_data($pluginFile, [$metaName => $metaName]);

        return $pluginData[$metaName];
    }

    private static function getPluginName(string $pluginFile): string
    {
        return self::getPluginMeta($pluginFile, self::PLUGIN_NAME);
    }

    private static function versionInRange(string $version, string $minVersion, string $maxVersion): bool
    {
        $maxVersionParts = explode('.', $maxVersion);
        $versionParts = explode('.', $version);
        $potentiallyNotSupported = false;
        foreach ($maxVersionParts as $index => $part) {
            if (!array_key_exists($index, $versionParts)) {
                return false; // misconfiguration
            }
            if ($versionParts[$index] > $part && $potentiallyNotSupported) {
                return false; // not supported
            }

            $potentiallyNotSupported = $versionParts[$index] === $part;
        }

        return version_compare($version, $minVersion, '>=');
    }

    public static function boot(string $pluginFile, ContainerBuilder $di): void
    {
        $allPlugins = get_plugins();
        $currentPluginName = self::getPluginName($pluginFile);
        $errorMessage = self::checkSmartlingConnectorSupport($allPlugins, $pluginFile);
        if ($errorMessage !== null) {
            self::displayErrorMessage($errorMessage);
            return;
        }
        $errorMessage = self::checkElementorSupport($allPlugins, $pluginFile);
        if ($errorMessage !== null) {
            self::displayErrorMessage($errorMessage);
            return;
        }

        require_once __DIR__ . DIRECTORY_SEPARATOR . 'ElementorDataSerializer.php';
        require_once __DIR__ . DIRECTORY_SEPARATOR . 'ElementorFilter.php';
        require_once __DIR__ . DIRECTORY_SEPARATOR . 'ElementorProcessor.php';
        require_once __DIR__ . DIRECTORY_SEPARATOR . 'ElementorFieldsFilterHelper.php';
        require_once __DIR__ . DIRECTORY_SEPARATOR . 'ElementorAutoSetup.php';
        (new static($di))->run();
    }

    private static function checkSmartlingConnectorSupport(array $allPlugins, string $pluginFile): ?string
    {
        [$minVersion, $maxVersion] = explode('-', self::getPluginMeta($pluginFile, self::SUPPORTED_SMARTLING_CONNECTOR_VERSIONS));
        $installed = self::findPluginByName($allPlugins, 'Smartling Connector');
        if (!$installed || !self::versionInRange($installed['Version'] ?? '0', $minVersion, $maxVersion)) {
            return "<strong>" . self::getPluginName($pluginFile) . "</strong> extension plugin requires <strong>Smartling Connector</strong> plugin version at least <strong>$minVersion</strong> and at most <strong>$maxVersion</strong>";
        }
        return null;
    }

    private static function checkElementorSupport(array $allPlugins, string $pluginFile): ?string
    {
        $installed = [];
        $supported = [];
        $supportedVersions = [
            'Elementor' => explode('-', self::getPluginMeta($pluginFile, self::SUPPORTED_ELEMENTOR_VERSIONS)),
            'Elementor Pro' => explode('-', self::getPluginMeta($pluginFile, self::SUPPORTED_ELEMENTOR_PRO_VERSIONS)),
        ];
        $elementor = self::findPluginByName($allPlugins, 'Elementor');
        if ($elementor) {
            $installed[] = 'Elementor';
            if (self::versionInRange($elementor['Version'] ?? '0', $supportedVersions['Elementor'][0], $supportedVersions['Elementor'][1])) {
                $supported[] = 'Elementor';
            }
        }
        $elementorPro = self::findPluginByName($allPlugins, 'Elementor Pro');
        if ($elementorPro) {
            $installed[] = 'Elementor Pro';
            if (self::versionInRange($elementorPro['Version'] ?? '0', $supportedVersions['Elementor Pro'][0], $supportedVersions['Elementor Pro'][1])) {
                $supported[] = 'Elementor Pro';
            }
        }
        if (count($installed) === 0 || count($installed) !== count($supported)) {
            return "<strong>" . self::getPluginName($pluginFile) . "</strong> extension plugin requires <strong>Elementor</strong> plugin version at least <strong>{$supportedVersions['Elementor'][0]}</strong> and at most <strong>{$supportedVersions['Elementor'][1]}</strong> or <strong>Elementor Pro</strong> plugin version at least <strong>{$supportedVersions['Elementor Pro'][0]}</strong> and at most <strong>{$supportedVersions['Elementor Pro'][1]}</strong>";
        }
        return null;
    }

    /**
     * @return false|array
     */
    private static function findPluginByName(array $allPlugins, string $name)
    {
        return ArrayHelper::first(array_filter($allPlugins, static function ($item) use ($name) {
            return $item['Name'] === $name;
        }));
    }

    public function run(): void
    {
        try {
            ElementorAutoSetup::register($this->di);
        } catch (\Error $e) {
            deactivate_plugins('Smartling-elementor', false, true);
            self::displayErrorMessage('Smartling-Elementor unable to start');
            $logger = MonologWrapper::getLogger(static::class);
            $logger->error('Smartling-Elementor unable to start: ' . $e->getMessage());
        }
    }
}
