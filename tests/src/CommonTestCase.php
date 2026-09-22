<?php

namespace tests\units\GlpiPlugin\Idmefv2;

use Auth;
use CommonDBTM;
use Entity;
use Glpi\Inventory\Conf;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Session;
use Toolbox;

class CommonTestCase extends TestCase
{
    /** @var int $debugMode save state of GLPI debug mode */
    private $debugMode = null;

    protected ?string $str = null;

    protected function disableDebug()
    {
        $this->debugMode = Session::DEBUG_MODE;
        if (isset($_SESSION['glpi_use_mode'])) {
            $this->debugMode = $_SESSION['glpi_use_mode'];
        }
        Toolbox::setDebugMode(Session::NORMAL_MODE);
    }

    protected function restoreDebug()
    {
        Toolbox::setDebugMode($this->debugMode);
    }

    protected function setUp(): void
    {
        $this->resetGLPILogs();
    }

    protected function tearDown(): void
    {
        $logs = ['php-errors.log', 'sql-errors.log'];
        foreach ($logs as $log) {
            if (!file_exists(GLPI_LOG_DIR . '/' . $log)) {
                // continue;
            }

            $log_content = file_get_contents(GLPI_LOG_DIR . "/$log");
            $this->assertEquals('', $log_content, "log not empty");
        }
    }

    protected function resetGLPILogs()
    {
        // Reset error logs
        file_put_contents(GLPI_LOG_DIR . "/sql-errors.log", '');
        file_put_contents(GLPI_LOG_DIR . "/php-errors.log", '');
    }

    /**
     * @deprecated not replaced
     *
     * @return void
     */
    protected function setupGLPIFramework(): void
    {
        return;
    }

    protected function login(string $name, string $password, $noauto = false)
    {
        Session::start();
        $auth = new Auth();
        $this->disableDebug();
        $result = $auth->login($name, $password, $noauto);
        $this->restoreDebug();
        $_SESSION['MESSAGE_AFTER_REDIRECT'] = [];

        return $result;
    }

    protected function logout()
    {
        Session::destroy();
        Session::start();
    }

    /**
     * Get a unique random string
     */
    protected function getUniqueString()
    {
        if (is_null($this->str)) {
            return $this->str = uniqid('str');
        }
        return $this->str .= 'x';
    }

    /**
     * @template T of CommonDBTM
     * @param class-string<T> $itemtype itemtype to create
     * @param array $crit
     * @return T
     */
    protected function getItem(string $itemtype, array $crit = []): CommonDBTM
    {
        $item = new $itemtype();
        $this->assertTrue($item->getFromDBByRequest($crit));
        return $item;
    }


    /**
     * Create an item of the given itemtype
     *
     * @template T of CommonDBTM
     * @param class-string<T> $itemtype itemtype to create
     * @param array $input
     * @return T
     */
    protected function createItem(string $itemtype, array $input = []): CommonDBTM
    {
        global $DB;

        $this->handleDeprecations($itemtype, $input);

        /** @var CommonDBTM */
        $item = new $itemtype();

        // set random name if not already set
        if (!isset($item->fields['name']) && $DB->fieldExists($item->getTable(), 'name')) {
            if (!isset($input['name'])) {
                $input['name'] = $this->getUniqueString();
            }
        }

        // assign entity if not already set
        if ($item->isEntityAssign()) {
            $entity = 0;
            if (Session::getLoginUserID(true)) {
                $entity = Session::getActiveEntity();
            }
            if (!isset($input[Entity::getForeignKeyField()])) {
                $input[Entity::getForeignKeyField()] = $entity;
            }
        }

        // assign recursiviy if not already set
        if ($item->maybeRecursive()) {
            if (!isset($input['is_recursive'])) {
                $input['is_recursive'] = 0;
                // if (Session::getLoginUserID(true)) {
                //     $input['is_recursive'] = Session::haveRecursiveAccessToEntity($entity) ? 1 : 0;
                // }
            }
        }

        $item->add($input);
        $this->assertFalse($item->isNewItem(), $this->getSessionMessage());

        // Reload the item to ensure that all fields are set
        $this->assertTrue($item->getFromDB($item->getID()));

        return $item;
    }

    /**
     * @deprecated use createItems insteaad
     *
     * @param array $batch
     * @return array
     */
    public function getItems(array $batch): array
    {
        return $this->createItems($batch);
    }

    public function createItems(array $batch): array
    {
        $output = [];

        foreach ($batch as $itemtype => $items) {
            foreach ($items as $data) {
                $item = $this->createItem($itemtype, $data);
                $output[$itemtype][$item->getID()] = $item;
            }
        }

        return $output;
    }

    public function updateItem(CommonDBTM $item, array $input): CommonDBTM
    {
        $success = $item->update(['id' => $item->fields['id']] + $input);
        $this->assertTrue($success);
        return $item;
    }

    public function deleteItem(CommonDBTM $item, bool $force = false): bool
    {
        $success = $item->delete($item->fields, $force);
        $this->assertTrue($success);
        return $success;
    }

    protected function getSessionMessage(): string
    {
        if (
            isset($_SESSION['MESSAGE_AFTER_REDIRECT'][INFO])
            || isset($_SESSION['MESSAGE_AFTER_REDIRECT'][WARNING])
            || isset($_SESSION['MESSAGE_AFTER_REDIRECT'][ERROR])
        ) {
            return '';
        }

        $messages = '';
        if (isset($_SESSION['MESSAGE_AFTER_REDIRECT'][INFO])) {
            $messages .= implode(' ', $_SESSION['MESSAGE_AFTER_REDIRECT'][INFO]);
        }
        if (isset($_SESSION['MESSAGE_AFTER_REDIRECT'][WARNING])) {
            $messages .= ' ' . implode(' ', $_SESSION['MESSAGE_AFTER_REDIRECT'][WARNING]);
        }
        if (isset($_SESSION['MESSAGE_AFTER_REDIRECT'][ERROR])) {
            $messages .= ' ' . implode(' ', $_SESSION['MESSAGE_AFTER_REDIRECT'][ERROR]);
        }
        return $messages;
    }

    /**
     * Handle deprecations in GLPI
     * Helps to make unit tests without deprecations warnings, accross 2 version of GLPI
     */
    private function handleDeprecations(&$itemtype, &$input): void
    {
    }

    protected function importInventory(array $files)
    {
        $inventory = new Conf();
        return $inventory->importFiles($files);
    }

    /**
     * Create an entity and switch to it
     *
     * @return int
     */
    protected function isolateInEntity(): int
    {
        $entity      = new Entity();
        $rand        = mt_rand();
        $entities_id = $entity->add([
            'name'        => "test sub entity $rand",
            'entities_id' => 0,
        ]);

        $success = Session::changeActiveEntities($entities_id);
        $this->assertTrue($success, 'Failed to change active entity');

        return $entities_id;
    }

    /**
     * Call a private method, and get its return value.
     *
     * @param mixed     $instance   Class instance
     * @param string    $methodName Method to call
     * @param mixed     ...$arg     Method arguments
     *
     * @return mixed
     */
    protected function callPrivateMethod($instance, string $methodName, mixed ...$args)
    {
        $method = new ReflectionMethod($instance, $methodName);
        if (version_compare(PHP_VERSION, '8.1.0') < 0) {
            $method->setAccessible(true);
        }

        return $method->invoke($instance, ...$args);
    }
}
