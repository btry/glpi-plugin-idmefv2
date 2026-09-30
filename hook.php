<?php

/**
 *  -------------------------------------------------------------------------
 *  IDMEFv2 plugin for GLPI
 *
 * @copyright Copyright (C) 2024-2025 Teclib' and contributors.
 * @copyright 2015-2023 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPLv3+
 * @link      https://idmefv2.ovh
 * @link      https://github.com/idmefv2
 *
 *  -------------------------------------------------------------------------
 *
 *  LICENSE
 *
 *  This file is part of IDMEFv2 plugin for GLPI.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 *  -------------------------------------------------------------------------
 */

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