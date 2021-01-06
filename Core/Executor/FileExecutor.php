<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\API\EmbeddedReferenceResolverBagInterface;

class FileExecutor extends AbstractExecutor
{
    use IgnorableStepExecutorTrait;

    protected $supportedStepTypes = array('file');
    protected $supportedActions = array('load', 'save', 'copy', 'move', 'delete', 'append', 'prepend', 'exists');

    /** @var EmbeddedReferenceResolverBagInterface $referenceResolver */
    protected $referenceResolver;

    /**
     * @param EmbeddedReferenceResolverBagInterface $referenceResolver
     */
    public function __construct(EmbeddedReferenceResolverBagInterface $referenceResolver)
    {
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

    /**
     * @param array $dsl
     * @param array $context
     * @return string
     * @throws \Exception
     */
    protected function load($dsl, $context)
    {
        if (!isset($dsl['file'])) {
            throw new InvalidStepDefinitionException("Can not load file: name missing");
        }
        $fileName = $this->referenceResolver->resolveReference($dsl['file']);
        if (!file_exists($fileName)) {
            throw new \Exception("Can not load '$fileName': file missing");
        }

        $this->setReferences($fileName, $dsl);

        return file_get_contents($fileName);
    }

    /**
     * @param array $dsl
     * @param array $context
     * @return bool
     * @throws \Exception
     */
    protected function exists($dsl, $context)
    {
        if (!isset($dsl['file'])) {
            throw new InvalidStepDefinitionException("Can not check for existence of file: name missing");
        }
        $fileName = $this->referenceResolver->resolveReference($dsl['file']);

        $exists = file_exists($fileName);

        if (array_key_exists('references', $dsl)) {
            foreach ($dsl['references'] as $key => $reference) {
                $reference = $this->parseReferenceDefinition($key, $reference);
                switch ($reference['attribute']) {
                    case 'exists':
                        $overwrite = false;
                        if (isset($reference['overwrite'])) {
                            $overwrite = $reference['overwrite'];
                        }
                        $this->referenceResolver->addReference($reference['identifier'], $exists, $overwrite);
                        break;
                }
            }
        }

        return $exists;
    }

    /**
     * @param array $dsl
     * @param array $context
     * @return int
     * @throws \Exception
     */
    protected function save($dsl, $context)
    {
        if (!isset($dsl['file']) || (!isset($dsl['body']) && !isset($dsl['template']))) {
            throw new InvalidStepDefinitionException("Can not save file: name or body or template missing");
        }

        if (isset($dsl['body']) && is_string($dsl['body'])) {
            $contents = $this->resolveReferencesInText($dsl['body']);
        } elseif (isset($dsl['template']) && is_string($dsl['template'])) {
            $path = $this->referenceResolver->resolveReference($dsl['template']);
            // we use the same logic as for the image/file fields in content: look up file 1st relative to the migration
            $template = dirname($context['path']) . '/templates/' . $path;
            if (!is_file($template)) {
                $template = $path;
            }
            $contents = $this->resolveReferencesInText(file_get_contents($template));
        } else {
            throw new InvalidStepDefinitionException("Can not save file: either body or template tag must be a string");
        }

        $fileName = $this->referenceResolver->resolveReference($dsl['file']);

        $overwrite = isset($dsl['overwrite']) ? $overwrite = $dsl['overwrite'] : false;
        if (!$overwrite && file_exists($fileName)) {
            throw new \Exception("Can not save file '$fileName: file already exists");
        }

        $return = file_put_contents($fileName, $contents);

        $this->setReferences($fileName, $dsl);

        return $return;
    }

    /**
     * @param array $dsl
     * @param array $context
     * @return int
     * @throws \Exception
     */
    protected function append($dsl, $context)
    {
        if (!isset($dsl['file']) || (!isset($dsl['body']) && !isset($dsl['template']))) {
            throw new InvalidStepDefinitionException("Can not append to file: name or body or template missing");
        }

        if (isset($dsl['body']) && is_string($dsl['body'])) {
            $contents = $this->resolveReferencesInText($dsl['body']);
        } elseif (isset($dsl['template']) && is_string($dsl['template'])) {
            $path = $this->referenceResolver->resolveReference($dsl['template']);
            // we use the same logic as for the image/file fields in content: look up file 1st relative to the migration
            $template = dirname($context['path']) . '/templates/' . $path;
            if (!is_file($template)) {
                $template = $path;
            }
            $contents = $this->resolveReferencesInText(file_get_contents($template));
        } else {
            throw new InvalidStepDefinitionException("Can not append to file: either body or template tag must be a string");
        }

        $fileName = $this->referenceResolver->resolveReference($dsl['file']);

        $return = file_put_contents($fileName, $contents, FILE_APPEND);

        $this->setReferences($fileName, $dsl);

        return $return;
    }

    /**
     * @param array $dsl
     * @param array $context
     * @return int
     * @throws \Exception
     */
    protected function prepend($dsl, $context)
    {
        if (!isset($dsl['file']) || (!isset($dsl['body']) && !isset($dsl['template']))) {
            throw new InvalidStepDefinitionException("Can not prepend to file: name or body or template missing");
        }

        if (isset($dsl['body']) && is_string($dsl['body'])) {
            $contents = $this->resolveReferencesInText($dsl['body']);
        } elseif (isset($dsl['template']) && is_string($dsl['template'])) {
            $path = $this->referenceResolver->resolveReference($dsl['template']);
            // we use the same logic as for the image/file fields in content: look up file 1st relative to the migration
            $template = dirname($context['path']) . '/templates/' . $path;
            if (!is_file($template)) {
                $template = $path;
            }
            $contents = $this->resolveReferencesInText(file_get_contents($template));
        } else {
            throw new InvalidStepDefinitionException("Can not append to file: either body or template tag must be a string");
        }

        $fileName = $this->referenceResolver->resolveReference($dsl['file']);

        if (file_exists($fileName)) {
            $contents .= file_get_contents($fileName);
        }

        $return = file_put_contents($fileName, $contents);

        $this->setReferences($fileName, $dsl);

        return $return;
    }

    /**
     * @param array $dsl
     * @param array $context
     * @return true
     * @throws \Exception
     */
    protected function copy($dsl, $context)
    {
        if (!isset($dsl['from']) || !isset($dsl['to'])) {
            throw new InvalidStepDefinitionException("Can not copy file: from or to missing");
        }

        $fileName = $this->referenceResolver->resolveReference($dsl['from']);
        if (!file_exists($fileName)) {
            throw new \Exception("Can not copy file '$fileName': file missing");
        }

        $this->setReferences($fileName, $dsl);

        $to = $this->referenceResolver->resolveReference($dsl['to']);
        $overwrite = isset($dsl['overwrite']) ? $overwrite = $dsl['overwrite'] : false;
        if (!$overwrite && file_exists($to)) {
            throw new \Exception("Can not copy file to '$to: file already exists");
        }

        if (!copy($fileName, $to)) {
            throw new \Exception("Can not copy file '$fileName' to '$to': operation failed");
        }

        return true;
    }

    /**
     * @param array $dsl
     * @param array $context
     * @return true
     * @throws \Exception
     */
    protected function move($dsl, $context)
    {
        if (!isset($dsl['from']) || !isset($dsl['to'])) {
            throw new InvalidStepDefinitionException("Can not move file: from or to missing");
        }

        $fileName = $this->referenceResolver->resolveReference($dsl['from']);
        if (!file_exists($fileName)) {
            throw new \Exception("Can not move file '$fileName': file missing");
        }

        $this->setReferences($fileName, $dsl);

        $to = $this->referenceResolver->resolveReference($dsl['to']);
        $overwrite = isset($dsl['overwrite']) ? $overwrite = $dsl['overwrite'] : false;
        if (!$overwrite && file_exists($to)) {
            throw new \Exception("Can not move to '$to': file already exists");
        }

        if (!rename($fileName, $to)) {
            throw new \Exception("Can not move file '$fileName': operation failed");
        }

        return true;
    }

    /**
     * @param array $dsl
     * @param array $context
     * @return true
     * @throws \Exception
     */
    protected function delete($dsl, $context)
    {
        if (!isset($dsl['file'])) {
            throw new InvalidStepDefinitionException("Can not delete file: name missing");
        }

        $fileName = $this->referenceResolver->resolveReference($dsl['file']);
        if (!file_exists($fileName)) {
            throw new \Exception("Can not move delete '$fileName': file missing");
        }

        $this->setReferences($fileName, $dsl);

        if (!unlink($fileName)) {
            throw new \Exception("Can not delete file '$fileName': operation failed");
        }

        return true;
    }

    /**
     * @param $fileName
     * @param $dsl
     * @return bool
     * @throws InvalidStepDefinitionException
     */
    protected function setReferences($fileName, $dsl)
    {
        if (!array_key_exists('references', $dsl)) {
            return false;
        }

        clearstatcache(true, $fileName);
        $stats = stat($fileName);

        if (!$stats) {
            throw new \Exception("Can not set references for file '$fileName': stat failed");
        }

        foreach ($dsl['references'] as $key => $reference) {
            $reference = $this->parseReferenceDefinition($key, $reference);
            switch ($reference['attribute']) {
                case 'body':
                    $value = file_get_contents($fileName);
                    break;
                case 'size':
                    $value = $stats[7];
                    break;
                case 'uid':
                    $value = $stats[4];
                    break;
                case 'gid':
                    $value = $stats[5];
                    break;
                case 'atime':
                    $value = $stats[8];
                    break;
                case 'mtime':
                    $value = $stats[9];
                    break;
                case 'ctime':
                    $value = $stats[10];
                    break;
                default:
                    throw new InvalidStepDefinitionException('File executor does not support setting references for attribute ' . $reference['attribute']);
            }

            $overwrite = false;
            if (isset($reference['overwrite'])) {
                $overwrite = $reference['overwrite'];
            }
            $this->referenceResolver->addReference($reference['identifier'], $value, $overwrite);
        }

        return true;
    }

    /**
     * Replaces any references inside a string
     *
     * @param string $text
     * @return string
     * @throws \Exception
     */
    protected function resolveReferencesInText($text)
    {
        return $this->referenceResolver->ResolveEmbeddedReferences($text);
    }
}
