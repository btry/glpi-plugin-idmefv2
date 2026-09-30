<?php

namespace GlpiPlugin\Idmefv2\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Exception\RedirectException;
use Glpi\Http\Firewall;
use Glpi\Security\Attribute\SecurityStrategy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConfigController extends AbstractController
{
    #[SecurityStrategy(Firewall::STRATEGY_AUTHENTICATED)]
    #[Route(
        path: 'front/config.form.php',
        name: 'idmefv2_config',
        methods: ['GET', 'POST'])]
    public function alert(Request $request): Response
    {
        throw new RedirectException('../../../front/config.form.php?forcetab=GlpiPlugin%5CIdmefv2%5CConfig$1');
    }
}