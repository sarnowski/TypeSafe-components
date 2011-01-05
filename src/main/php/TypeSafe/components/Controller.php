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

require_once('pinjector/Registry.php');

class Controller {
    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var Configuration
     */
    private $config;

    /**
     * @var array
     */
    private $context = array();

    /**
     * @var array
     */
    private $viewConfig = array();

    /**
     * @param Kernel $kernel
     * @param Registry $registry
     * @return void
     */
    public function _initialize(Kernel $kernel, Registry $registry, Configuration $config) {
        $this->kernel = $kernel;
        $this->registry = $registry;
        $this->config = $config;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set($key, $value) {
        $this->context[$key] = $value;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has($key) {
        return isset($this->context[$key]);
    }

    /**
     * @throws InternalServerErrorException
     * @param string $key
     * @return mixed
     */
    public function get($key) {
        if (!isset($this->context[$key])) {
            throw new InternalServerErrorException("Context key $key not found.");
        }
        return $this->context[$key];
    }

    /**
     * @return array
     */
    public function getContext() {
        return $this->context;
    }

    /**
     * @param string $viewKey
     * @param string $viewName
     * @return void
     */
    public function setView($viewKey, $viewName) {
        $this->viewConfig[$viewKey] = $viewName;
    }

    /**
     * @param string $viewKey
     * @return void
     */
    public function unsetView($viewKey) {
        unset($this->viewConfig[$viewKey]);
    }

    /**
     * @throws InternalServerErrorException
     * @param string $viewKey
     * @return string
     */
    public function getView($viewKey) {
        if (!isset($this->viewConfig[$viewKey])) {
            throw new InternalServerErrorException("View key $viewKey not found.");
        }
        return $this->viewConfig[$viewKey];
    }

    /**
     * @param string $key
     * @param string $defaultView
     * @return void
     */
    public function view($key, $defaultView = null) {
        // which view?
        if (!isset($this->viewConfig[$key])) {
            if (is_null($defaultView)) {
                throw new InternalServerErrorException("View key '$key' not set.");
            } else {
                $view = $defaultView;
            }
        } else {
            $view = $this->viewConfig[$key];
        }

        // component registered for view?
        $handler = $this->kernel->getInstance('Component', $view, true);
        if (!is_null($handler)) {
            foreach ($this->context as $key => $value) {
                $handler->set($key, $value);
            }

            $this->registry->call('ControllerPreFilter', new PreFilterCallback($handler));
            $handler->handle();
            $this->registry->call('ControllerPostFilter', new PostFilterCallback($handler));

            extract($handler->getContext(), EXTR_SKIP);
        } else {
            extract($this->context, EXTR_SKIP);
        }

        // load it
        require($this->config->get('viewsDirectory').'/'.$view.'.php');
    }
}