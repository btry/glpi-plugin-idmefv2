<?php

namespace tests\units\Glpi\Plugin\Idmefv2\Enrich;

use Computer;
use GlpiPlugin\Idmefv2\Enrich\AddLocation;
use Location;
use NetworkPort;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use tests\units\GlpiPlugin\idmefv2\DbTestCase;

#[CoversClass(AddLocation::class)]
final class AddLocationTest extends DbTestCase
{
    private function createComputerWithIp(
        string $computer_name,
        string $ip_address,
        string $location_name,
        ?float $longitude = null,
        ?float $latitude = null,
        ?float $altitude = null,
    ): Computer {
        $location_data = [
            'name' => $location_name,
        ];

        if ($longitude !== null) {
            $location_data['longitude'] = $longitude;
        }
        if ($latitude !== null) {
            $location_data['latitude'] = $latitude;
        }
        if ($altitude !== null) {
            $location_data['altitude'] = $altitude;
        }

        $location = $this->createItem(Location::class, $location_data);

        $computer = $this->createItem(Computer::class, [
            'name'        => $computer_name,
            'entities_id' => 0,
            'locations_id' => $location->getID(),
        ]);

        $network_port = new NetworkPort();
        $network_port->add([
            'items_id'           => $computer->getID(),
            'itemtype'           => Computer::class,
            'entities_id'        => $computer->fields['entities_id'],
            'logical_number'     => 1,
            'name'               => 'eth0',
            'instantiation_type' => 'NetworkPortEthernet',
            'NetworkName_name'   => 'eth0',
            'NetworkName__ipaddresses' => ['-1' => $ip_address],
            '_create_children'   => true,
        ]);

        $this->assertGreaterThan(0, $network_port->getID());

        return $computer;
    }

    public static function provideMessagesWithLocation(): array
    {
        $location_a = 'Main Office';
        $location_b = 'Remote Branch';
        $location_c = 'Data Center';
        $location_d = 'R&D Lab';

        $analyzer_only_input = new stdClass();
        $analyzer_only_input->Analyzer = new stdClass();
        $analyzer_only_input->Analyzer->IP = '10.0.0.11';
        $analyzer_only_expected = new stdClass();
        $analyzer_only_expected->Analyzer = clone $analyzer_only_input->Analyzer;
        $analyzer_only_expected->Analyzer->Location = $location_a;

        $source_only_input = new stdClass();
        $source_only_input->Source = new stdClass();
        $source_only_input->Source->IP = '10.0.0.12';
        $source_only_expected = new stdClass();
        $source_only_expected->Source = clone $source_only_input->Source;
        $source_only_expected->Source->Location = $location_b;

        $target_only_input = new stdClass();
        $target_only_input->Target = new stdClass();
        $target_only_input->Target->IP = '10.0.0.13';
        $target_only_expected = new stdClass();
        $target_only_expected->Target = clone $target_only_input->Target;
        $target_only_expected->Target->Location = $location_c;

        $sensor_only_input = new stdClass();
        $sensor_only_input->Sensor = new stdClass();
        $sensor_only_input->Sensor->IP = '10.0.0.14';
        $sensor_only_expected = new stdClass();
        $sensor_only_expected->Sensor = clone $sensor_only_input->Sensor;
        $sensor_only_expected->Sensor->Location = $location_d;

        $all_items_input = new stdClass();
        $all_items_input->Analyzer = new stdClass();
        $all_items_input->Analyzer->IP = '10.0.0.11';
        $all_items_input->Source = new stdClass();
        $all_items_input->Source->IP = '10.0.0.12';
        $all_items_input->Target = new stdClass();
        $all_items_input->Target->IP = '10.0.0.13';
        $all_items_input->Sensor = new stdClass();
        $all_items_input->Sensor->IP = '10.0.0.14';

        $all_items_expected = new stdClass();
        $all_items_expected->Analyzer = clone $all_items_input->Analyzer;
        $all_items_expected->Source = clone $all_items_input->Source;
        $all_items_expected->Target = clone $all_items_input->Target;
        $all_items_expected->Sensor = clone $all_items_input->Sensor;
        $all_items_expected->Analyzer->Location = $location_a;
        $all_items_expected->Source->Location = $location_b;
        $all_items_expected->Target->Location = $location_c;
        $all_items_expected->Sensor->Location = $location_d;

        return [
            'Analyzer only' => [
                $analyzer_only_input,
                $analyzer_only_expected,
            ],
            'Source only' => [
                $source_only_input,
                $source_only_expected,
            ],
            'Target only' => [
                $target_only_input,
                $target_only_expected,
            ],
            'Sensor only' => [
                $sensor_only_input,
                $sensor_only_expected,
            ],
            'All items together' => [
                $all_items_input,
                $all_items_expected,
            ],
        ];
    }

