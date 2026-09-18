<?php

use GlpiPlugin\Idmefv2\Install;
use GlpiPlugin\Idmefv2\Uninstall;
use Toolbox as GlpiToolbox;

function plugin_idmefv2_install(array $args = []): bool
{
    if (!is_readable(__DIR__ . '/install/Install.php')) {
        return false;
    }
    require_once(__DIR__ . '/install/Install.php');
    $version = Install::detectVersion();
    $install = new Install(new Migration(PLUGIN_CARBON_VERSION));

    $success = true;
    $silent = !isCommandLine() && $_SESSION['glpi_use_mode'] !== Session::DEBUG_MODE;
    if ($silent) {
        // do not output messages
        ob_start();
    }
    try {
        if ($version === '0.0.0') {
            $success = $install->install($args);
        } else {
            $success = $install->upgrade($version, $args);
        }
    } catch (RuntimeException $e) {
        if (isCommandLine()) {
            throw $e;
        }
        $error_footer = '<br />' . __('Please check the logs for more details. Fill an issue in the repository of the plugin.', 'carbon');
        Session::addMessageAfterRedirect($e->getMessage() . $error_footer, false, ERROR);
        $success = false;
    }

    if ($silent) {
        // do not output messages
        ob_end_clean();
    }
    return $success;
}

function plugin_idmefv2_uninstall(): bool
{
    if (!is_readable(__DIR__ . '/install/Uninstall.php')) {
        return false;
    }
    require_once(__DIR__ . '/install/Uninstall.php');
    $uninstall = new Uninstall();
    try {
        $uninstall->uninstall();
    } catch (Exception $e) {
        $backtrace = GlpiToolbox::backtrace('');
        trigger_error($e->getMessage() . PHP_EOL . $backtrace, E_USER_WARNING);
        return false;
    }

    return true;
}