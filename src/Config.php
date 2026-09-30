<?php

namespace GlpiPlugin\Idmefv2;

use CommonGLPI;
use CommonDBTM;
use Config as GlpiConfig;
use Glpi\Application\View\TemplateRenderer;
use Override;
use Session;

class Config extends CommonDBTM
{

    public static string $rightname = 'config';
    protected static bool $notable = true;
    private const CONFIG_CONTEXT = 'plugin:idmefv2';


    #[Override]
    public static function getTypeName($nb = 0)
    {
        return plugin_idmefv2_getFriendlyName();
    }

    #[Override]
    public static function getIcon(): string
    {
        return 'fa-solid fa-fire';
    }

    #[Override]
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        $tabName = '';
        if (!$withtemplate) {
            if ($item->getType() == GlpiConfig::class) {
                $tabName = GlpiConfig::createTabEntry(self::getTypeName(), 0, $item::class, self::getIcon());
            }
        }
        return $tabName;
    }

    /**
     * Show tab
     *
     * @param CommonGLPI $item
     * @param int $tabnum
     * @param int $withtemplate
     * @return void
     */
    #[Override]
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        /** @var CommonDBTM $item */
        if (!$item->getType() == GlpiConfig::class) {
            return true;
        }
        $config = new self();
        $config->showForm($item->getId());
    }
        #[Override]
    public function showForm($ID, $options = [])
    {
        $canedit  = Session::haveRight(GlpiConfig::$rightname, UPDATE);
        $current_config = GlpiConfig::getConfigurationValues(self::CONFIG_CONTEXT);

        $current_config['require_http_client_certificate'] ??= '1';
        $ca_path = self::getCaCertificatePath();
        $client_ca_public_key = is_readable($ca_path) ? (string) file_get_contents($ca_path) : '';

        $renderer = TemplateRenderer::getInstance();
        $renderer->display('@idmefv2/pages/Config.html.twig', [
            'can_edit'                   => $canedit,
            'context'                    => self::CONFIG_CONTEXT,
            'current_config'             => $current_config,
            'client_ca_public_key'       => $client_ca_public_key,
        ]);

        return true;
    }

    public static function configUpdate(array $input): array
    {
        return self::handleClientCaPublicKey($input);
    }

    public static function handleClientCaPublicKey(array $input): array
    {
        if (!array_key_exists('client_ca_public_key', $input)) {
            return $input;
        }

        $client_ca_public_key = trim((string) $input['client_ca_public_key']);
        unset($input['client_ca_public_key']);

        if ($client_ca_public_key !== '' && @openssl_pkey_get_public($client_ca_public_key) === false) {
            Session::addMessageAfterRedirect(
                __s('The client CA public key must be a valid PEM public key or certificate.', 'idmefv2'),
                false,
                ERROR
            );
            return $input;
        }

        $ca_path = self::getCaCertificatePath();
        $ca_directory = dirname($ca_path);
        if (!is_dir($ca_directory) && !@mkdir($ca_directory, 0750, true) && !is_dir($ca_directory)) {
            Session::addMessageAfterRedirect(
                __s('Unable to create the client CA certificate directory.', 'idmefv2'),
                false,
                ERROR
            );
            return $input;
        }

        if ($client_ca_public_key === '') {
            if (is_file($ca_path) && !@unlink($ca_path)) {
                Session::addMessageAfterRedirect(
                    __s('Unable to remove the client CA public key file.', 'idmefv2'),
                    false,
                    ERROR
                );
            }
            return $input;
        }

        if (@file_put_contents($ca_path, $client_ca_public_key . "\n", LOCK_EX) === false) {
            Session::addMessageAfterRedirect(
                __s('Unable to save the client CA public key file.', 'idmefv2'),
                false,
                ERROR
            );
        }

        return $input;
    }

    public static function getCaCertificatePath(): string
    {
        return GLPI_PLUGIN_DOC_DIR . '/idmefv2/mtls/ca.pem';
    }

    /**
     * Get config value
     *
     * @param $name     string   config name
     *
     * @return mixed
     */
    public static function getConfigurationValue(string $name)
    {
        return GlpiConfig::getConfigurationValue(self::CONFIG_CONTEXT, $name);
    }
}
