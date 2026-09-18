<?php

namespace GlpiPlugin\Idmefv2;

use Exception;
use GlpiPlugin\Idmefv2\Enrich\AddLocation;
use GlpiPlugin\Idmefv2\Idmefv2 as GlpiPluginIdmefv2;
use Idmefv2;
use InvalidArgumentException;
use Plugin;
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
