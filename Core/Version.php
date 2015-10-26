<?php
namespace Kaliop\Migration\Core;

/**
 * Class Version
 *
 * Class to encapsulate a migration definition. This allows for different migration definitions eg.: PHP or Yaml.
 *
 * @package Kaliop\Migration\Core
 */
class Version
{

    protected $configuration;

    /**
     * The migration definition type
     *
     * Possible values:
     * - PHP
     * - Yaml
     * - SQL (Not implemented yet)
     *
     * @var string
     */
    public $type;

    /**
     * The migration definition object
     *
     * @var mixed
     */
    public $migration;

    /**
     * The version string
     *
     * @var string
     */
    public $version;

    /**
     * Short description created from the migration file name.
     *
     * @var string
     */
    public $description;

    /**
     * @param Configuration $config
     * @param string $version
     */
    public function __construct(Configuration $config, $version)
    {
        $this->configuration = $config;

        $this->version = $version;
    }

    /**
     * Helper method to execute the migration definition.
     *
     * @throws \Exception
     */
    public function execute()
    {
        try {

            $this->configuration->output->writeln(
                sprintf('  <info>++</info> migrating <comment>%s</comment>', $this->version)
            );

            $this->migration->execute();

        } catch (\Exception $e) {

            $this->configuration->output->writeln(
                sprintf(
                    '<error>Migration %s failed. Error "%s"</error>',
                    $this->version,
                    $e->getMessage()
                )
            );

            throw $e;
        }
    }
}

?>
