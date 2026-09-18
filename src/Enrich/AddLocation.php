<?php

namespace GlpiPlugin\Idmefv2\Enrich;

use Dropdown;
use IPAddress;
use Location;
use stdClass;
use DBmysql;

class AddLocation implements EnrichInterface
{
    public function enrich(stdClass $data): stdClass
    {
        // Based on the IDMEFv2 scpecification v08, complete any (sub) object having an IP with the location if the matching computer in GLPI
        $items = ['Analyzer', 'Source', 'Target', 'Sensor'];
        foreach ($items as $item) {
            if (isset($data->$item)) {
                $data->$item = $this->enrichItem($data->$item);
            }
        }

        return $data;
    }

    private function enrichItem(stdClass $item): stdClass
    {
        if (isset($item->IP) && !isset($item->Location)) {
            $location = $this->getLocation($item->IP);
            if ($location) {
                $item->Location = Dropdown::getDropdownName(Location::getTable(), (int) $location->getID());
                if ($location->fields['longitude'] !== null && $location->fields['latitude'] !== null) {
                    $gps_coordinates = [
                        'longitude' => (float) $location->fields['longitude'],
                        'latitude'  => (float) $location->fields['latitude'],
                    ];
                    if ($location->fields['altitude'] !== null) {
                        $gps_coordinates['altitude'] = (float) $location->fields['altitude'];
                    }
                    $item->GeoLocation = implode(',', $gps_coordinates);
                }
            }
        }

        return $item;
    }

    /**
     * Get the location for a given IP, gathered in GLPI database
     *
     * @param string $ip
     * @return Location|null
     */
    private function getLocation(string $ip): ?Location
    {
        /** @var DBmysql $DB */
        global $DB;

        $ip_row = $DB->request([
            'SELECT' => [
                'itemtype',
                'items_id',
                'mainitemtype',
                'mainitems_id',
            ],
            'FROM'   => IPAddress::getTable(),
            'WHERE'  => [
                'name'      => $ip,
                'is_deleted' => 0,
            ],
            'LIMIT'  => 1,
        ])->current();

        if ($ip_row === false) {
            return null;
        }

        $itemtype = $ip_row['mainitemtype'] ?? '';
        $items_id = (int) ($ip_row['mainitems_id'] ?? 0);

        if ($itemtype === '' || $itemtype === 'NULL' || $items_id <= 0) {
            return null;
        }

        $asset_table = getTableForItemType($itemtype);
        if ($asset_table === '') {
            return null;
        }

        $location_table = getTableForItemType(Location::class);
        $location_row = $DB->request([
            'SELECT' => [
                $location_table => '*',
            ],
            'FROM'   => $asset_table,
            'INNER JOIN' => [
                $location_table => [
                    'ON' => [
                        $asset_table => 'locations_id',
                        $location_table => 'id',
                    ],
                ],
            ],
            'WHERE'  => [$asset_table . '.id' => $items_id],
            'LIMIT'  => 1,
        ])->current();

        if ($location_row === false) {
            return null;
        }

        $location = new Location();
        $location->getFromResultSet($location_row);

        return $location;
    }
}