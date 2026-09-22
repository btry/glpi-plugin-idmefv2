<?php

/**
 * -------------------------------------------------------------------------
 * IDMEFv2 plugin for GLPI
 *
 * @copyright Copyright (C) 2024-2025 Teclib' and contributors.
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPLv3+
 * @link      https://www.idmefv2.ovh/
 * @link      https://github.com/IDMEFv2/
 *
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of IDMEFv2 plugin for GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * -------------------------------------------------------------------------
 */

use Config as GlpiConfig;
use GlpiPlugin\Idmefv2\Config;

// Version of the plugin (major.minor.bugfix)
define('PLUGIN_IDMEFV2_VERSION', '1.0.0-dev');
// Schema version of this version (major.minor.bugfix)
define('PLUGIN_IDMEFV2_SCHEMA_VERSION', '1.0.0');

// Version compatibility check -- from GLPI developer documentation
// > A bug in GLPI prior to 11.0.7 caused plugin routes with method constraints other than GET to never match.
// > The router context was always evaluated as GET, so any route declared with only POST, PUT, DELETE, PATCH, etc.
// > would never be found.
//
// > This bug was fixed in GLPI 11.0.7. If your plugin needs to support GLPI < 11.0.7, use the following workaround:
// > include GET alongside the intended methods and check the actual method manually inside the controller.
//
// For now, there is no such controller, then compatibility with GLPI 11.0.0 is still OK.
// Watch it when adding new controllers.

// Minimal GLPI version, inclusive
define('PLUGIN_IDMEFV2_MIN_GLPI_VERSION', '11.0.0');
// Maximum GLPI version, exclusive
define('PLUGIN_IDMEFV2_MAX_GLPI_VERSION', '13.0.0');

/**
 * Init hooks of the plugin.
 * REQUIRED
 *
 * @return void
 */
function plugin_init_idmefv2()
{
    /** @var array $CFG_GLPI */
    global $CFG_GLPI;

    if (!Plugin::isPluginActive('idmefv2')) {
        return;
    }

    require_once(__DIR__ . '/vendor/autoload.php');
    plugin_idmefv2_setupHooks();
    plugin_idmefv2_registerClasses();
}

function plugin_idmefv2_setupHooks()
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    if (Session::haveRight(GlpiConfig::$rightname, UPDATE)) {
        $PLUGIN_HOOKS['config_page']['idmefv2'] = 'front/config.form.php';
    }
}

function plugin_idmefv2_boot()
{
    \Glpi\Http\SessionManager::registerPluginStatelessPath('idmefv2', '#^/alert\.php$#');
}

function plugin_idmefv2_registerClasses()
{
    Plugin::registerClass(Config::class, ['addtabon' => GlpiConfig::class]);
}

/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array
 */
function plugin_version_idmefv2()
{
    $requirements = [
        'name'           => 'IDMEFv2',
        'version'        => PLUGIN_IDMEFV2_VERSION,
        'author'         => '<a href="http://www.teclib.com">Teclib\'</a>',
        'license'        => 'GPLv3',
        'homepage'       => '',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_IDMEFV2_MIN_GLPI_VERSION,
            ],
        ],
    ];

    $dev_version = strpos(PLUGIN_IDMEFV2_VERSION, '-dev') !== false;
    if (!$dev_version) {
        // This is not a development version
        $requirements['requirements']['glpi']['max'] = PLUGIN_IDMEFV2_MAX_GLPI_VERSION;
    }
    return $requirements;
}

/**
 * Check plugin's prerequisites before installation
 *
 * @return bool
 */
function plugin_idmefv2_check_prerequisites()
{
    /** @var DBmysql $DB */
    global $DB;

    $prerequisitesSuccess = true;

    // In case GLPI is so old that the modern version checker is not implemented
    /** @phpstan-ignore if.alwaysFalse */
    if (version_compare(GLPI_VERSION, "10.0.0", 'lt')) {
        echo "This plugin requires GLPI >= " . PLUGIN_IDMEFV2_MIN_GLPI_VERSION . " and GLPI < " . PLUGIN_IDMEFV2_MAX_GLPI_VERSION . "<br>";
        $prerequisitesSuccess = false;
    }

    if (!is_readable(__DIR__ . '/vendor/autoload.php') || !is_file(__DIR__ . '/vendor/autoload.php')) {
        echo "Run composer install --no-dev in the plugin directory<br>";
        $prerequisitesSuccess = false;
    }

    if ($DB->use_timezones !== true) {
        echo "Enable timezones support<br>";
        $prerequisitesSuccess = false;
    }

    return $prerequisitesSuccess;
}

/**
 * Get the path to the empty SQL schema file
 * @param string $version The version of the schema file to get
 *
 * @return string|null
 */
function plugin_idmefv2_getSchemaPath(?string $version = null): ?string
{
    $version ??= PLUGIN_IDMEFV2_VERSION;

    // Drop suffixes for alpha, beta, rc versions
    $matches = [];
    preg_match('/^(\d+\.\d+\.\d+)/', $version, $matches);
    $version = $matches[1];

    $matches = [];
    preg_match('/^(\d+\.\d+\.\d+)/', PLUGIN_IDMEFV2_VERSION, $matches);
    $current_version = $matches[1];

    if ($version === $current_version) {
        $schemaPath = Plugin::getPhpDir('idmefv2') . '/install/mysql/plugin_idmefv2_empty.sql';
    } else {
        $schemaPath = Plugin::getPhpDir('idmefv2') . "/install/mysql/plugin_idmefv2_{$version}_empty.sql";
    }

    // Plugin::getPhpDir may return false
    /** @phpstan-ignore identical.alwaysFalse */
    if ($schemaPath === false) {
        return null;
    }

    return $schemaPath;
}

/**
 * Get friendly name of the plugin, may be used in various places
 *
 * @return string
 */
function plugin_idmefv2_getFriendlyName(): string
{
    return __('IDMEFv2', 'idmefv2');
}
