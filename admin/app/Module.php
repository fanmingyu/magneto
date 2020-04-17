<?php

namespace Group\Magneto\Admin;

use Group\Common\Phalcon\Module as CommonModule;
use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;
use Group\Common\Library\PhalconLib;
use Group\Common\Library\Logger;
use Group\Common\Extensions\Enum\EnumExceptionLevel;
use Group\Common\Extensions\RPC\RpcClientAdapter;
use Group\Common\Extensions\RPC\LocalClientAdapter;

/**
 * 项目名称
 */
define('APP_NAME', 'magneto');

/**
 * 环境
 */
define('ENV', get_cfg_var('phalcon.env'));

class Module extends CommonModule {

    public function registerAutoloaders() {
        if (defined('WEIXIN_API')) {
            $controllerDir = array(
                __NAMESPACE__ . '\Controllers' => APP_ROOT_DIR . '/api/app/controllers',
            );
        } else {
            $controllerDir = array(
                __NAMESPACE__ . '\Controllers' => __DIR__ . '/controllers/',
                __NAMESPACE__ . '\Controllers\Api' => __DIR__ . '/controllers/api',
            );
        }

        $loader = new \Phalcon\Loader();
        $loader->registerNamespaces($controllerDir + array(
            __NAMESPACE__ . '\Models' => __DIR__ . '/models/',
            __NAMESPACE__ . '\Daos' => __DIR__ . '/daos/',
            __NAMESPACE__ . '\Service' => __DIR__ . '/services/',
            __NAMESPACE__ . '\Plugins' => __DIR__ . '/plugins',
            'Group\Magneto\Collections' => APP_ROOT_DIR . '/admin/app/collections/',
            'Group\Magneto\Plugins' => APP_ROOT_DIR . '/admin/app/plugins',
        ))->register();
    }

    /**
     *
     * Register the services here to make them module-specific
     *
     */
    public function registerServices() {
        $di = $this->di;
        $config = $di->get('config');

        //get bootstrap obj
        $bootstrap = $di->get('bootstrap');

        $config = $di->get('config');

        define("APP_MODULE_PUB_DIR", APP_MODULE_DIR . '/public');

        //api的路由规则
        if (substr(PHP_SAPI, 0, 3) !== 'cli') {
            $router = $di->getShared('router');
            $router->add('/api/?([a-zA-Z0-9_-]*)/?([a-zA-Z0-9_]*)(/.*)*',
                array('namespace' => 'Group\Magneto\Admin\Controllers\Api', 'controller' => 1, 'action' => 2, 'params' => 3));
        }

        set_exception_handler(function ($e) use ($di) {
            $view = $di->get('view');
            $view->setVar('message', $e->getMessage());
            $view->render('common', 'exception');
        });

        // registering a dispatcher
        $di->set('dispatcher', function () use ($di) {
            if (substr(PHP_SAPI, 0, 3) == 'cli') {
                $dispatcher = new \Phalcon\CLI\Dispatcher();
                return $dispatcher;
            }

            $evtManager = $di->getShared('eventsManager');
            $evtManager->attach("dispatch:beforeException", function ($event, $dispatcher, $exception) {
                switch ($exception->getCode()) {
                    case \Phalcon\Mvc\Dispatcher::EXCEPTION_HANDLER_NOT_FOUND:
                    case \Phalcon\Mvc\Dispatcher::EXCEPTION_ACTION_NOT_FOUND:
                        $dispatcher->forward(array(
                            'controller' => 'error',
                            'action' => 'show404'
                        ));
                        return false;
                }
            });

            //$adminListener = new Plugins\AdminListener($di);
            //$evtManager->attach('dispatch', $adminListener);
            $dispatcher = new \Phalcon\Mvc\Dispatcher();
            $dispatcher->setEventsManager($evtManager);
            $dispatcher->setDefaultNamespace(__NAMESPACE__ . "\\Controllers\\");
            return $dispatcher;
        });

        // set view with volt
        $di->set('view', function() use ($di) {
            $view = new \Phalcon\Mvc\View();
            $view->setViewsDir(__DIR__ . '/views/');
            $view->registerEngines(array(
                ".html" => function($view, $di) {
                    $volt = new \Phalcon\Mvc\View\Engine\Volt($view, $di);
                    $volt->setOptions(array(
                        "compiledPath" => $di->get('config')->view->compiledPath,
                        "compiledExtension" => $di->get('config')->view->compiledExtension,
                    ));
                    $compiler = $volt->getCompiler();
                    $compiler->addExtension(new \Phalcon\Volt\Extension\PhpFunction());
                    $compiler->addFilter('stripSensitive', function($resolvedArgs, $exprArgs) {
                        return 'Group\Magneto\Plugins\ViewUtil::stripSensitive('.$resolvedArgs.')';
                    });
                    return $volt;
                }
            ));
            return $view;
        });

        $di->setShared('redis', function () use ($config) {
            try {
                $redis = \Group\Common\Library\RedisCreator::getRedis($config->redis->toArray(), 1, false);
                $result = $redis->select(12);
                if (!$result) {
                    throw new \Exception('Redis select db failed');
                }
                return $redis;
            } catch (\Exception $e) {
                throw $e;
            }
        });

        //数据库配置
        $dbs = $config->dbs->toArray();
        foreach ($dbs as $dbname => $dbconfig) {
            $di->setShared("db_{$dbname}", function () use ($dbconfig) {
                return new DbAdapter($dbconfig);
            });
        }

        $di->setShared('collectionManager', function() {
            return new \Phalcon\Mvc\Collection\Manager();
        });

    }
}
