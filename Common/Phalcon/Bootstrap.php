<?php

namespace Group\Common\Phalcon;

use Group\Common\Library\TraceSdk;

final class Bootstrap {
    protected $config      = null;
    protected $di          = null;
    protected $application = null;
    protected $loader      = null;
    protected $projectName = '';

    protected $module      = array(
        'classPath' => '',
        'className' => '',
        'mode'      => '',
    );

    // Key is the mode name
    // Value is the module-bootstrap filename
    public static $modeMap     = array(
        'Web'     => 'Module',
        'Cli'     => 'Task',
        'Srv'     => 'Srv',
        'Micro'   => 'Micro',
        'Api'     => 'Srv'
    );

    // Which env are you in? product, dev, test, pre or something else
    protected $env = "dev";

    public function __construct($modulePath, $projectName = '') {
        if (!is_dir($modulePath)) {
            throw new \Exception("Module directory not exists or not a dir");
        }

        $this->projectName = empty($projectName) ? md5($modulePath) : $projectName;

        // Initial, we read two configurations from php.ini
        $env = get_cfg_var("phalcon.env");

        if($env) {
            $this->env = $env;
        }

        if($this->env != 'product') {
            $debug = new \Phalcon\Debug();
            $debug->listen();
        }

        // Constants definition
        define("APP_ENV", $this->env);
        define("APP_MODULE_DIR", rtrim($modulePath, '/') . '/');
        define("APP_ROOT_DIR", rtrim(dirname($modulePath), '/') . '/');
        define("APP_ROOT_COMMON_DIR", APP_ROOT_DIR . 'Common/');
        define("APP_ROOT_COMMON_CONF_DIR", APP_ROOT_COMMON_DIR . 'config/');
        define("APP_ROOT_COMMON_LOAD_DIR", APP_ROOT_COMMON_DIR . 'load/');
        define("APP_ROOT_PUB_DIR", APP_ROOT_DIR . 'public/');
    }

    public function setModule(array $module)
    {
        // @TODO: check
        $this->module = $module;
    }

    protected function initConf()
    {
        // Global config file must exists
        $gConfPath = APP_ROOT_COMMON_CONF_DIR  . APP_ENV . '.php';
        if(!is_file($gConfPath)) {
            throw new \Phalcon\Config\Exception("Global config file not exist, file position: {$gConfPath}");
        }
        $this->config = new \Phalcon\Config($this->load($gConfPath));

        // Module config file must exists
        $mConfPath = APP_MODULE_DIR . "/app/config/" . APP_ENV . ".php";
        if(!is_file($mConfPath)) {
            throw new \Phalcon\Config\Exception("Module config file not exist, file position: {$mConfPath}");
        }
        $mConfig  = new \Phalcon\Config($this->load($mConfPath));

        // Gather module info
        $module = array();
        $mode = ucfirst(strtolower($mConfig->application->mode));
        if ($mode === 'Srv' && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api') === 0) {
            $mode = 'Api';
        }

        $module['mode'] = $mode;
        $module['className'] = $mConfig->application->namespace . self::$modeMap[$module['mode']];
        $module['classPath'] = APP_MODULE_DIR . 'app/' . self::$modeMap[$module['mode']] . ".php";

        // Web or Cli or Srv or Micro or Api
        define("APP_RUN_MODE", $module['mode']);
        $this->setModule($module);

        // Merge them
        $this->config->merge($mConfig);
    }

    /**
     *
     * @param mixed $argv
     *
     * If mode = Web, $argv = $uri
     * If mode = Cli, $argv = <array>
     *    $argv['module'] = xxx
     *    $argv['task']   = yyy
     *    $argv['action'] = zzz
     *    $argv[] = $param1
     *    $argv[] = $param2
     *
     * If mode = Srv, $argv = null
     * If mode = Micro, $argv = null
     *
     */
    public function exec($argv = null)
    {
        $this->initConf();
        $handleMethod = 'exec' . self::$modeMap[APP_RUN_MODE];
        $this->{$handleMethod}($argv);

        $this->sqlLog();
    }

