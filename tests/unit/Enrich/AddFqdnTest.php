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

namespace tests\units\GlpiPlugin\Idmefv2\Enrich;

use Computer;
use FQDN;
use GlpiPlugin\Idmefv2\Enrich\AddFqdn;
use NetworkPort;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use tests\units\GlpiPlugin\Idmefv2\DbTestCase;

#[CoversClass(AddFqdn::class)]
final class AddFqdnTest extends DbTestCase
{
    public function testEnrichLeavesMessageWithoutAnalyzerUnchanged(): void
    {
        $message = new stdClass();
        $message->ID = 'alert-1';

        $actual = (new AddFqdn())->enrich($message);

        $this->assertSame($message, $actual);
        $this->assertSame('alert-1', $actual->ID);
        $this->assertObjectNotHasProperty('Analyzer', $actual);
    }


    public function testEnrichDoesNotSetHostnameWhenAnalyzerHasNoIp(): void
    {
        $message = new stdClass();
        $message->Analyzer = new stdClass();
        $message->Analyzer->name = 'sensor';

        $actual = (new AddFqdn())->enrich($message);

        $this->assertSame($message, $actual);
        $this->assertObjectNotHasProperty('hostname', $actual->Analyzer);
        $this->assertSame('sensor', $actual->Analyzer->name);
    }

    public function testEnrichDoesNotOverrideExistingHostname(): void
    {
        $message = new stdClass();
        $message->Analyzer = new stdClass();
        $message->Analyzer->IP = '192.0.2.10';
        $message->Analyzer->hostname = 'configured.example.test';

        $actual = (new AddFqdn())->enrich($message);

        $this->assertSame($message, $actual);
        $this->assertSame('configured.example.test', $actual->Analyzer->hostname);
    }

    public function testEnrichDoesNotAddHostnameWhenIpHasNoFqdn(): void
    {
        $message = new stdClass();
        $message->Analyzer = new stdClass();
        $message->Analyzer->IP = '192.0.2.11';

        $actual = (new AddFqdn())->enrich($message);

        $this->assertSame($message, $actual);
        $this->assertObjectNotHasProperty('hostname', $actual->Analyzer);
    }

    public function testEnrichAddsFqdnForMatchingIp(): void
    {
        $this->login('glpi', 'glpi');

        $fqdn = $this->createItem(FQDN::class, [
            'fqdn' => 'example.test',
        ]);
        $computer = $this->createItem(Computer::class, [
            'name'        => 'IDMEFv2 analyzer',
            'entities_id' => 0,
        ]);
        $network_port = new NetworkPort();
        $network_port->add([
            'items_id'                => $computer->getID(),
            'itemtype'                => Computer::class,
            'entities_id'             => 0,
            'logical_number'          => 1,
            'name'                    => 'eth0',
            'instantiation_type'      => 'NetworkPortEthernet',
            'NetworkName_name'        => 'analyzer',
            'NetworkName_fqdns_id'    => $fqdn->getID(),
            'NetworkName__ipaddresses' => ['-1' => '192.0.2.12'],
            '_create_children'        => true,
        ]);
        $this->assertGreaterThan(0, $network_port->getID());

        $message = new stdClass();
        $message->Analyzer = new stdClass();
        $message->Analyzer->IP = '192.0.2.12';

        $actual = (new AddFqdn())->enrich($message);

        $this->assertSame('analyzer.example.test', $actual->Analyzer->hostname);
    }
}
