<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\EmbeddedReferenceResolverBagInterface;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\Exception\MigrationBundleException;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;

/**
 * @property EmbeddedReferenceResolverBagInterface $referenceResolver
 */
class FileExecutor extends AbstractExecutor
{
    use IgnorableStepExecutorTrait;
    use ReferenceSetterTrait;
    use NonScalarReferenceSetterTrait;

    protected $supportedStepTypes = array('file');
    protected $supportedActions = array('load', 'load_csv', 'save', 'copy', 'move', 'delete', 'append', 'prepend', 'exists');

    protected $scalarReferences = array('count');

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

        return $action == 'load_csv' ? $this->$action($step) : $this->$action($step->dsl, $step->context);
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
        $fileName = $this->resolveReference($dsl['file']);
        if (!file_exists($fileName)) {
            throw new MigrationBundleException("Can not load '$fileName': file missing");
        }

        $this->setReferences($fileName, $dsl);

        return file_get_contents($fileName);
    }

    /**
     * @param MigrationStep $step
     * @return string[][]
     * @throws InvalidStepDefinitionException
     * @throws \Kaliop\eZMigrationBundle\API\Exception\InvalidMatchResultsNumberException
     */
    protected function load_csv($step)
    {
        if (!isset($step->dsl['expect'])) {
            // for csv files, it makes sense that we expect them to have many rows
            $step = new MigrationStep(
                $step->type,
                array_merge($step->dsl, array('expect' => self::$EXPECT_MANY)),
                $step->context
            );
        }

        $dsl = $step->dsl;

        if (!isset($dsl['file'])) {
            throw new InvalidStepDefinitionException("Can not load file: name missing");
        }
        $fileName = $this->resolveReference($dsl['file']);
        if (!file_exists($fileName)) {
            throw new MigrationBundleException("Can not load '$fileName': file missing");
        }

        $separator = isset($dsl['separator']) ? $this->resolveReference($dsl['separator']) : ',';
        $enclosure = isset($dsl['enclosure']) ? $this->resolveReference($dsl['enclosure']) : '"';
        $escape = isset($dsl['escape']) ? $this->resolveReference($dsl['escape']) : '\\';

        $singleResult = ($this->expectedResultsType($step) == self::$RESULT_TYPE_SINGLE);

        $data = array();
        if (($handle = fopen($fileName, "r")) !== FALSE) {
            while (($row = fgetcsv($handle, 0, $separator, $enclosure, $escape)) !== FALSE) {
                $data[] = $row;
                if ($singleResult && count($data) > 1) {
                    break;
                }
            }
            fclose($handle);
        } else {
            throw new MigrationBundleException("Can not load '$fileName'");
        }

        $this->validateResultsCount($data, $step);
        $this->setDataReferences($data, $dsl, $singleResult);

        // NB: this is one of the very few places where we return a nested array...
        return $data;
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
        $fileName = $this->resolveReference($dsl['file']);

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
                        $this->addReference($reference['identifier'], $exists, $overwrite);
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
            $path = $this->resolveReference($dsl['template']);
            // we use the same logic as for the image/file fields in content: look up file 1st relative to the migration
            $template = dirname($context['path']) . '/templates/' . $path;
            if (!is_file($template)) {
                $template = $path;
            }
            $contents = $this->resolveReferencesInText(file_get_contents($template));
        } else {
            throw new InvalidStepDefinitionException("Can not save file: either body or template tag must be a string");
        }

        $fileName = $this->resolveReference($dsl['file']);

        $overwrite = isset($dsl['overwrite']) ? $this->resolveReference($dsl['overwrite']) : false;
        if (!$overwrite && file_exists($fileName)) {
            throw new MigrationBundleException("Can not save file '$fileName: file already exists");
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
            $path = $this->resolveReference($dsl['template']);
            // we use the same logic as for the image/file fields in content: look up file 1st relative to the migration
            $template = dirname($context['path']) . '/templates/' . $path;
            if (!is_file($template)) {
                $template = $path;
            }
            $contents = $this->resolveReferencesInText(file_get_contents($template));
        } else {
            throw new InvalidStepDefinitionException("Can not append to file: either body or template tag must be a string");
        }

        $fileName = $this->resolveReference($dsl['file']);

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
            $path = $this->resolveReference($dsl['template']);
            // we use the same logic as for the image/file fields in content: look up file 1st relative to the migration
            $template = dirname($context['path']) . '/templates/' . $path;
            if (!is_file($template)) {
                $template = $path;
            }
            $contents = $this->resolveReferencesInText(file_get_contents($template));
        } else {
            throw new InvalidStepDefinitionException("Can not append to file: either body or template tag must be a string");
        }

        $fileName = $this->resolveReference($dsl['file']);

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

        $fileName = $this->resolveReference($dsl['from']);
        if (!file_exists($fileName)) {
            throw new MigrationBundleException("Can not copy file '$fileName': file missing");
        }

        $this->setReferences($fileName, $dsl);

        $to = $this->resolveReference($dsl['to']);
        $overwrite = isset($dsl['overwrite']) ? $this->resolveReference($dsl['overwrite']) : false;
        if (!$overwrite && file_exists($to)) {
            throw new MigrationBundleException("Can not copy file to '$to: file already exists");
        }

        if (!copy($fileName, $to)) {
            throw new MigrationBundleException("Can not copy file '$fileName' to '$to': operation failed");
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

        $fileName = $this->resolveReference($dsl['from']);
        if (!file_exists($fileName)) {
            throw new MigrationBundleException("Can not move file '$fileName': file missing");
        }

        $this->setReferences($fileName, $dsl);

        $to = $this->resolveReference($dsl['to']);
        $overwrite = isset($dsl['overwrite']) ? $this->resolveReference($dsl['overwrite']) : false;
        if (!$overwrite && file_exists($to)) {
            throw new MigrationBundleException("Can not move to '$to': file already exists");
        }

        if (!rename($fileName, $to)) {
            throw new MigrationBundleException("Can not move file '$fileName': operation failed");
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

        $fileName = $this->resolveReference($dsl['file']);
        if (!file_exists($fileName)) {
            throw new MigrationBundleException("Can not move delete '$fileName': file missing");
        }

        $this->setReferences($fileName, $dsl);

        if (!unlink($fileName)) {
            throw new MigrationBundleException("Can not delete file '$fileName': operation failed");
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
        if (!array_key_exists('references', $dsl) || !count($dsl['references'])) {
            return false;
        }

        clearstatcache(true, $fileName);
        $stats = stat($fileName);

        if (!$stats) {
            throw new MigrationBundleException("Can not set references for file '$fileName': stat failed");
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
            $this->addReference($reference['identifier'], $value, $overwrite);
        }

        return true;
    }

    protected function setDataReferences($data, $dsl, $singleResult)
    {
        if (!array_key_exists('references', $dsl) || !count($step->dsl['references'])) {
            return false;
        }

        foreach ($dsl['references'] as $key => $reference) {
            $reference = $this->parseReferenceDefinition($key, $reference);
            switch ($reference['attribute']) {
                case 'count':
                    $value = count($data);
                    break;
                default:
                    if (strpos($reference['attribute'], 'column.') !== 0) {
                        throw new InvalidStepDefinitionException('File Executor does not support setting references for attribute ' . $reference['attribute']);
                    }
                    if (count($data)) {
                        $colNum = substr($reference['attribute'], 7);
                        if (!isset($data[0][$colNum])) {
                            /// @todo use a MigrationBundleException ?
                            throw new \InvalidArgumentException('File Executor does not support setting references for attribute ' . $reference['attribute']);
                        }
                        $value = array_column($data, $colNum);
                        if ($singleResult) {
                            $value = reset($value);
                        }
                    } else {
                        // we should validate the requested column name, but we can't...
                        $value = array();
                    }
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
     * @param string $text
     * @return string
     * @throws \Exception
     */
    protected function resolveReferencesInText($text)
    {
        return $this->referenceResolver->ResolveEmbeddedReferences($text);
    }

    /**
     * @param array $referenceDefinition
     * @return bool
     */
    protected function isScalarReference($referenceDefinition)
    {
        return in_array($referenceDefinition['attribute'], $this->scalarReferences);
    }
}