    /**
     * sql日志记录
     */
    private function sqlLog() {
        $di = getDI();
        if (!$di->has('profiler')) {
            return;
        }

        $profiles = $di->getProfiler()->getProfiles();
        if (empty($profiles)) return;

        $digPoint = TraceSdk::digLogStart(__FILE__, __LINE__, 'mysql');
        foreach ($profiles as $profile) {
            if (empty($profile)) continue;

            $sql = $profile->getSQLStatement();
            $op = substr($sql, 0, 6);

            // sql语句显示优化
            $start = stripos($sql, 'select');
            $end = stripos($sql, 'from');
            // count请求不要过滤
            $isCountSql = stripos($sql, 'count(');
            if ($isCountSql === false && $start !== false && $end !== false && $start < $end) {
                $sql = substr($sql, 0, $start + strlen('select')).' * '.substr($sql, $end);
            }

            // 修正参数
            $digPoint['start'] = bcsub(microtime(true), $profile->getTotalElapsedSeconds(), 4);

            $variables = $profile->getSQLVariables();
            TraceSdk::digMysqlEnd($digPoint, $sql, $variables, $op, 'sqlLog');
        }
    }

    /**
     *
     * for mode Web
     *
     */
    protected function execModule($uri = null)
    {
        $this->loader = new \Phalcon\Loader();
        $this->di     = new \Phalcon\DI\FactoryDefault();

        $this->application = new \Phalcon\Mvc\Application();
        $this->application->setDI($this->di);

        $this->load(APP_ROOT_COMMON_LOAD_DIR . 'default-web.php');

        $this->di->setShared('bootstrap', $this);

        // Load module
        require($this->module['classPath']);
        $moduleClass = $this->module['className'];
        $module = new $moduleClass($this->di);

        try {
            echo $this->application->handle($uri)->getContent();
        } catch(\Phalcon\Mvc\Application\Exception $e) {
            $router = $this->di->get('router');
            echo $this->application->handle("/". $router->getDefaultModule().$router->getRewriteUri())->getContent();
        }
    }

    /**
     *
     * for mode Srv
     *
     */
    protected function execSrv()
    {
        $this->loader = new \Phalcon\Loader();
        $this->di     = new \Phalcon\DI\FactoryDefault();

        $this->di->setShared('bootstrap', $this);
        if (APP_RUN_MODE == 'Api') {
            $this->load(APP_ROOT_COMMON_LOAD_DIR . 'default-api.php');
        } else {
            $this->load(APP_ROOT_COMMON_LOAD_DIR . 'default-web.php');
        }

        // Load module
        require($this->module['classPath']);
        $moduleClass = $this->module['className'];
        $module = new $moduleClass($this->di);
        if (APP_RUN_MODE == 'Api') {
            $this->application = new \Phalcon\Mvc\Micro();
            $this->application->setDI($this->di);
            // 处理api逻辑
            $this->handleMicroApi();
        } else {
            require_once(APP_ROOT_COMMON_DIR.'/Extensions/Base/BackendServerBase.php');
            $backend = new \Group\Common\Extensions\BackendServerBase($this->di);
            $backend->setEventsManager($this->di->getShared('eventsManager'));

            $this->application = new \Yar_Server($backend);
            $this->application->handle();
        }
    }

    /**
     *
     * for mode Srv
     * just for test
     *
     */
    public function execSrvforTest()
    {
        $this->initConf();

        $this->loader = new \Phalcon\Loader();
        $this->di     = new \Phalcon\DI\FactoryDefault();

        $this->di->setShared('bootstrap', $this);
        $this->load(APP_ROOT_COMMON_LOAD_DIR . 'default-web.php');

        // Load module
        require($this->module['classPath']);
        $moduleClass = $this->module['className'];
        $module = new $moduleClass($this->di);

        require_once(APP_ROOT_COMMON_DIR.'/Extensions/Base/BackendServerBase.php');
        $backend = new \Group\Common\Extensions\BackendServerBase($this->di);

        // $this->application = new \Yar_Server($backend);
        // $this->application->handle();
    }

    /**
     *
     * for mode Cli
     *
     */
    public function execTask($argv, $di = null)
    {
        $this->initConf();

        $this->loader = new \Phalcon\Loader();

        if(is_null($di) || ! ($di instanceof \Phalcon\DI\FactoryDefault\CLI)) {
            $this->di = new \Phalcon\DI\FactoryDefault\CLI();
        } else {
            $this->di = $di;
        }

        $this->application = new \Phalcon\CLI\Console();
        $this->application->setDI($this->di);

        $this->load(APP_ROOT_COMMON_LOAD_DIR . 'default-cli.php');

        $this->di->setShared('bootstrap', $this);

        // Load module
        require($this->module['classPath']);
        $moduleClass = $this->module['className'];
        $module = new $moduleClass($this->di);

        $this->application->handle($argv);
    }