    public static function provideMessagesWithGeoLocation(): array
    {
        $analyzer_1 = new stdClass();
        $analyzer_1->IP = '10.0.0.21';
        $message_1 = new stdClass();
        $message_1->Analyzer = $analyzer_1;
        $message_1_expected = new stdClass();
        $message_1_expected->Analyzer = clone $analyzer_1;
        $message_1_expected->Analyzer->Location = 'Paris HQ';
        $message_1_expected->Analyzer->GeoLocation = '48.8566,2.3522';

        $analyzer_2 = new stdClass();
        $analyzer_2->IP = '10.0.0.22';
        $message_2 = new stdClass();
        $message_2->Analyzer = $analyzer_2;
        $message_2_expected = new stdClass();
        $message_2_expected->Analyzer = clone $analyzer_2;
        $message_2_expected->Analyzer->Location = 'Alpine Station';
        $message_2_expected->Analyzer->GeoLocation = '45.503,6.566,1234.5';

        return [
            'GeoLocation without altitude' => [
                $message_1,
                $message_1_expected,
            ],
            'GeoLocation with altitude' => [
                $message_2,
                $message_2_expected,
            ],
        ];
    }

    #[DataProvider('provideMessagesWithLocation')]
    public function testEnrichAddsLocationForMatchingAsset(stdClass $message, stdClass $expected): void
    {
        $this->login('glpi', 'glpi');

        $this->createComputerWithIp('idmef-analyzer', '10.0.0.11', 'Main Office');
        $this->createComputerWithIp('idmef-source', '10.0.0.12', 'Remote Branch');
        $this->createComputerWithIp('idmef-target', '10.0.0.13', 'Data Center');
        $this->createComputerWithIp('idmef-sensor', '10.0.0.14', 'R&D Lab');

        $actual = (new AddLocation())->enrich($message);

        $this->assertEquals($expected, $actual);
    }

    #[DataProvider('provideMessagesWithGeoLocation')]
    public function testEnrichAddsGeoLocationForMatchingAsset(stdClass $message, stdClass $expected): void
    {
        $this->login('glpi', 'glpi');
        $this->createComputerWithIp('idmef-gps-no-altitude', '10.0.0.21', 'Paris HQ', 48.8566, 2.3522);
        $this->createComputerWithIp('idmef-gps-with-altitude', '10.0.0.22', 'Alpine Station', 45.503, 6.566, 1234.5);

        $actual = (new AddLocation())->enrich($message);

        $this->assertEquals($expected, $actual);
    }

    public function testEnrichDoesNotOverrideExistingLocation(): void
    {
        $this->login('glpi', 'glpi');
        $this->createComputerWithIp('idmef-keep-location', '10.0.0.31', 'Existing Site');

        $message = new stdClass();
        $message->Source = new stdClass();
        $message->Source->IP = '10.0.0.31';
        $message->Source->Location = 'Manual override';

        $actual = (new AddLocation())->enrich($message);

        $this->assertSame('Manual override', $actual->Source->Location);
    }

    public function testEnrichDoesNothingForUnknownIp(): void
    {
        $message = new stdClass();
        $message->Target = new stdClass();
        $message->Target->IP = '10.0.0.99';

        $actual = (new AddLocation())->enrich($message);

        $this->assertObjectNotHasProperty('Location', $actual->Target);
        $this->assertObjectNotHasProperty('GeoLocation', $actual->Target);
    }
}
