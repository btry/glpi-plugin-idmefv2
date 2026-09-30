<?php

namespace tests\units\GlpiPlugin\Idmefv2;

use GlpiPlugin\Idmefv2\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    private string $caPath;
    private bool $caFileExisted = false;
    private ?string $caFileContents = null;

    public function testConfigUpdateSavesValidPemWithoutReturningItForConfigurationStorage(): void
    {
        $this->caPath = dirname(__DIR__) . '/fixtures/CA/test_ca_pubkey.pem';
        $publicKey = file_get_contents($this->caPath);

        $result = Config::configUpdate([
            'client_ca_public_key' => $publicKey,
            'require_http_client_certificate' => 1,
        ]);

        $this->assertSame(['require_http_client_certificate' => 1], $result);
        $actualContents = file_get_contents(Config::getCaCertificatePath());
        $this->assertSame($publicKey, $actualContents);
    }

    public function testConfigUpdateRejectsInvalidPemAndPreservesExistingFile(): void
    {
        file_put_contents(Config::getCaCertificatePath(), 'previous CA contents');

        $result = Config::configUpdate([
            'client_ca_public_key' => 'not a PEM public key',
            'require_http_client_certificate' => 1,
        ]);

        $this->assertSame(['require_http_client_certificate' => 1], $result);
        $this->assertSame('previous CA contents', file_get_contents(Config::getCaCertificatePath()));
    }

    public function testConfigUpdateRemovesFileWhenPemIsEmpty(): void
    {
        file_put_contents(Config::getCaCertificatePath(), 'previous CA contents');

        $result = Config::configUpdate([
            'client_ca_public_key' => " \n ",
            'require_http_client_certificate' => 0,
        ]);

        $this->assertSame(['require_http_client_certificate' => 0], $result);
        $this->assertFileDoesNotExist(Config::getCaCertificatePath());
    }

    public function testConfigUpdateLeavesInputUntouchedWhenPemIsNotSubmitted(): void
    {
        $input = ['require_http_client_certificate' => 1];

        $this->assertSame($input, Config::configUpdate($input));
        if ($this->caFileExisted) {
            $this->assertSame($this->caFileContents, file_get_contents($this->caPath));
        } else {
            $this->assertFileDoesNotExist(Config::getCaCertificatePath());
        }
    }
}
