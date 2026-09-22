<?php

namespace GlpiPlugin\Idmefv2\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Http\Firewall;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Idmefv2\Alert;
use GlpiPlugin\Idmefv2\Config;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AlertController extends AbstractController
{
    #[SecurityStrategy(Firewall::STRATEGY_NO_CHECK)]
    #[Route(
        path: 'alert.php',
        name: 'idmefv2_alert',
        methods: ['GET', 'POST'])]
    public function alert(Request $request): Response
    {
        // Check the requester declared to send a json body
        if ($request->headers->get('Content-Type') !== 'application/json') {
            throw new BadRequestHttpException('Bad request');
        }

        // if method is GET, then throw an exception, workaround bug in GLPI up to 11.0.7
        if ($request->isMethod('GET')) {
            // throw new BadRequestHttpException('Bad request');
            return new Response('', 403);
        }


        $require_client_certificate = Config::getConfigurationValue('require_client_certificate');
        if ($require_client_certificate) {
            $this->validateClientCertificate($request);
        }

        $alert = new Alert((string) $request->getContent());
        $alert->processAlert();

        return new Response(
            $alert->getResponse(),
            Response::HTTP_OK, [
                'Content-Type' => 'application/json'
            ]
        );
    }

    private function validateClientCertificate(Request $request): void
    {
        $raw_certificate = $this->extractClientCertificate($request);
        if ($raw_certificate === null || trim($raw_certificate) === '') {
            throw new AccessDeniedHttpException('Mutual TLS client certificate is required.');
        }

        $verify_status = $request->server->get('SSL_CLIENT_VERIFY');
        if (is_string($verify_status) && in_array(strtoupper($verify_status), ['FAILED', 'INVALID', 'NONE'], true)) {
            throw new AccessDeniedHttpException('Mutual TLS client certificate verification failed.');
        }

        $ca_path = $this->getTrustedCertificateAuthorityPath();
        if ($ca_path === null || !is_readable($ca_path)) {
            throw new AccessDeniedHttpException('Mutual TLS trust store is not configured on this server.');
        }

        $certificate = @openssl_x509_read($raw_certificate);
        if ($certificate === false) {
            throw new AccessDeniedHttpException('Client certificate is not a valid PEM certificate.');
        }

        $ca_certificate = @file_get_contents($ca_path);
        if ($ca_certificate === false || trim($ca_certificate) === '') {
            throw new AccessDeniedHttpException('Mutual TLS trust bundle could not be read.');
        }

        $ca_public_key = @openssl_pkey_get_public($ca_certificate);
        if ($ca_public_key === false) {
            throw new AccessDeniedHttpException('Mutual TLS trust bundle is not a valid PEM certificate/public key file.');
        }

        $verification = @openssl_x509_verify($certificate, $ca_public_key);
        if ($verification !== 1) {
            throw new AccessDeniedHttpException('Client certificate is not trusted by the configured CA.');
        }
    }

    private function extractClientCertificate(Request $request): ?string
    {
        $certificate = $request->server->get('SSL_CLIENT_CERT')
            ?? $request->server->get('SSL_CLIENT_CERTIFICATE')
            ?? $request->server->get('HTTP_X_CLIENT_CERT')
            ?? $request->server->get('HTTP_X_SSL_CLIENT_CERT');

        if (!is_string($certificate) || trim($certificate) === '') {
            return null;
        }

        return str_replace(['\\n', '\r\n'], "\n", $certificate);
    }

    private function getTrustedCertificateAuthorityPath(): ?string
    {
        foreach (['GLPI_IDMEFV2_CLIENT_CA', 'IDMEFV2_CLIENT_CA', 'GLPI_IDMEFV2_CLIENT_CA_FILE', 'IDMEFV2_CLIENT_CA_FILE'] as $env_name) {
            $value = getenv($env_name);
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        $fallback = dirname(__DIR__, 3) . '/config/mtls/ca.pem';
        $fallback = GLPI_PLUGIN_DOC_DIR . '/idmefv2/mtls/ca.pem';
        return is_readable($fallback) ? $fallback : null;
    }
}
