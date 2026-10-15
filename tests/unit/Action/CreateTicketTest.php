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

namespace tests\units\GlpiPlugin\Idmefv2\Action;

use Computer;
use GlpiPlugin\Idmefv2\Action\CreateTicket;
use Location;
use NetworkPort;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Ticket;
use tests\units\GlpiPlugin\Idmefv2\DbTestCase;

#[CoversClass(CreateTicket::class)]
final class CreateTicketTest extends DbTestCase
{
    public function testCreatesTicketWithMatchedSourceAndTargetAssets(): void
    {
        global $CFG_GLPI;

        $this->login('glpi', 'glpi');

        $location = $this->createItem(Location::class, [
            'name'        => 'IDMEFv2 target location',
            'entities_id' => 0,
        ]);
        $source = $this->createComputerWithIp('IDMEFv2 source', '10.20.0.11');
        $target = $this->createComputerWithIp('IDMEFv2 target', '10.20.0.12', $location->getID());

        $message = new stdClass();
        $message->ID = 'alert-1';
        $message->Description = 'Detected suspicious activity';
        $message->Note = 'Additional alert details';
        $message->Source = [
            (object) ['IP' => '10.20.0.11'],
            (object) ['IP' => '10.20.0.11'],
        ];
        $message->Target = [
            (object) [
                'IP'       => '10.20.0.12',
                'Location' => 'IDMEFv2 target location',
            ],
        ];

        $result = (new CreateTicket())->execute($message);

        $ticket_id = (int) substr($result->Attachment[0]->Name, strlen('GLPITicket'));
        $ticket = new Ticket();
        $this->assertGreaterThan(0, $ticket_id);
        $this->assertTrue($ticket->getFromDB($ticket_id));
        $this->assertSame('Detected suspicious activity', $ticket->fields['name']);
        $this->assertSame('Additional alert details', $ticket->fields['content']);
        $this->assertSame($location->getID(), (int) $ticket->fields['locations_id']);

        $linked_items = $ticket->getLinkedItems();
        $this->assertCount(2, $linked_items[Computer::class]);
        $this->assertEqualsCanonicalizing(
            [$source->getID(), $target->getID()],
            array_values($linked_items[Computer::class])
        );
        $this->assertSame(
            [$CFG_GLPI['url_base'] . Ticket::getFormURLWithID($ticket_id, false)],
            $result->Attachment[0]->ExternalURI
        );
    }

    public function testCreatesTicketWhenNoIpMatchesAnAsset(): void
    {
        $this->login('glpi', 'glpi');

        $message = new stdClass();
        $message->ID = 'alert-2';
        $message->Source = [
            (object) [],
            (object) ['IP' => '192.0.2.10'],
        ];
        $message->Attachment = [(object) ['Name' => 'existing-attachment']];

        $result = (new CreateTicket())->execute($message);

        $this->assertCount(2, $result->Attachment);
        $this->assertSame('existing-attachment', $result->Attachment[0]->Name);
        $ticket_id = (int) substr($result->Attachment[1]->Name, strlen('GLPITicket'));
        $ticket = new Ticket();
        $this->assertTrue($ticket->getFromDB($ticket_id));
        $this->assertSame('IDMEFv2 alert alert-2', $ticket->fields['name']);
        $this->assertSame('', $ticket->fields['content']);
        $this->assertSame([], $ticket->getLinkedItems());
    }

    private function createComputerWithIp(string $name, string $ip_address, int $location_id = 0): Computer
    {
        $computer = $this->createItem(Computer::class, [
            'name'        => $name,
            'entities_id' => 0,
            'locations_id' => $location_id,
        ]);

        $network_port = $this->createItem(NetworkPort::class, [
            'items_id'                => $computer->getID(),
            'itemtype'                => $computer::class,
            'entities_id'             => 0,
            'logical_number'          => 1,
            'name'                    => 'eth0',
            'instantiation_type'      => 'NetworkPortEthernet',
            'NetworkName_name'        => 'eth0',
            'NetworkName__ipaddresses' => ['-1' => $ip_address],
            '_create_children'        => true,
        ]);

        $this->assertGreaterThan(0, $network_port->getID());

        return $computer;
    }
}