    /**
     *
     * for mode Micro
     *
     */
    public function execMicro()
    {
        $this->initConf();
        $this->loader = new \Phalcon\Loader();
        $this->di = new \Phalcon\DI\FactoryDefault\CLI();

        $this->application = new \Phalcon\Mvc\Micro();
        $this->application->setDI($this->di);

        $this->load(APP_ROOT_COMMON_CONF_DIR . 'default-micro.php');

        $this->di->setShared('bootstrap', $this);

        return $this->application;

        // ***WARNING*** You need handle it yourself
    }

    private function handleMicroApi() {
        $app = $this->application;

        /**
         * The base route return the list of defined routes for the application.
         * This is not strictly REST compliant, but it helps to base API documentation off of.
         * By calling this, you can quickly see a list of all routes and their methods.
         */
        $app->map('/api/{apiController}/{apiAction}', function($apiController, $apiAction) use ($app) {
            $digPoint = TraceSdk::digLogStart(__FILE__, __LINE__, 'rpc');
            try {
                $apiController = ucfirst($apiController).'Api';
                $apiController = $this->config->application->namespace.'Apis\\'.$apiController;
                if (!class_exists($apiController)) {
                    throw new \Group\Common\Extensions\Exceptions\RpcApiException(
                        'Not Found.',
                        404,
                        array(
                            'dev' => 'That controller '.$apiController.' was not found on the server.',
                            'internalCode' => 'NF1000',
                            'more' => 'Check controller for misspellings.'
                        )
                    );
                }

                $controllerObj = new $apiController();
                if (!is_callable(array($controllerObj, $apiAction))) {
                    throw new \Group\Common\Extensions\Exceptions\RpcApiException(
                        'Not Found.',
                        404,
                        array(
                            'dev' => 'That action ' . $apiAction . ' was not found on the server.',
                            'internalCode' => 'NF1000',
                            'more' => 'Check action for misspellings.'
                        )
                    );
                }

                $result = call_user_func_array(array($controllerObj, $apiAction), array());
                TraceSdk::digLogEnd($digPoint, [
                    'apiController' => $apiController,
                    'apiAction' => $apiAction,
                    'url'=>isset($_SERVER["REQUEST_URI"]) ? $_SERVER['HTTP_HOST'] . $_SERVER["REQUEST_URI"] : $_SERVER["PHP_SELF"]
                ]);

                return $result;
            } catch (\Exception $exception) {
                TraceSdk::record(
                    TraceSdk::LOG_TYPE_EXCEPTION,
                    $exception->getFile(),
                    $exception->getLine(),
                    'rpc',
                    $exception->getMessage()
                );

                // RpcApiException's send method provides the correct response headers and body
                if (is_a($exception, '\\Group\\Common\\Extensions\\Exceptions\\RpcApiException')) {
                    $exception->send();
                } else {
                    $error = $exception->getFile() . ':' . $exception->getLine();
                    $message = $exception->getMessage();
                    $code = $exception->getCode();
                    (new \Group\Common\Extensions\Exceptions\RpcApiException(
                        $message, 500, array('dev'=>$message, 'internalCode'=>$code, 'more'=>$error))
                    )->send();
                }
            }
        })->via(['GET', 'POST']);

        /**
         * The notFound service is the default handler function that runs when no route was matched.
         * We set a 404 here unless there's a suppress error codes.
         */
        $app->notFound(function () use ($app) {
            TraceSdk::record(
                TraceSdk::LOG_TYPE_INFO,
                __FILE__,
                __LINE__,
                'rpc',
                'That route was not found on the server.'
            );

            (new \Group\Common\Extensions\Exceptions\RpcApiException(
                'Not Found.',
                404,
                array(
                    'dev' => 'That route was not found on the server.',
                    'internalCode' => 'NF1000',
                    'more' => 'Check route for misspellings.'
                )
            ))->send();
        });

        /**
         * After a route is run, usually when its Controller returns a final value,
         * the application runs the following function which actually sends the response to the client.
         *
         * The default behavior is to send the Controller's returned value to the client as JSON.
         * However, by parsing the request querystring's 'type' paramter, it is easy to install
         * different response type handlers.  Below is an alternate csv handler.
         */
        $app->after(function() use ($app) {
            // OPTIONS have no body, send the headers, exit
            if ($app->request->getMethod() == 'OPTIONS') {
                $app->response->setStatusCode('200', 'OK');
                $app->response->send();
                return;
            }

            if ($app->response->isSent()) {
                return;
            }

            $records = $app->getReturnedValue();
            TraceSdk::record(TraceSdk::LOG_TYPE_INFO, __FILE__, __LINE__, 'rpc', $records);

            // Respond by default as JSON
            if(!$app->request->get('type') || $app->request->get('type') == 'json') {
                // Results returned from the route's controller.  All Controllers should return an array

                $response = new \Group\Common\Extensions\Base\ApiJSONResponse();
                $response->useEnvelope(false) //this is default behavior
                    ->convertSnakeCase(false) //this is also default behavior
                    ->send($records);

                return;
            } else if($app->request->get('type') == 'csv') {
                $response = new \Group\Common\Extensions\Base\ApiCSVResponse();
                $response->useHeaderRow(true)->send($records);
                return;
            } else {
                TraceSdk::record(
                    TraceSdk::LOG_TYPE_NOTICE,
                    __FILE__,
                    __LINE__,
                    'rpc',
                    'Could not understand type specified by type paramter in query string.'
                );

                (new \Group\Common\Extensions\Exceptions\RpcApiException(
                    'Could not return results in specified format',
                    403,
                    array(
                        'dev' => 'Could not understand type specified by type paramter in query string.',
                        'internalCode' => 'NF1000',
                        'more' => 'Type may not be implemented. Choose either "csv" or "json"'
                    )
                ))->send();
            }
        });

        $app->handle();
    }

