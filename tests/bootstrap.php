<?php

use Glpi\Application\Environment;
use Glpi\Application\ResourcesChecker;
use Glpi\Cache\CacheManager;
use Glpi\Cache\SimpleCache;
use Glpi\Kernel\Kernel;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

define('TEST_PLUGIN_NAME', 'idmefv2');
define('GLPI_URI', getenv('GLPI_URI') ?: 'http://localhost');
define('TU_USER', '_test_user');
define('TU_PASS', 'PhpUnit_4');
define('TU_FIXTURE_PATH', __DIR__ . '/fixtures');

ini_set('session.use_cookies', 0); //disable session cookies

// Check the resources state before trying to be sure that the tests are executed with up-to-date dependencies.
require_once dirname(__DIR__, 3) . '/src/Glpi/Application/ResourcesChecker.php';
(new ResourcesChecker(dirname(__DIR__, 3)))->checkResources();

global $GLPI_CACHE;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

// Add a PSR4 loader for test framework classes
spl_autoload_register(function ($class) {
    $namespace = 'tests\\units\\GlpiPlugin\\Idmefv2\\';
    $len = strlen($namespace);
    if (strncmp($namespace, $class, $len) !== 0) {
        return false;
    }
    $relative_class = substr($class, $len);
    $file = dirname(__FILE__) . '/src/' . str_replace('_', DIRECTORY_SEPARATOR, $relative_class) . '.php';
    // if the file exists, require it
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    return false;
});

$kernel = new Kernel(Environment::TESTING->value);
$kernel->boot();

if (!file_exists(GLPI_CONFIG_DIR . '/config_db.php')) {
    echo("\nConfiguration file for tests not found\n\nrun: php bin/console database:install --env=testing ...\n\n");
    exit(1);
}
if (Update::isUpdateMandatory()) {
    echo 'The GLPI codebase has been updated. The update of the GLPI database is necessary.' . PHP_EOL;
    exit(1);
}

//init cache
if (file_exists(GLPI_CONFIG_DIR . DIRECTORY_SEPARATOR . CacheManager::CONFIG_FILENAME)) {
    // Use configured cache for cache tests
    $cache_manager = new CacheManager();
    $GLPI_CACHE = $cache_manager->getCoreCacheInstance();
} else {
    // Use "in-memory" cache for other tests
    $GLPI_CACHE = new SimpleCache(new ArrayAdapter());
}

// To prevent errors caught by `error` asserter to also generate logs, unregister GLPI error handler.
// Errors that are pushed directly to logs (SQL errors/warnings for instance) will still have to be explicitly
// validated by `$this->has*LogRecord*()` asserters, otherwise it will make test fails.
set_error_handler(null);
