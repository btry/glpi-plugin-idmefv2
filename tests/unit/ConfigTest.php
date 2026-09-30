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

use GlpiPlugin\Idmefv2\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    private string $caPath;
    private bool $caFileExisted = false;
    private ?string $caFileContents = null;

    public function testConfigUpdateSavesValidPemWithoutReturningItForConfigurationStorage(): void
    {
        $this->caPath = dirname(__DIR__) . '/fixtures/CA/test_ca_pubkey.pem';
        $publicKey = file_get_contents($this->caPath);

        $result = Config::configUpdate([
            'client_ca_public_key' => $publicKey,
            'require_http_client_certificate' => 1,
        ]);

        $this->assertSame(['require_http_client_certificate' => 1], $result);
        $actualContents = file_get_contents(Config::getCaCertificatePath());
        $this->assertSame($publicKey, $actualContents);
    }

    public function testConfigUpdateRejectsInvalidPemAndPreservesExistingFile(): void
    {
        file_put_contents(Config::getCaCertificatePath(), 'previous CA contents');

        $result = Config::configUpdate([
            'client_ca_public_key' => 'not a PEM public key',
            'require_http_client_certificate' => 1,
        ]);

        $this->assertSame(['require_http_client_certificate' => 1], $result);
        $this->assertSame('previous CA contents', file_get_contents(Config::getCaCertificatePath()));
    }

    public function testConfigUpdateRemovesFileWhenPemIsEmpty(): void
    {
        file_put_contents(Config::getCaCertificatePath(), 'previous CA contents');

        $result = Config::configUpdate([
            'client_ca_public_key' => " \n ",
            'require_http_client_certificate' => 0,
        ]);

        $this->assertSame(['require_http_client_certificate' => 0], $result);
        $this->assertFileDoesNotExist(Config::getCaCertificatePath());
    }

    public function testConfigUpdateLeavesInputUntouchedWhenPemIsNotSubmitted(): void
    {
        $input = ['require_http_client_certificate' => 1];

        $this->assertSame($input, Config::configUpdate($input));
        if ($this->caFileExisted) {
            $this->assertSame($this->caFileContents, file_get_contents($this->caPath));
        } else {
            $this->assertFileDoesNotExist(Config::getCaCertificatePath());
        }
    }
}
