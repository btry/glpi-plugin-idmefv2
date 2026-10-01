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

namespace GlpiPlugin\Idmefv2;

use Exception;
use GlpiPlugin\Idmefv2\Enrich\AddLocation;
use GlpiPlugin\Idmefv2\Idmefv2 as GlpiPluginIdmefv2;
use RuntimeException;
use stdClass;

use function Safe\json_decode;
use function Safe\json_encode;

class Alert
{

    private string $body;
    private array $enrichments;
    private array $actions;
    private stdClass $returned_idmefv2_message;

    public function __construct(string $body)
    {
        $this->body = $body;
        $this->returned_idmefv2_message = new StdClass();
        // TODO: make enrichments and actions configurable via plugin settings
        // These arrays are ordered, as some enrichments may require previous ones
        // Actions are always processed after enrichments, as they may require enriched data
        $this->enrichments = [
            new AddLocation(),
        ];
        $this->actions = [];
    }

    public function processAlert()
    {
        try {
            $this->returned_idmefv2_message = json_decode($this->body, false);
            $this->validateMessage();
        } catch (Exception $e) {
            $new_e = new RuntimeException('Invalid alert message: ' . $e->getMessage());
            throw $new_e;
        }

        foreach ($this->enrichments as $enrichment) {
            $this->returned_idmefv2_message = $enrichment->enrich($this->returned_idmefv2_message);
        }

        // FIXME: delay action in a separate process for faster answer to the alert sender,
        // as actions may take a long time to complete
        foreach ($this->actions as $action) {
            $action->execute($this->returned_idmefv2_message);
        }
    }

    public function getResponse(): string
    {
        return json_encode($this->returned_idmefv2_message);
    }

    private function validateMessage()
    {
        (new GlpiPluginIdmefv2())->isIdmefv2Compliant($this->returned_idmefv2_message);
    }
}
