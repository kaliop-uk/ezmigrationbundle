<?php


namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\API\ReferenceBagInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\VarDumper\VarDumper;

class ReferenceExecutor extends AbstractExecutor
{
    protected $supportedStepTypes = array('reference');
    protected $supportedActions = array('set', 'load', 'dump');

    protected $container;
    /** @var ReferenceBagInterface $referenceResolver */
    protected $referenceResolver;

    public function __construct(ContainerInterface $container, ReferenceBagInterface $referenceResolver)
    {
        $this->container = $container;
        $this->referenceResolver = $referenceResolver;
    }

    /**
     * @param MigrationStep $step
     * @return void
     * @throws \Exception if migration step is not for this type of db
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

        $this->$action($step->dsl, $step->context);
    }

    protected function set($dsl, $context) {
        if (!isset($dsl['identifier'])) {
            throw new \Exception("Invalid step definition: miss 'identifier' for setting reference");
        }
        if (!isset($dsl['value'])) {
            throw new \Exception("Invalid step definition: miss 'value' for setting reference");
        }
        $value = $dsl['value'];
        if (preg_match('/%.+%$/', $value)) {
            $value = $this->container->getParameter(trim($value, '%'));
        }
        $overwrite = isset($dsl['overwrite']) ? $overwrite = $dsl['overwrite'] : false;
        $this->referenceResolver->addReference($dsl['identifier'], $value, $overwrite);

        return true;
    }

    protected function load($dsl, $context)
    {
        if (!isset($dsl['file'])) {
            throw new \Exception("Invalid step definition: miss 'file' for loading references");
        }
        $fileName = $dsl['file'];

        $overwrite = isset($dsl['overwrite']) ? $overwrite = $dsl['overwrite'] : false;

        $fileName = str_replace('{ENV}', $this->container->get('kernel')->getEnvironment(), $fileName);

        if (!is_file($fileName) && is_file($context['path'] . '/references/' . $fileName)) {
            $fileName = $context['path'] . '/references/' . $fileName;
        }

        if (!is_file($fileName)) {
            throw new \Exception("Invalid step definition: invalid 'file' for loading references");
        }
        $data = file_get_contents($fileName);

        $ext = pathinfo($dsl['file'], PATHINFO_EXTENSION);
        switch ($ext) {
            case 'json':
                $data = json_decode($data, true);
                break;
            case 'yml':
            case 'yaml':
                $data = Yaml::parse($data);
                break;
            default:
                throw new \Exception("Invalid step definition: unsupported file extension for loading references from");
        }

        if (!is_array($data)) {
            throw new \Exception("Invalid step definition: file does not contain an array of key/value pairs");
        }

        foreach ($data as $refName => $value) {
            if (preg_match('/%.+%$/', $value)) {
                $value = $this->container->getParameter(trim($value, '%'));
            }
            $this->referenceResolver->addReference($refName, $value, $overwrite);
        }

        return $data;
    }

    protected function dump($dsl, $context) {
        if (!isset($dsl['identifier'])) {
            throw new \Exception("Invalid step definition: miss 'identifier' for dumping reference");
        }
        if (!$this->referenceResolver->isReference($dsl['identifier'])) {
            throw new \Exception("Invalid step definition: identifier '' is not a reference");
        }
        VarDumper::dump($dsl['identifier']);
        $value = $this->referenceResolver->resolveReference($dsl['identifier']);
        VarDumper::dump($value);

        return $value;
    }
}
