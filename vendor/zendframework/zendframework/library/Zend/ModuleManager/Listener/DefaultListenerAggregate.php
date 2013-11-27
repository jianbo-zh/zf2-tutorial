<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\ModuleManager\Listener;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\ModuleManager\ModuleEvent;
use Zend\Stdlib\CallbackHandler;

/**
 * Default listener aggregate
 */
class DefaultListenerAggregate extends AbstractListener implements
    ListenerAggregateInterface
{
    /**
     * @var array
     */
    protected $listeners = array();

    /**
     * @var ConfigMergerInterface
     */
    protected $configListener;

    /**
     * Attach one or more listeners
     *
     * @param  EventManagerInterface $events
     * @return DefaultListenerAggregate
     */
    public function attach(EventManagerInterface $events)
    {
        $options                     = $this->getOptions();
        $configListener              = $this->getConfigListener();
        $locatorRegistrationListener = new LocatorRegistrationListener($options);

        // High priority, we assume module autoloading (for FooNamespace\Module classes) should be available before anything else
        $this->listeners[] = $events->attach(new ModuleLoaderListener($options));	// **绑定 模块类自动加载
        $this->listeners[] = $events->attach(ModuleEvent::EVENT_LOAD_MODULE_RESOLVE, new ModuleResolverListener);	// **绑定　创建一个模块对象
        // High priority, because most other loadModule listeners will assume the module's classes are available via autoloading
        $this->listeners[] = $events->attach(ModuleEvent::EVENT_LOAD_MODULE, new AutoloaderListener($options), 9000); // **绑定　自动加载模块的自动加载配置

        if ($options->getCheckDependencies()) {
            $this->listeners[] = $events->attach(ModuleEvent::EVENT_LOAD_MODULE, new ModuleDependencyCheckerListener, 8000);
        }

        $this->listeners[] = $events->attach(ModuleEvent::EVENT_LOAD_MODULE, new InitTrigger($options));	// ** 绑定 模块的 init初始化
        $this->listeners[] = $events->attach(ModuleEvent::EVENT_LOAD_MODULE, new OnBootstrapListener($options));	// 程序引导bootstrap时，调用模块的bootstrap
        $this->listeners[] = $events->attach($locatorRegistrationListener);		// ** 创建模块别名，和设置模块服务实例（服务管理器）
        $this->listeners[] = $events->attach($configListener);		// **　绑定和处理模块的配置文件
        return $this;
    }

    /**
     * Detach all previously attached listeners
     *
     * @param  EventManagerInterface $events
     * @return void
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $key => $listener) {
            $detached = false;
            if ($listener === $this) {
                continue;
            }
            if ($listener instanceof ListenerAggregateInterface) {
                $detached = $listener->detach($events);
            } elseif ($listener instanceof CallbackHandler) {
                $detached = $events->detach($listener);
            }

            if ($detached) {
                unset($this->listeners[$key]);
            }
        }
    }

    /**
     * Get the config merger.
     *
     * @return ConfigMergerInterface
     */
    public function getConfigListener()
    {
        if (!$this->configListener instanceof ConfigMergerInterface) {
            $this->setConfigListener(new ConfigListener($this->getOptions()));
        }
        return $this->configListener;
    }

    /**
     * Set the config merger to use.
     *
     * @param  ConfigMergerInterface $configListener
     * @return DefaultListenerAggregate
     */
    public function setConfigListener(ConfigMergerInterface $configListener)
    {
        $this->configListener = $configListener;
        return $this;
    }
}
