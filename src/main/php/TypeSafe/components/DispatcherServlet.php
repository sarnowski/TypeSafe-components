<?php
/*
 * Copyright 2011 Tobias Sarnowski
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once('pinjector/Kernel.php');
require_once('TypeSafe/Servlet.php');
require_once('TypeSafe/config/Configuration.php');

require_once('Controller.php');
require_once('ControllerPreFilter.php');
require_once('ControllerPostFilter.php');
require_once('Component.php');

/**
 *
 * @author Tobias Sarnowski
 */
class DispatcherServlet implements Servlet {

    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var Configuration
     */
    private $config;

    /**
     * @var Registry
     */
    private $registry;


    /**
     * @param Kernel $kernel
     * @param Configuration $config
     * @param Registry $registry
     */
    public function __construct(Kernel $kernel, Configuration $config, Registry $registry) {
        $this->kernel = $kernel;
        $this->config = $config;
        $this->registry = $registry;
    }

    /**
     * Handles a request.
     *
     * @param  array $matches
     * @return void
     */
    public function handleRequest($matches) {
        $baseUrl = findBaseUrl($matches[0]);
        define('BASEURL', substr($baseUrl, 0, strlen($baseUrl) - 1));

        if (isset($matches[1])) {
            $request = $matches[1];
        } else {
            $request = $this->config->get('controller');
        }

        // parse request
        $request = explode('/', $request);
        $controllerName = $request[0];

        // get controller and call method
        $controller = $this->kernel->getInstance('Controller', $controllerName);

        if (!($controller instanceof Controller)) {
            throw new InternalServerErrorException("Controller '$controllerName' does not inherit Controller.");
        }

        // hack in registry
        $controller->_initialize($this->kernel, $this->registry, $this->config);

        // method given?
        if (isset($request[1])) {
            $method = $request[1];
        } else {
            $method = '_index';
        }

        // method exists or wildcard?
        if (!method_exists($controller, $method)) {
            if (method_exists($controller, '_catchall')) {
                $method = '_catchall';
            } else {
                throw new NotFoundException("Method '$method' not available");
            }
        }

        // call it
        $this->registry->call('ControllerPreFilter', new PreFilterCallback($controller));
        $view = $controller->$method($matches);
        $this->registry->call('ControllerPostFilter', new PostFilterCallback($controller));

        // register result as view
        $controller->setView('_initial', $view);

        // load the returned view
        $controller->view('_initial');
    }
}

class PreFilterCallback implements RegistryCallback {

    /**
     * @var Controller
     */
    private $controller;

    public function __construct(Controller $controller) {
        $this->controller = $controller;
    }

    /**
     * Will be called for each entry of the key in the registry.
     *
     * @param  mixed $filter
     * @return boolean if the loop has to go on
     */
    public function process($filter) {
        $filter->filterPreController($this->controller);
        return true;
    }
}

class PostFilterCallback implements RegistryCallback {

    /**
     * @var Controller
     */
    private $controller;

    public function __construct(Controller $controller) {
        $this->controller = $controller;
    }

    /**
     * Will be called for each entry of the key in the registry.
     *
     * @param  mixed $filter
     * @return boolean if the loop has to go on
     */
    public function process($filter) {
        $filter->filterPostController($this->controller);
        return true;
    }
}