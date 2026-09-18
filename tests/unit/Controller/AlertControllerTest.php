<?php

namespace tests\units\Glpi\Plugin\Idmefv2\Controller;

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Idmefv2\Controller\AlertController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function PHPUnit\Framework\assertJsonStringEqualsJsonString;

#[CoversClass(AlertController::class)]
final class AlertControllerTest extends TestCase
{
    public function testAcceptsValidMutualTlsClientCertificate(): void
    {
        ['ca_pem' => $caPem, 'ca_key_pem' => $caKeyPem] = $this->createCertificateAuthority();
        $this->writeTrustedCaCertificate($caPem);
        $clientPem = $this->createSignedClientCertificate('idmefv2-client', $caPem, $caKeyPem);

        $analyzer = new stdClass();
        $analyzer->IP = '10.0.0.5';
        $analyzer->Name = 'test-analyzer';
        $message = new StdClass();
        $message->Version = '2.D.V08';
        $message->ID = Uuid::uuid4()->toString();
        $message->Analyzer = $analyzer;

        $request = Request::create(
            '/plugins/idmefv2/alert',
            'POST',
            [],
            [],
            [],
            [
                'SSL_CLIENT_CERT' => $clientPem,
                'SSL_CLIENT_VERIFY' => 'SUCCESS',
            ],
            json_encode($message)
        );

        $response = (new AlertController())->alert($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString('{"Version":"2.D.V08","ID":"' . $message->ID .'","Analyzer":{"IP":"10.0.0.5","Name":"test-analyzer"}}', $response->getContent());
    }

    public function testRejectsFailedMutualTlsCertificateValidation(): void
    {
        $request = Request::create(
            '/plugins/idmefv2/alert',
            'POST',
            [],
            [],
            [],
            [
                'SSL_CLIENT_CERT' => 'not-a-certificate',
                'SSL_CLIENT_VERIFY' => 'FAILED',
            ],
            '{"Analyzer":{"IP":"10.0.0.5"}}'
        );

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Mutual TLS client certificate verification failed.');

        (new AlertController())->alert($request);
    }

    private function createCertificateAuthority(): array
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        $this->assertNotFalse($privateKey);

        $csr = openssl_csr_new([
            'CN' => 'IDMEFv2 Test CA',
        ], $privateKey, ['digest_alg' => 'sha256']);
        $this->assertNotFalse($csr);

        $certificate = openssl_csr_sign($csr, null, $privateKey, 3650, ['digest_alg' => 'sha256']);
        $this->assertNotFalse($certificate);

        $caPem = '';
        openssl_x509_export($certificate, $caPem);

        $caKeyPem = '';
        openssl_pkey_export($privateKey, $caKeyPem);

        return [
            'ca_pem' => $caPem,
            'ca_key_pem' => $caKeyPem,
        ];
    }

    private function createSignedClientCertificate(string $commonName, string $caPem, string $caKeyPem): string
    {
        $caCertificate = openssl_x509_read($caPem);
        $this->assertNotFalse($caCertificate);

        $caPrivateKey = openssl_pkey_get_private($caKeyPem);
        $this->assertNotFalse($caPrivateKey);

        $clientKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        $this->assertNotFalse($clientKey);

        $clientCsr = openssl_csr_new([
            'CN' => $commonName,
        ], $clientKey, ['digest_alg' => 'sha256']);
        $this->assertNotFalse($clientCsr);

        $clientCertificate = openssl_csr_sign($clientCsr, $caCertificate, $caPrivateKey, 365, ['digest_alg' => 'sha256']);
        $this->assertNotFalse($clientCertificate);

        $clientPem = '';
        openssl_x509_export($clientCertificate, $clientPem);

        return $clientPem;
    }

    private function writeTrustedCaCertificate(string $caPem): void
    {
        $caDirectory = GLPI_PLUGIN_DOC_DIR . '/idmefv2/mtls';
        if (!is_dir($caDirectory) && !mkdir($caDirectory, 0o755, true) && !is_dir($caDirectory)) {
            $this->fail(sprintf('Unable to create CA directory: %s', $caDirectory));
        }

        $caFile = $caDirectory . '/ca.pem';
        $written = file_put_contents($caFile, $caPem);
        $this->assertNotFalse($written);
    }
}
