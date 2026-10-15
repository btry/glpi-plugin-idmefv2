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

namespace GlpiPlugin\Idmefv2\Action;

use IPAddress;
use RuntimeException;
use stdClass;
use Ticket;
use Throwable;

class CreateTicket
{
    public function execute(stdClass $data): stdClass
    {
        global $DB, $CFG_GLPI;
        /**
         * create in the root entity for now,
         *  check if GLPI business rules can handle entity assignation
         */
        $entity_id = 0;

        // Find assets by IP address in source and target.
        // If an asset is found , associate it to the ticket.
        // If multiple assets are found, associate all of them.
        // Enumerate in the ticket content the assets found and their type (computer, network equipment, etc.)

        // Use the category if the IDMEFv2 alert in the titme of the ticket.


        $ticket = new Ticket();
        $ticket_input = [
            'name'        => $data->Description ?? ('IDMEFv2 alert ' . ($data->ID ?? '')),
            'content'     => $data->Note ?? '',
            'entities_id' => $entity_id,
        ];

        $linked_assets = [];
        foreach (['Source', 'Target'] as $role) {
            foreach ($data->$role ?? [] as $endpoint) {
                if (!isset($endpoint->IP)) {
                    continue;
                }

                $asset = IPAddress::getUniqueItemByIPAddress($endpoint->IP, $entity_id);
                if ($asset === []) {
                    continue;
                }

                $asset_key = $asset['itemtype'] . ':' . $asset['id'];
                if (isset($linked_assets[$asset_key])) {
                    continue;
                }

                $linked_assets[$asset_key] = true;
                $ticket_input['items_id'][$asset['itemtype']][] = (int) $asset['id'];

                if ($role === 'Target' && !isset($ticket_input['locations_id'])) {
                    $target = getItemForItemtype($asset['itemtype']);
                    if ($target instanceof \CommonDBTM && $target->getFromDB((int) $asset['id'])) {
                        $location_id = (int) ($target->fields['locations_id'] ?? 0);
                        if ($location_id > 0) {
                            $ticket_input['locations_id'] = $location_id;
                        }
                    }
                }
            }
        }

        $ticket_id = null;
        $DB->beginTransaction();
        try {
            $ticket_id = $ticket->add($ticket_input);
            if (!$ticket_id) {
                throw new RuntimeException('Unable to create ticket for IDMEFv2 alert.');
            }
            $DB->commit();
        } catch (Throwable $exception) {
            $DB->rollBack();
            trigger_error('Unable to create ticket for IDMEFv2 alert: ' . $exception->getMessage(), E_USER_WARNING);
        }

        if ($ticket_id === null) {
            // Failed to create the ticket, return the original data without modification.
            return $data;
        }

        $ticket_url = $CFG_GLPI['url_base'] . Ticket::getFormURLWithID($ticket_id, false);
        $data->Attachment ??= [];
        $data->Attachment[] = (object) [
            'Name'        => 'GLPITicket' . $ticket_id,
            'ExternalURI' => [$ticket_url],
        ];

        return $data;
    }
}