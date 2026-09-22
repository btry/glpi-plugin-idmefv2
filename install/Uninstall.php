<?php

namespace GlpiPlugin\Idmefv2;

use Config;
use CronTask as GlpiCronTask;
use DBmysql;
use DisplayPreference;
use Glpi\Dashboard\Dashboard;
use ProfileRight;
use RuntimeException;

class Uninstall
{
    public function uninstall()
    {
        $this->deleteTables();
        $this->deleteConfig();
        $this->deleteAutomaticActions();
        $this->deleteRights();
        $this->deleteDisplayPrefs();
        $this->deleteDashboard();

        return true;
    }

    private function deleteTables()
    {
        /** @var DBmysql $DB */
        global $DB;

        $iterator = $DB->listTables('glpi_plugin_idmefv2_%');
        foreach ($iterator as $table) {
            $DB->dropTable($table['TABLE_NAME']);
        }
    }

    private function deleteConfig()
    {
        $config = new Config();
        if (!$config->deleteByCriteria(['context' => 'plugin:idmefv2'])) {
            throw new RuntimeException('Error while deleting config');
        }
    }

    private function deleteRights()
    {
        $profile_right = new ProfileRight();
        if (
            !$profile_right->deleteByCriteria([
                'name' => ['LIKE', 'idmefv2:%'],
            ])
        ) {
            throw new RuntimeException('Error while deleting rights');
        }
    }

    public function deleteAutomaticActions()
    {
        $actions = [
        ];

        foreach ($actions as $itemtype) {
            $cron_task = new GlpiCronTask();
            $cron_task->deleteByCriteria([
                'itemtype' => $itemtype,
            ]);
        }
    }

    private function deleteDisplayPrefs()
    {
    }

    private function deleteDashboard()
    {
    }
}
