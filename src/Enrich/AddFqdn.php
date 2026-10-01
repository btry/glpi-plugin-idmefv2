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

namespace GlpiPlugin\Idmefv2\Enrich;

class AddFqdn implements EnrichInterface
{
    public function enrich(array $data): array
    {
        // Based on the IDMEFv2 scpecification v08, complete any (sub) object having an IP with the FQDN if the matching computer in GLPI
        if (isset($data['Analyzer']['IP']) && !isset($data['Analyzer']['hostname'])) {
            $fqdn = $this->getFqdn($data['Analyzer']['IP']);
            if ($fqdn) {
                $data['Analyzer']['hostname'] = $fqdn;
            }
        }

        return $data;
    }

    /**
     * Get the FQDN for a given IP, gathered in GLPI database
     *
     * @param string $ip
     * @return string|null
     */
    private function getFqdn(string $ip): ?string
    {
        global $DB;

        return null;
    }
}
