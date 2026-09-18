<?php

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
