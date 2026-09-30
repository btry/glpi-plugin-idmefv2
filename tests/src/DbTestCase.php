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

namespace tests\units\GlpiPlugin\idmefv2;

use DBmysql;
use Glpi\Tests\DbTestCase as GlpiDbTestCase;

class DbTestCase extends GlpiDbTestCase
{
    // public function setUp(): void
    // {
    //     /** @var DBmysql $DB */
    //     global $DB;

    //     $DB->beginTransaction();
    //     parent::setUp();
    // }

    // public function tearDown(): void
    // {
    //     /** @var DBmysql $DB */
    //     global $DB;

    //     $DB->rollback();
    //     if (!defined('TEST_PLUGIN_NAME')) {
    //         throw new \RuntimeException('TEST_PLUGIN_NAME is not defined');
    //     }
    //     $this->recursiveRmDir(GLPI_PLUGIN_DOC_DIR . '/' . TEST_PLUGIN_NAME);
    //     parent::tearDown();
    // }

    /**
     * Recursively remove directory
     * @see https://www.php.net/manual/en/function.rmdir.php#117354
     *
     * @param string $src
     * @return void
     */
    protected function recursiveRmDir(string $src)
    {
        if (!is_dir($src)) {
            return;
        }
        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if ($file != '.' && $file != '..') {
                $full = $src . '/' . $file;
                if (is_dir($full)) {
                    $this->recursiveRmDir($full);
                } else {
                    unlink($full);
                }
            }
        }
        closedir($dir);
        rmdir($src);
    }

    protected function DBVersionCheck()
    {
        /** @var DBmysql $DB */
        global $DB;

        $version_string = $DB->getVersion();

        $server  = preg_match('/-MariaDB/', $version_string) ? 'MariaDB' : 'MySQL';
        $version = preg_replace('/^((\d+\.?)+).*$/', '$1', $version_string);

        if ($server === 'MySQL' && version_compare($version, '8.0.0', '<')) {
            $this->markTestSkipped('Mysql 8.0 minimum required');
        }

        if ($server === 'MariaDB' && version_compare($version, '10.2.0', '<')) {
            $this->markTestSkipped('MariaDB 10.4 minimum required');
        }
    }
}
