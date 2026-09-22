<?php

namespace tests\units\GlpiPlugin\Idmefv2;

use Config;
use CronTask as GLPICronTask;
use DBmysql;
use Glpi\System\Diagnostic\DatabaseSchemaIntegrityChecker;
use GLPIKey;
use GlpiPlugin\Idmefv2\Install;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Depends;
use Plugin;
use Profile;
use ProfileRight;
use Session;
use tests\units\GlpiPlugin\Idmefv2\CommonTestCase;

#[CoversClass(Install::class)]
class PluginInstallTest extends CommonTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        self::login('glpi', 'glpi', true);
    }

    /**
     * Helper method to wipe all plugin data
     *
     * @return void
     */
    protected function wipePlugin()
    {
        /** @var DBmysql */
        global $DB;

        $plugin_name = TEST_PLUGIN_NAME;
        //Drop plugin configuration if exists
        $config = new Config();
        $config->deleteByCriteria(['context' => 'plugin:' . $plugin_name]);

        // Drop tables of the plugin if they exist
        $result = $DB->listTables('glpi_plugin_' . $plugin_name . '_%');
        foreach ($result as $data) {
            $DB->dropTable($data['TABLE_NAME']);
        }
    }

    /**
     * Execute plugin installation in the context if tests
     */
    protected function executeInstallation()
    {
        /** @var DBmysql */
        global $DB;

        $plugin_name = TEST_PLUGIN_NAME;

        $this->assertTrue($DB->connected);
        $this->wipePlugin();

        // Reset logs
        $this->resetGLPILogs();

        $plugin = new Plugin();
        // Since GLPI 9.4 plugins list is cached
        $plugin->checkStates(true);
        $plugin->getFromDBbyDir($plugin_name);
        $this->assertFalse($plugin->isNewItem());

        // Install the plugin
        ob_start();
        $plugin->install($plugin->fields['id']);
        $install_output = ob_get_clean();
        $session_messages = implode(PHP_EOL, $_SESSION['MESSAGE_AFTER_REDIRECT'][ERROR] ?? []);
        $this->assertTrue($plugin->isInstalled($plugin_name), $install_output . PHP_EOL . $session_messages);

        // Enable the plugin
        $success = $plugin->activate($plugin->fields['id']);
        $this->assertTrue($success);
        $plugin->bootPlugins();
        $plugin->init();
        $messages = $_SESSION['MESSAGE_AFTER_REDIRECT'][ERROR] ?? [];
        $messages = implode(PHP_EOL, $messages);
        $this->assertTrue(Plugin::isPluginActive($plugin_name), 'Cannot enable the plugin: ' . $messages);
    }

    public function testInstallPlugin()
    {
        if (!Plugin::isPluginActive(TEST_PLUGIN_NAME)) {
            // For unit test script which expects that installation runs in the tests context
            $this->executeInstallation();
        }
        $plugin = new Plugin();
        $plugin->bootPlugins();
        $plugin->init();
        $this->assertTrue(Plugin::isPluginActive(TEST_PLUGIN_NAME), 'Plugin not activated');
        $this->checkSchema(PLUGIN_IDMEFV2_VERSION);
        $this->test_version_is_consistent_across_files();

        $this->checkConfig();
        $this->checkAutomaticAction();
        $this->checkRights();
        $this->checkDisplayPrefs();
        $this->checkRegisteredClasses();
    }

    public function testConfigurationExists()
    {
        $config = Config::getConfigurationValues(TEST_PLUGIN_NAME);
        $expected = [];
        $diff = array_diff_key(array_flip($expected), $config);
        $this->assertEquals(0, count($diff));

        return $config;
    }

    private function checkSchema(
        string $version,
        bool $strict = true,
        bool $ignore_innodb_migration = false,
        bool $ignore_timestamps_migration = false,
        bool $ignore_utf8mb4_migration = false,
        bool $ignore_dynamic_row_format_migration = false,
        bool $ignore_unsigned_keys_migration = false
    ): bool {
        /** @var DBmysql $DB */
        global $DB;

        $schemaFile = plugin_idmefv2_getSchemaPath($version);

        $checker = new DatabaseSchemaIntegrityChecker(
            $DB,
            $strict,
            $ignore_innodb_migration,
            $ignore_timestamps_migration,
            $ignore_utf8mb4_migration,
            $ignore_dynamic_row_format_migration,
            $ignore_unsigned_keys_migration
        );

        $message = '';
        try {
            $differences = $checker->checkCompleteSchema($schemaFile, true, 'plugin:idmefv2');
        } catch (\Throwable $e) {
            $message = __('Failed to check the sanity of the tables!', 'idmefv2');
            if (isCommandLine()) {
                echo $message . PHP_EOL;
            } else {
                Session::addMessageAfterRedirect($message, false, ERROR);
            }
            return false;
        }

        if (count($differences) > 0) {
            foreach ($differences as $table_name => $difference) {
                $message = null;
                switch ($difference['type']) {
                    case DatabaseSchemaIntegrityChecker::RESULT_TYPE_ALTERED_TABLE:
                        $message = sprintf(__('Table schema differs for table "%s".'), $table_name);
                        break;
                    case DatabaseSchemaIntegrityChecker::RESULT_TYPE_MISSING_TABLE:
                        $message = sprintf(__('Table "%s" is missing.'), $table_name);
                        break;
                    case DatabaseSchemaIntegrityChecker::RESULT_TYPE_UNKNOWN_TABLE:
                        $message = sprintf(__('Unknown table "%s" has been found in database.'), $table_name);
                        break;
                }
                // echo $message . PHP_EOL;
                // echo $difference['diff'] . PHP_EOL;
                $message .= $message . PHP_EOL . $difference['diff'] . PHP_EOL;
            }

            $this->fail($message);
            return false;
        }

        return true;
    }

    private function checkAutomaticAction()
    {
        $cronTask = new GLPICronTask();
        $rows = $cronTask->find([
            'itemtype' => ['LIKE', 'GlpiPlugin\\\\Idmefv2\\\\%'],
        ]);
        $this->assertEquals(0, count($rows));
    }

    protected function checkConfig()
    {
        $plugin_path = Plugin::getPhpDir(TEST_PLUGIN_NAME, true);
        require_once($plugin_path . '/setup.php');

        $expected = [
            'dbversion'              => PLUGIN_IDMEFV2_SCHEMA_VERSION,
        ];

        $config = Config::getConfigurationValues('plugin:' . TEST_PLUGIN_NAME);
        // Ignore keys that are not in the expected list
        $this->assertCount(count($expected), $config);

        $glpi_key = new GLPIKey();
        foreach ($expected as $key => $expected_value) {
            $value = $config[$key];
            if (!empty($value) && $glpi_key->isConfigSecured('plugin:idmefv2', $key)) {
                $value = $glpi_key->decrypt($config[$key]);
            }
            $this->assertEquals($expected_value, $value, "configuration key $key mismatch");
        }
    }

    private function checkRights()
    {
    }

    private function checkRight(string $rightname, array $profiles)
    {
        /** @var DBmysql */
        global $DB;

        $profile_table = Profile::getTable();
        $profile_fk = Profile::getForeignKeyField();
        $profileright_table = ProfileRight::getTable();
        $request = [
            'SELECT' => [
                Profile::getTableField('id'),
                ProfileRight::getTableField('rights'),
            ],
            'FROM' => $profile_table,
            'LEFT JOIN' => [
                $profileright_table => [
                    'FKEY' => [
                        $profile_table => 'id',
                        $profileright_table => $profile_fk,
                    ],
                ],
            ],
            'WHERE' => [
                ProfileRight::getTableField('name') => $rightname,
            ],
        ];

        foreach ($DB->request($request) as $profile_right) {
            if (!isset($profiles[$profile_right['id']])) {
                $this->assertEquals(0, $profile_right['rights']);
            } else {
                $this->assertEquals($profiles[$profile_right['id']], $profile_right['rights']);
            }
        }
    }

    private function checkDisplayPrefs()
    {
    }

    #[Depends('testInstallPlugin')]
    public function test_dashboard_is_configured()
    {
    }

    public function checkRegisteredClasses()
    {
    }

    #[Depends('testInstallPlugin')]
    public function test_version_is_consistent_across_files()
    {
        $setup_version = PLUGIN_IDMEFV2_VERSION;
        $plugin_dir = dirname(__DIR__, 2);
        $composer_file = $plugin_dir . '/composer.json';
        $package_file  = $plugin_dir . '/package.json';
        $package_lock_file  = $plugin_dir . '/package-lock.json';

        $composer = json_decode(file_get_contents($composer_file), true);
        $package = json_decode(file_get_contents($package_file), true);
        $package_lock = json_decode(file_get_contents($package_lock_file), true);

        $this->assertSame($setup_version, $composer['version'] ?? null);
        $this->assertSame($setup_version, $package['version'] ?? null);
        $this->assertSame($setup_version, $package_lock['version'] ?? null);
        // Find in packages[] the entry whose name is idmefv2 and check its version is the same as setup_version
        $idmefv2_package = null;
        foreach ($package_lock['packages'] as $package) {
            if ($package['name'] === 'idmefv2') {
                $idmefv2_package = $package;
                break;
            }
        }
        $this->assertNotNull($idmefv2_package, "Idmefv2 package not found in package-lock.json");
        $this->assertSame($setup_version, $idmefv2_package['version'] ?? null, "Version mismatch for idmefv2 package");

        // Check that SECURITY.md mentions the current version as supported
        $setup_version = preg_replace("#-.*$#", '', $setup_version);
        $setup_version = preg_replace("#\.[0-9]+$#", '.x', $setup_version);
        $security_file = $plugin_dir . '/SECURITY.md';
        // Find a markdown table under the title "Supported Versions"
        $security_content = file_get_contents($security_file);
        $matches = [];
        preg_match('/## Supported Versions\s*\n(.*?)(\n##|\Z)/s', $security_content, $matches);
        $this->assertNotEmpty($matches, "Supported Versions section not found in SECURITY.md");
        $supported_versions_table = trim($matches[1]);
        // Check that the table contains a row with the current version after the section title
        $this->assertNotEmpty($supported_versions_table, "Supported Versions table is empty in SECURITY.md");
        $this->assertStringContainsString($setup_version, $supported_versions_table, "Current version '$setup_version' not found in Supported Versions table in SECURITY.md");
    }

    #[Depends('testInstallPlugin')]
    public function test_tagged_version_is_declared_in_plugin_xml()
    {
        // Test that git is available in the system
        exec('git --version', $output, $return_var);
        if ($return_var !== 0) {
            $this->fail('Git is not available in the system');
            return;
        }

        // check if HEAD is exactly a tagged commit
        unset($output);
        exec('git describe --tags --exact-match 2> /dev/null', $output, $return_var);
        if ($return_var !== 0) {
            $this->markTestSkipped('Current commit is not tagged');
            return;
        }

        // Test that the version in setup.php is the same as the git tag
        $tag = $output[0];
        $setup_version = PLUGIN_IDMEFV2_VERSION;
        $this->assertSame($tag, $setup_version, "Git tag '$tag' does not match version in setup.php '$setup_version'");

        // Chek that the version is not -dev suffixed
        $this->assertStringNotContainsString('-dev', $setup_version, "Version '$setup_version' should not be suffixed with -dev");

        // Check that the version is declared in plugin.xml
        // in root.versions.version[].num field
        $plugin_dir = dirname(__DIR__, 2);
        $plugin_xml_file = $plugin_dir . '/plugin.xml';
        $plugin_xml = simplexml_load_file($plugin_xml_file);
        $versions = $plugin_xml->versions->version;
        $version_found = false;
        foreach ($versions as $version) {
            if ((string) $version->num === $setup_version) {
                $version_found = true;
                break;
            }
        }
        $this->assertTrue($version_found, "Version '$setup_version' is not declared in plugin.xml");
    }

    #[Depends('testInstallPlugin')]
    public function test_changelog_is_updated()
    {
        // Test that git is available in the system
        exec('git --version', $output, $return_var);
        if ($return_var !== 0) {
            $this->fail('Git is not available in the system');
            return;
        }

        // check if HEAD is exactly a tagged commit
        exec('git describe --tags --exact-match 2> /dev/null', $output, $return_var);
        if ($return_var !== 0) {
            $this->markTestSkipped('Current commit is not tagged');
            return;
        }

        // Test that the version in setup.php is present in the changelog
        $setup_version = PLUGIN_IDMEFV2_VERSION;
        $changelog_file = dirname(__DIR__, 2) . '/CHANGELOG.md';
        // Traverse each line of he file without eating all memory in case the file is big
        $handle = fopen($changelog_file, 'r');
        if (!$handle) {
            $this->fail("Cannot open changelog file '$changelog_file'");
            return;
        }
        $version_found = false;
        $limit = 30;
        while (($line = fgets($handle)) !== false) {
            if (strpos($line, "## [$setup_version]") === 0) {
                $version_found = true;
                break;
            }
            $limit--;
            if ($limit <= 0) {
                break;
            }
        }
        fclose($handle);
        $this->assertTrue($version_found, "Version '$setup_version' not found in CHANGELOG.md");
    }
}
