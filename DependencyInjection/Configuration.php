<?php

/*
 * This file is part of the ONGR package.
 *
 * (c) NFQ Technologies UAB <info@nfq.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ONGR\ApiBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Validates and merges configuration from your app/config files.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('ongr_api');

        $rootNode
            ->children()
                ->arrayNode('authorization')
                    ->addDefaultsIfNotSet()
                    ->validate()
                        ->ifTrue(function ($node) {
                            return $node['enabled'] && !isset($node['secret']);
                        })
                        ->thenInvalid("'secret' for api must be set if authorization is enabled.")
                    ->end()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('Set to true if authorization needs to be enabled.')
                        ->end()
                        ->scalarNode('secret')
                            ->info('Secret used for authentication')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('default_encoding')
                    ->defaultValue('json')
                    ->example('json')
                    ->info('Default encoding type. Changed through headers')
                    ->isRequired()
                    ->validate()
                        ->ifNotInArray(['json', 'xml'])
                        ->thenInvalid('Currently valid encoders are only json and xml!')
                    ->end()
                ->end()
                ->arrayNode('versions')
                    ->info('Defines api versions.')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('version')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('version')
                                ->info('Defines a version for current api')
                                ->example('v2')
                            ->end()
                            ->append($this->getEndpointNode())
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    /**
     * Builds configuration tree for endpoints.
     *
     * @return NodeDefinition
     */
    public function getEndpointNode()
    {
        $builder = new TreeBuilder();
        $node = $builder->root('endpoints');

        $node
            ->requiresAtLeastOneElement()
            ->info('Defines version endpoints.')
            ->useAttributeAsKey('endpoint')
            ->prototype('array')
                ->children()
                    ->scalarNode('endpoint')
                        ->info('Endpoint name (will be included in url (excl. default))')
                        ->example('custom')
                    ->end()
                    ->scalarNode('manager')
                        ->isRequired()
                        ->info('Elasticsearch manager which will be used for document fetching')
                        ->example('es.manager.custom')
                    ->end()
                    ->append($this->getDocumentNode())
                ->end()
            ->end();

        return $node;
    }

    /**
     * Builds configuration tree for documents
     *
     * @return NodeDefinition
     */
    public function getDocumentNode()
    {
        $builder = new TreeBuilder();
        $node = $builder->root('documents');

        $node
            ->beforeNormalization()
                ->ifNull()
                ->thenEmptyArray()
            ->end()
            ->prototype('array')
                ->beforeNormalization()
                    ->ifString()
                    ->then(function ($string) {
                        return ['name' => $string];
                    })
                ->end()
                ->children()
                    ->scalarNode('name')
                        ->isRequired()
                        ->info('Document name (AKA Repository name)')
                        ->example('ONGRDemoBundle:Product')
                    ->end()
                    ->scalarNode('controller')
                        ->defaultValue('ONGRApiBundle:Rest')
                        ->info('Front controller for rest actions')
                    ->end()
                    ->arrayNode('methods')
                        ->requiresAtLeastOneElement()
                        ->defaultValue(['POST', 'GET', 'PUT', 'DELETE'])
                        ->prototype('scalar')
                            ->validate()
                                ->ifNotInArray(['POST', 'GET', 'PUT', 'DELETE'])
                                ->thenInvalid(
                                    'Invalid method used! Available methods: POST, GET, PUT, DELETE.'
                                    . 'Please check your ongr_api configuration.'
                                )
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }
}
