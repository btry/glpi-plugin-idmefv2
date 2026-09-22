# IDMEFv2 plugin for GLPI

<!-- ![GLPI Banner](https://user-images.githubusercontent.com/29282308/31666160-8ad74b1a-b34b-11e7-839b-043255af4f58.png) -->

[![License GPL 3.0](https://img.shields.io/badge/License-GPL%203.0-blue.svg)](https://github.com/IDMEFv2/GLPI-Plugin/blob/main/LICENSE)
[![Project Status: Active](http://www.repostatus.org/badges/latest/active.svg)](http://www.repostatus.org/#active)
[![GLPI 11](https://img.shields.io/badge/GLPI-12.x-orange.svg)](https://glpi-project.org/)

## Description

This is an experimental plugin ! The security of the plugin is not satisfying yet and it **WILL** expose sensitive data.

The IDMEFv2 plugin for GLPI adds native support for receiving and enriching IDMEFv2 alerts directly from security tools and monitoring systems.

It exposes a dedicated webhook-style endpoint to accept alert payloads, validate them against the IDMEFv2 specification, and enrich the alert with information already present in GLPI such as asset locations, hostnames, and related metadata.

This makes it easier to correlate external security events with the inventory managed in GLPI on order to add possibily crucial information to potentially incomplete IDMEFv2 messages.

## Features

- Secure endpoint for IDMEFv2 alert ingestion
- Validation of incoming alert payloads
- Enrichment of IP-based entities with GLPI inventory data
- Support for location-based enrichment and geolocation hints
- Mutual TLS support for trusted alert sources
- Native integration with the GLPI plugin system

## Endpoint

The plugin exposes the alert endpoint at, relativeli to the web root of GLPI:

- /plugins/idmefv2/alert.php

This endpoint accepts IDMEFv2 JSON payloads and returns a JSON response.

The request must present the HTTP header "Content-type: application/json".

## Requirements

- GLPI >= 12.0.0 and < 13.0.0
- PHP 8.3+
- Composer dependencies installed in the plugin directory

## Installation

1. Place the plugin in the GLPI plugins directory.
2. Install PHP dependencies from the plugin folder:

   ```bash
   composer install
   ```

3. Enable the plugin in GLPI.
4. Configure the plugin settings and the trusted certificate authority if you use mutual TLS.

## Usage

The plugin is designed to receive security alerts from an external sender, typically a SIEM, IDS, or detection pipeline.

A typical flow is:

1. Send an IDMEFv2 JSON payload to the alert endpoint.
2. The plugin validates the message structure and format.
3. Matching IPs are enriched using GLPI asset metadata.
4. The plugin returns the enriched payload as JSON.

Example endpoint:

```text
https://your-glpi-instance/plugins/idmefv2/alert.php
```

## Configuration

The plugin configuration page provides the settings needed to configure the alert processing behavior and security checks.

When using client certificate authentication, configure the certificate authority bundle used to validate the sender certificate before accepting the payload.

## Development

The plugin follows GLPI plugin development guidelines and uses the modern Symfony routing approach for HTTP endpoints.

If you want to contribute:

- work on a dedicated branch
- keep changes focused and tested
- follow the existing GLPI coding standards

## Contributing

### Bug reporting / Feature request

- Open a ticket for each bug or feature request so it can be discussed.

### Contributing code

- Follow the GLPI plugin development guidelines.
- Use a feature branch on your fork.
- Open a pull request for review.

## License

This plugin is distributed under the GPLv3 license.
