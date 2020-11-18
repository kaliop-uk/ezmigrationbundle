<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\EnumerableReferenceResolverInterface;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\ReferenceResolverBagInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\Yaml\Yaml;

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
            throw new InvalidStepDefinitionException("Invalid step definition: missing 'mode'");
        }

        $action = $step->dsl['mode'];

        if (!in_array($action, $this->supportedActions)) {
            throw new InvalidStepDefinitionException("Invalid step definition: value '$action' is not allowed for 'mode'");
        }

        $this->skipStepIfNeeded($step);

        return $this->$action($step->dsl, $step->context);
    }

    protected function set($dsl, $context)
    {
        if (!isset($dsl['identifier'])) {
            throw new InvalidStepDefinitionException("Invalid step definition: miss 'identifier' for setting reference");
        }
        if (!isset($dsl['value'])) {
            throw new InvalidStepDefinitionException("Invalid step definition: miss 'value' for setting reference");
        }

        if (!isset($dsl['resolve_references']) || $dsl['resolve_references']) {
            // this makes sense since we started supporting embedded refs...
            $value = $this->referenceResolver->resolveReference($dsl['value']);
        } else {
            $value = $dsl['value'];
        }

        if (is_string($value)) {
            if (0 === strpos($value, '%env(') && ')%' === substr($value, -2) && '%env()%' !== $value) {
                /// @todo find out how to use Sf components to resolve this value instead of doing it by ourselves...
                $env = substr($value, 5, -2);
                // we use getenv because $_ENV gets cleaned up (by whom?)
                $val = getenv($env);
                if ($val === false) {
                    throw new \Exception("Env var $env seems not to be defined");
                }
                $value = $val;
            } else {
                /// @todo add support for eZ dynamic parameters too

                if (preg_match('/.*%.+%.*$/', $value)) {
                    // we use the same parameter resolving rule as symfony, even though this means abusing the ContainerInterface
                    $value = $this->container->getParameterBag()->resolveString($value);
                }
            }
        }

        $overwrite = isset($dsl['overwrite']) ? $overwrite = $dsl['overwrite'] : false;
        $this->referenceResolver->addReference($dsl['identifier'], $value, $overwrite);

        return $value;
    }

    protected function load($dsl, $context)
    {
        if (!isset($dsl['file'])) {
            throw new InvalidStepDefinitionException("Invalid step definition: miss 'file' for loading references");
        }
        $fileName = $this->referenceResolver->resolveReference($dsl['file']);
        $fileName = str_replace('{ENV}', $this->container->get('kernel')->getEnvironment(), $fileName);

        $overwrite = isset($dsl['overwrite']) ? $overwrite = $dsl['overwrite'] : false;

        if (!is_file($fileName) && is_file(dirname($context['path']) . '/references/' . $fileName)) {
            $fileName = dirname($context['path']) . '/references/' . $fileName;
        }

        if (!is_file($fileName)) {
            throw new InvalidStepDefinitionException("Invalid step definition: invalid file '$fileName' for loading references");
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
                throw new InvalidStepDefinitionException("Invalid step definition: unsupported file extension '$ext' for loading references from");
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
            throw new InvalidStepDefinitionException("Invalid step definition: miss 'file' for saving references");
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
            /// @todo use Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE option if it is supported
                $data = Yaml::dump($data);
                break;
            default:
                throw new InvalidStepDefinitionException("Invalid step definition: unsupported file extension '$ext' for saving references to");
        }

        file_put_contents($fileName, $data);

        return $data;
    }

    protected function dump($dsl, $context)
    {
        if (!isset($dsl['identifier'])) {
            throw new InvalidStepDefinitionException("Invalid step definition: miss 'identifier' for dumping reference");
        }
        if (!$this->referenceResolver->isReference($dsl['identifier'])) {
            throw new \Exception("Invalid step definition: identifier '{$dsl['identifier']}' is not a reference");
        }
        if (isset($context['output']) && $context['output'] instanceof OutputInterface && $context['output']->isQuiet()) {
            return $this->referenceResolver->resolveReference($dsl['identifier']);
        }

        if (isset($dsl['label'])) {
            $label = $dsl['label'];
        } else {
            $label = $this->dumpVar($dsl['identifier']);
        }
        $value = $this->dumpVar($this->referenceResolver->resolveReference($dsl['identifier']));

        if (isset($context['output']) && $context['output'] instanceof OutputInterface) {
            $context['output']->write($label . $value, false, OutputInterface::OUTPUT_RAW|OutputInterface::VERBOSITY_NORMAL);
        } else {
            echo $label . $value;
        }

        return $value;
    }

    /**
     * Similar to VarDumper::dump(), but returns the output
     * @param mixed $var
     * @return string
     * @throws \ErrorException
     */
    protected function dumpVar($var)
    {
        $cloner = new VarCloner();
        $dumper = \in_array(\PHP_SAPI, ['cli', 'phpdbg'], true) ? new CliDumper() : new HtmlDumper();
        $output = '';

        $dumper->dump(
            $cloner->cloneVar($var),
            function ($line, $depth) use (&$output) {
                // A negative depth means "end of dump"
                if ($depth >= 0) {
                    // Adds a two spaces indentation to the line
                    /// @todo should we use NBSP for html dumping?
                    $output .= str_repeat('  ', $depth) . $line . "\n";
                }
            }
        );

        return $output;
    }
}
