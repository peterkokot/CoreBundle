<?php

namespace Symfony\Cmf\Bundle\CoreBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

class CmfCoreExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Prepend persistence, multilang and other common configuration to all cmf
     * bundles.
     *
     * {@inheritDoc}
     */
    public function prepend(ContainerBuilder $container)
    {
        // process the configuration of SymfonyCmfCoreExtension
        $configs = $container->getExtensionConfig($this->getAlias());
        $parameterBag = $container->getParameterBag();
        $configs = $parameterBag->resolveValue($configs);
        $config = $this->processConfiguration(new Configuration(), $configs);

        $extensions = $container->getExtensions();
        if (isset($config['multilang']['locales'])){
            $prependConfig = array('multilang' => $config['multilang']);
            if (isset($extensions['cmf_routing'])) {
                $container->prependExtensionConfig('cmf_routing', array('dynamic' => $prependConfig['multilang']));
            }
            if (isset($extensions['cmf_simple_cms'])) {
                $container->prependExtensionConfig('cmf_simple_cms', $prependConfig);
            }
        }

        if ($config['persistence']['phpcr']) {
            $bundles = $container->getParameter('kernel.bundles');
            $persistenceConfig = $config['persistence']['phpcr'];

            foreach ($container->getExtensions() as $name => $extension) {
                $prependConfig = array();

                switch ($name) {
                    case 'cmf_block':
                        $prependConfig = array(
                            'persistence' => array(
                                'phpcr' => array(
                                    'enabled' => $persistenceConfig['enabled'],
                                    'use_sonata_admin' => $persistenceConfig['use_sonata_admin'],
                                    'block_basepath' => $persistenceConfig['basepath'].'/content',
                                    'manager_name' => $persistenceConfig['manager_name'],
                                )
                            )
                        );
                        break;
                    case 'cmf_content':
                        $prependConfig = array(
                            'persistence' => array(
                                'phpcr' => array(
                                    'enabled' => $persistenceConfig['enabled'],
                                    'use_sonata_admin' => $persistenceConfig['use_sonata_admin'],
                                    'content_basepath' => $persistenceConfig['basepath'].'/content',
                                )
                            )
                        );
                        break;
                    case 'cmf_create':
                        $prependConfig = array(
                            'persistence' => array(
                                'phpcr' => array(
                                    'enabled' => $persistenceConfig['enabled'],
                                    'image' => array(
                                        'basepath' => $persistenceConfig['basepath'].'/media',
                                    )
                                )
                            )
                        );
                        break;
                    case 'cmf_media':
                        $prependConfig = array(
                            'persistence' => array(
                                'phpcr' => array(
                                    'enabled' => $persistenceConfig['enabled'],
                                    'media_basepath' => $persistenceConfig['basepath'].'/media',
                                    'manager_name' => $persistenceConfig['manager_name'],
                                )
                            )
                        );
                        break;
                    case 'cmf_menu':
                        $prependConfig = array(
                            'persistence' => array(
                                'phpcr' => array(
                                    'enabled' => $persistenceConfig['enabled'],
                                    'use_sonata_admin' => $persistenceConfig['use_sonata_admin'],
                                    'content_basepath' => $persistenceConfig['basepath'].'/content',
                                    'menu_basepath' => $persistenceConfig['basepath'].'/menu',
                                    'manager_name' => $persistenceConfig['manager_name'],
                                )
                            )
                        );
                        break;
                    case 'cmf_routing':
                        $prependConfig = array(
                            'dynamic' => array(
                                'enabled' => true,
                                'persistence' => array(
                                    'phpcr' => array(
                                        'enabled' => $persistenceConfig['enabled'],
                                        'use_sonata_admin' => $persistenceConfig['use_sonata_admin'],
                                        'content_basepath' => $persistenceConfig['basepath'].'/content',
                                        'route_basepath' => $persistenceConfig['basepath'].'/routes',
                                        'manager_name' => $persistenceConfig['manager_name'],
                                    )
                                )
                            )
                        );

                        if (isset($bundles['CmfContentBundle'])) {
                            $prependConfig['dynamic']['generic_controller'] = 'cmf_content.controller:indexAction';
                        }
                        break;
                    case 'cmf_search':
                        $prependConfig = array(
                            'persistence' => array(
                                'phpcr' => array(
                                    'enabled' => $persistenceConfig['enabled'],
                                    'search_basepath' => $persistenceConfig['basepath'].'/content',
                                    'manager_name' => $persistenceConfig['manager_name'],
                                )
                            )
                        );
                        break;
                    case 'cmf_simple_cms':
                        $prependConfig = array(
                            'persistence' => array(
                                'phpcr' => array(
                                    'enabled' => $persistenceConfig['enabled'],
                                    'use_sonata_admin' => $persistenceConfig['use_sonata_admin'],
                                    'basepath' => $persistenceConfig['basepath'].'/simple',
                                    'manager_name' => $persistenceConfig['manager_name'],
                                )
                            )
                        );
                        break;
                    case 'cmf_tree_browser':
                        $prependConfig = array(
                            'persistence' => array(
                                'phpcr' => array(
                                    'enabled' => $persistenceConfig['enabled'],
                                )
                            )
                        );
                        break;
                }

                if ($prependConfig) {
                    $container->prependExtensionConfig($name, $prependConfig);
                }
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        if ($config['persistence']['phpcr']['enabled']) {
            $container->setParameter($this->getAlias() . '.persistence.phpcr.manager_name', $config['persistence']['phpcr']['manager_name']);
            $container->setParameter($this->getAlias() . '.persistence.phpcr.basepath', $config['persistence']['phpcr']['basepath']);
        }
        if ($config['publish_workflow']['enabled']) {
            $checker = $this->loadPublishWorkflow($config['publish_workflow'], $loader, $container);
        } else {
            $loader->load('no-publish-workflow.xml');
            $checker = 'cmf_core.publish_workflow.checker.always';
        }
        $container->setAlias('cmf_core.publish_workflow.checker', $checker);

        if (isset($config['multilang'])) {
            $container->setParameter($this->getAlias() . '.multilang.locales', $config['multilang']['locales']);
            $loader->load('translatable.xml');
        } else {
            $container->setParameter($this->getAlias() . '.multilang.locales', array());
            $loader->load('translatable-disabled.xml');
        }

        $this->setupFormTypes($container, $loader);
    }

    /**
     * Setup the cmf_core_checkbox_url_label form type if the routing bundle is there
     *
     * @param ContainerBuilder $container
     * @param LoaderInterface  $loader
     */
    public function setupFormTypes(ContainerBuilder $container, LoaderInterface $loader)
    {
        $bundles = $container->getParameter('kernel.bundles');
        if (isset($bundles['CmfRoutingBundle'])) {
            $loader->load('form-type.xml');

            // if there is twig, register our form type with twig
            if ($container->hasParameter('twig.form.resources')) {
                $resources = $container->getParameter('twig.form.resources');
                $container->setParameter('twig.form.resources', array_merge($resources, array('CmfCoreBundle:Form:checkbox_url_label_form_type.html.twig')));
            }
        }
    }

    /**
     * Load and configure the publish workflow services.
     *
     * @param $config
     * @param XmlFileLoader    $loader
     * @param ContainerBuilder $container
     *
     * @return string the name of the workflow checker service to alias
     *
     * @throws InvalidConfigurationException
     */
    private function loadPublishWorkflow($config, XmlFileLoader $loader, ContainerBuilder $container)
    {
        $container->setParameter($this->getAlias().'.publish_workflow.view_non_published_role', $config['view_non_published_role']);
        $loader->load('publish-workflow.xml');

        if (!$config['request_listener']) {
            $container->removeDefinition($this->getAlias() . '.publish_workflow.request_listener');
        } elseif (!class_exists('Symfony\Cmf\Bundle\RoutingBundle\Routing\DynamicRouter')) {
            throw new InvalidConfigurationException('The "publish_workflow.request_listener" may not be enabled unless "Symfony\Cmf\Bundle\RoutingBundle\Routing\DynamicRouter" is available.');
        }

        if (!class_exists('Sonata\AdminBundle\Admin\AdminExtension')) {
            $container->removeDefinition($this->getAlias() . '.admin_extension.publish_workflow.publishable');
            $container->removeDefinition($this->getAlias() . '.admin_extension.publish_workflow.time_period');
        }

        return $config['checker_service'];
    }

    /**
     * {@inheritDoc}
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/schema';
    }

    /**
     * {@inheritDoc}
     */
    public function getNamespace()
    {
        return 'http://cmf.symfony.com/schema/dic/core';
    }
}
