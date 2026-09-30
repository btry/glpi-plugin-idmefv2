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

namespace tests\units\GlpiPlugin\Idmefv2;

use Config;
use GlpiPlugin\Idmefv2\Uninstall;
use PHPUnit\Framework\Attributes\CoversClass;
use Plugin;
use ProfileRight;

#[CoversClass(Uninstall::class)]
class PluginUninstallTest extends CommonTestCase
{
    public function testUninstallPlugin()
    {
        global $DB;

        $pluginName = TEST_PLUGIN_NAME;

        $plugin = new Plugin();
        $plugin->getFromDBbyDir($pluginName);

        // Uninstall the plugin
        ob_start();
        $plugin->uninstall($plugin->getID());
        $log = ob_get_clean();

        // Check the plugin is not installed
        $plugin->getFromDBbyDir(strtolower($pluginName));
        $this->AssertEquals(Plugin::NOTINSTALLED, (int) $plugin->fields['state']);

        // Check all plugin's tables are dropped
        $tables = [];
        $result = $DB->listTables('glpi_plugin_' . $pluginName . '_%');
        foreach ($result as $row) {
            $tables[] = array_pop($row);
        }
        $this->AssertEquals(0, count($tables), "not deleted tables \n" . json_encode($tables, JSON_PRETTY_PRINT));

        $this->checkConfig();
        // $this->checkRequestType();
        $this->checkAutomaticAction();
        $this->checkRights();
        $this->checkDisplayPrefs();
        $this->checkDashboard();

        Config::deleteConfigurationValues('idmefv2:test_dataset', ['version']);
    }

    public function checkAutomaticAction()
    {
    }

    private function checkConfig()
    {
        $config = Config::getConfigurationValues(TEST_PLUGIN_NAME);
        $this->assertArrayNotHasKey('plugin:idmefv2', $config);
    }

    private function checkRights()
    {
        $profile_right = new ProfileRight();
        $rights = $profile_right->find(['name' => ['LIKE', 'idmefv2:%']]);

        $this->assertEquals(0, count($rights));
    }

    private function checkDisplayPrefs()
    {
    }

    private function checkDashboard()
    {
    }
}
