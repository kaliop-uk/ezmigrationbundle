<?php


namespace Kaliop\eZMigrationBundle\Core\Executor;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\VarDumper\VarDumper;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\API\ReferenceResolverBagInterface;
use Kaliop\eZMigrationBundle\API\EnumerableReferenceResolverInterface;

class ReferenceExecutor extends AbstractExecutor
{
    use IgnorableStepExecutorTrait;

    protected $supportedStepTypes = array('reference');
    protected $supportedActions = array('set', 'load', 'save', 'dump');

    protected $container;
    /** @var ReferenceResolverBagInterface $referenceResolver */
    protected $referenceResolver;

    public function __construct(ContainerInterface $container, ReferenceResolverBagInterface $referenceResolver)
    {
        $this->container = $container;
        $this->referenceResolver = $referenceResolver;
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
            throw new \Exception("Invalid step definition: missing 'mode'");
        }

        $action = $step->dsl['mode'];

        if (!in_array($action, $this->supportedActions)) {
            throw new \Exception("Invalid step definition: value '$action' is not allowed for 'mode'");
        }

        $this->skipStepIfNeeded($step);

        return $this->$action($step->dsl, $step->context);
    }

    protected function set($dsl, $context)
    {
        if (!isset($dsl['identifier'])) {
            throw new \Exception("Invalid step definition: miss 'identifier' for setting reference");
        }
        if (!isset($dsl['value'])) {
            throw new \Exception("Invalid step definition: miss 'value' for setting reference");
        }
        // this makes sense since we started supporting embedded refs...
        $value = $this->referenceResolver->resolveReference($dsl['value']);
        /// @todo add support for eZ dynamic parameters too
        if (preg_match('/.*%.+%.*$/', $value)) {
            // we use the same parameter resolving rule as symfony, even though this means abusing the ContainerInterface
            $value = $this->container->getParameterBag()->resolveString($value);
        }

        $overwrite = isset($dsl['overwrite']) ? $overwrite = $dsl['overwrite'] : false;
        $this->referenceResolver->addReference($dsl['identifier'], $value, $overwrite);

        return $value;
    }

    protected function load($dsl, $context)
    {
        if (!isset($dsl['file'])) {
            throw new \Exception("Invalid step definition: miss 'file' for loading references");
        }
        $fileName = $this->referenceResolver->resolveReference($dsl['file']);
        $fileName = str_replace('{ENV}', $this->container->get('kernel')->getEnvironment(), $fileName);

        $overwrite = isset($dsl['overwrite']) ? $overwrite = $dsl['overwrite'] : false;

        if (!is_file($fileName) && is_file(dirname($context['path']) . '/references/' . $fileName)) {
            $fileName = dirname($context['path']) . '/references/' . $fileName;
        }

        if (!is_file($fileName)) {
            throw new \Exception("Invalid step definition: invalid file '$fileName' for loading references");
        }
        $data = file_get_contents($fileName);

        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        switch ($ext) {
            case 'json':
                $data = json_decode($data, true);
                break;
            case 'yml':
            case 'yaml':
                $data = Yaml::parse($data);
                break;
            default:
                throw new \Exception("Invalid step definition: unsupported file extension '$ext' for loading references from");
        }

        if (!is_array($data)) {
            throw new \Exception("Invalid step definition: file does not contain an array of key/value pairs");
        }

        foreach ($data as $refName => $value) {
            if (preg_match('/%.+%$/', $value)) {
                $value = $this->container->getParameter(trim($value, '%'));
            }

            if (!$this->referenceResolver->addReference($refName, $value, $overwrite)) {
                throw new \Exception("Failed adding to Reference Resolver the reference: $refName");
            }
        }

        return $data;
    }

    /**
     * @todo find a smart way to allow saving the references file next to the current migration
     */
    protected function save($dsl, $context)
    {
        if (!isset($dsl['file'])) {
            throw new \Exception("Invalid step definition: miss 'file' for saving references");
        }
        $fileName = $this->referenceResolver->resolveReference($dsl['file']);
        $fileName = str_replace('{ENV}', $this->container->get('kernel')->getEnvironment(), $fileName);

        $overwrite = isset($dsl['overwrite']) ? $overwrite = $dsl['overwrite'] : false;

        if (is_file($fileName) && !$overwrite) {
            throw new \Exception("Invalid step definition: file '$fileName' for saving references already exists");
        }

        if (! $this->referenceResolver instanceof EnumerableReferenceResolverInterface) {
            throw new \Exception("Can not save references as resolver is not enumerable");
        }

        $data = $this->referenceResolver->listReferences();

        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        switch ($ext) {
            case 'json':
                $data = json_encode($data, JSON_PRETTY_PRINT);
                break;
            case 'yml':
            case 'yaml':
                $data = Yaml::dump($data);
                break;
            default:
                throw new \Exception("Invalid step definition: unsupported file extension '$ext' for saving references to");
        }

        file_put_contents($fileName, $data);

        return $data;
    }

    protected function dump($dsl, $context)
    {
        if (!isset($dsl['identifier'])) {
            throw new \Exception("Invalid step definition: miss 'identifier' for dumping reference");
        }
        if (!$this->referenceResolver->isReference($dsl['identifier'])) {
            throw new \Exception("Invalid step definition: identifier '{$dsl['identifier']}' is not a reference");
        }
        if (isset($dsl['label'])) {
            echo $dsl['label'];
        } else {
            VarDumper::dump($dsl['identifier']);
        }
        $value = $this->referenceResolver->resolveReference($dsl['identifier']);
        VarDumper::dump($value);

        return $value;
    }
}
