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
        return 'fa-solid fa-xxxx';
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

        $renderer = TemplateRenderer::getInstance();
        $renderer->display('@idmefv2/pages/Config.html.twig', [
            'can_edit'                   => $canedit,
            'context'                    => self::CONFIG_CONTEXT,
            'current_config'             => $current_config,
        ]);

        return true;
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
