<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\EmbeddedReferenceResolverBagInterface;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @property EmbeddedReferenceResolverBagInterface $referenceResolver
 */
class HTTPExecutor extends AbstractExecutor
{
    use IgnorableStepExecutorTrait;
    use ReferenceSetterTrait;

    protected $supportedStepTypes = array('http');
    protected $supportedActions = array('call');

    protected $container;

    /**
     * @param ContainerInterface $container
     * @param EmbeddedReferenceResolverBagInterface $referenceResolver has to implement EmbeddedReferenceResolverInterface as well!
     */
    public function __construct(ContainerInterface $container, EmbeddedReferenceResolverBagInterface $referenceResolver)
    {
        $this->referenceResolver = $referenceResolver;
        $this->container = $container;
    }

    /**
     * @param MigrationStep $step
     * @return mixed
     * @throws \Exception
     */
    public function execute(MigrationStep $step)
    {
        parent::execute($step);

        if (!isset($step->dsl['mode'])) {
            throw new InvalidStepDefinitionException("Invalid step definition: missing 'mode'");
        }

        $action = $step->dsl['mode'];

        if (!in_array($action, $this->supportedActions)) {
            throw new InvalidStepDefinitionException("Invalid step definition: value '$action' is not allowed for 'mode'");
        }

        $this->skipStepIfNeeded($step);

        return $this->$action($step->dsl, $step->context);
    }

    /**
     * @param array $dsl
     * @param array $context
     * @return true
     * @throws \Exception
     */
    protected function call($dsl, $context)
    {
        if (!isset($dsl['uri'])) {
            throw new InvalidStepDefinitionException("Can not execute http call without 'uri' in the step definition");
        }

        $method = isset($dsl['method']) ? $this->resolveReference($dsl['method']) : 'GET';

        $uri = $this->resolveReferencesInText($dsl['uri']);

        $headers = isset($dsl['headers']) ? $this->resolveReferencesInTextRecursively($dsl['headers']) : array();

        $body = isset($dsl['body']) ? $this->resolveReferencesInText($dsl['body']) : null;

        if (isset($dsl['client'])) {
            $client = $this->container->get('httplug.client.'.$this->resolveReference($dsl['client']));
        } else {
            $client = $this->container->get('httplug.client');
        }

        $request = $this->container->get('httplug.message_factory')->createRequest($method, $uri, $headers, $body);

        $response = $client->sendRequest($request);

        $this->setReferences($response, $dsl);

        return $response;
    }

    /**
     * @param ResponseInterface $response
     * @param array $dsl
     * @return bool
     * @throws InvalidStepDefinitionException
     * @todo use jmespath syntax to allow setting refs to response headers
     */
    protected function setReferences(ResponseInterface $response, $dsl)
    {
        if (!array_key_exists('references', $dsl) || !count($dsl['references'])) {
            return false;
        }

        foreach ($dsl['references'] as $key => $reference) {
            $reference = $this->parseReferenceDefinition($key, $reference);
            switch ($reference['attribute']) {
                case 'status_code':
                    $value = $response->getStatusCode();
                    break;
                case 'reason_phrase':
                    $value = $response->getReasonPhrase();
                    break;
                case 'protocol_version':
                    $value = $response->getProtocolVersion();
                    break;
                case 'body':
                    $value = $response->getBody()->__toString();
                    break;
                case 'body_size':
                    $value = $response->getBody()->getSize();
                    break;
                default:
                    throw new InvalidStepDefinitionException('HTTP executor does not support setting references for attribute ' . $reference['attribute']);
            }

            $overwrite = false;
            if (isset($reference['overwrite'])) {
                $overwrite = $reference['overwrite'];
            }
            $this->addReference($reference['identifier'], $value, $overwrite);
        }

        return true;
    }

    /**
     * Replaces any references inside a string
     *
     * @param string
     * @return string
     * @throws \Exception
     */
    protected function resolveReferencesInText($text)
    {
        return $this->referenceResolver->ResolveEmbeddedReferences($text);
    }

    protected function resolveReferencesInTextRecursively($textOrArray)
    {
        if (is_array($textOrArray)) {
            foreach ($textOrArray as $condition => $values) {
                $textOrArray[$condition] = $this->resolveReferencesInTextRecursively($values);
            }
            return $textOrArray;
        } else {
            return $this->resolveReferencesInText($textOrArray);
        }
    }
}