    /**
     *
     * for mode Cli
     * just for test
     *
     */
    public function execTaskforTest($argv, $di = null)
    {
        $this->initConf();

        $this->loader = new \Phalcon\Loader();

        if(is_null($di) || ! ($di instanceof \Phalcon\DI\FactoryDefault\CLI)) {
            $this->di = new \Phalcon\DI\FactoryDefault\CLI();
        } else {
            $this->di = $di;
        }

        $this->application = new \Phalcon\CLI\Console();
        $this->application->setDI($this->di);

        $this->load(APP_ROOT_COMMON_LOAD_DIR . 'default-cli.php');

        $this->di->setShared('bootstrap', $this);

        // Load module
        require($this->module['classPath']);
        $moduleClass = $this->module['className'];
        $module = new $moduleClass($this->di);

        //$this->application->handle($argv);
    }

    /**
     *
     * for mode Web
     * just for test
     *
     */
    public function execModuleforTest()
    {
        $this->initConf();

        $this->loader = new \Phalcon\Loader();
        $this->di = new \Phalcon\DI\FactoryDefault();

        $this->load(APP_ROOT_COMMON_LOAD_DIR . 'default-web.php');

        $this->di->setShared('bootstrap', $this);

        require($this->module['classPath']);
        $moduleClass = $this->module['className'];
        $module = new $moduleClass($this->di);

        $this->application = new \Phalcon\Mvc\Application();
        $this->application->setDI($this->di);
    }

    public function load($file)
    {
        $system      = APP_ROOT_DIR;
        $loader      = $this->loader;
        $config      = $this->config;
        $application = $this->application;
        $bootstrap   = $this;
        $di          = $this->di;
        $projectName = $this->projectName;
        return require $file;
    }

    public function dependModule($moduleName)
    {
        // Module config file must exists
        $mConfPath = APP_ROOT_DIR . "/{$moduleName}/app/config/" . APP_ENV . ".php";
        if(!is_file($mConfPath)) {
            throw new \Phalcon\Config\Exception("Module config file not exist, file position: {$mConfPath}");
        }
        $mConfig  = new \Phalcon\Config($this->load($mConfPath));

        // Gather module info
        $runMode = ucfirst(strtolower($mConfig->application->mode));
        $moduleClassName = $mConfig->application->namespace . self::$modeMap[$runMode];
        $moduleClassPath = APP_ROOT_DIR . "/{$moduleName}/app/" . self::$modeMap[$runMode] . ".php";

        $mConfig->merge($this->config);
        $this->setConfig($mConfig);

        require_once($moduleClassPath);
        new $moduleClassName($this->di);
    }

    public function getEnv()
    {
        return $this->env;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function setConfig(\Phalcon\Config $config)
    {
        if(!$this->di->has('config')) {
            $gConfig = new \Phalcon\Config();
        } else {
            $gConfig = $this->di->getConfig();
        }
        $gConfig->merge($config);
        $this->config = $gConfig;
        $this->di->set('config', $gConfig);
    }

    public function getDI()
    {
        return $this->di;
    }

    public function setDI(\Phalcon\DI $di)
    {
        $this->di = $di;
    }

    public function getApplication()
    {
        return $this->application;
    }
}
