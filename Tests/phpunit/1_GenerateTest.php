<?php

include_once(__DIR__.'/CommandExecutingTest.php');

/**
 * Tests the `generate` command
 */
class GenerateTest extends CommandExecutingTest
{
    protected $targetBundle = 'EzPublishCoreBundle'; // it is always present :-)

    /**
     * Tests the kaliop:migration:generate command
     *
     * @dataProvider provideGenerateParameters
     *
     * @param string|null $name
     * @param string|null $format
     * @param string|null $type
     * @param string|null $matchType
     * @param string|null $matchValue
     * @param bool $matchExcept
     * @param string|null $mode
     */
    public function testGenerateDSL(
        $name = null,
        $format = null,
        $type = null,
        $matchType = null,
        $matchValue = null,
        $matchExcept = false,
        $mode = null
    ) {
        $parameters = array(
            'bundle' => $this->targetBundle
        );

        if ($name) {
            $parameters['name'] = $name;
        }

        if ($format) {
            $parameters['--format'] = $format;
        }

        if ($type) {
            $parameters['--type'] = $type;
        }

        if ($matchType) {
            $parameters['--match-type'] = $matchType;
        }

        if ($matchValue) {
            $parameters['--match-value'] = $matchValue;
        }

        if ($matchExcept) {
            $parameters['--match-except'] = null;
        }

        if ($mode) {
            $parameters['--mode'] = $mode;
        }

        $output = $this->runCommand('kaliop:migration:generate', $parameters);
        $this->checkGeneratedFile($this->saveGeneratedFile($output), $mode);
    }

    public function provideGenerateParameters()
    {
        $out = array(
            array(),
            array(null, 'sql'),
            array(null, 'php'),
            array('unit_test_generated'),
            array('unit_test_generated', 'sql'),
            array('unit_test_generated', 'php'),
            array('unit_test_generated_content', null, 'content', 'contenttype_identifier', 'user_group'),
            array('unit_test_generated_content', 'yml', 'content', 'contenttype_identifier', 'user_group', false, 'update'),
            array('unit_test_generated_content', 'json', 'content', 'contenttype_identifier', 'user_group', false, 'delete'),
            array('unit_test_generated_content_type', null, 'content_type', 'identifier', 'folder'),
            array('unit_test_generated_content_type', 'yml', 'content_type', 'identifier', 'folder', false, 'update'),
            array('unit_test_generated_content_type', 'json', 'content_type', 'identifier', 'folder', false, 'delete'),
            array('unit_test_generated_content_type', 'yml', 'content_type', 'identifier', 'folder,user', true, 'delete'),
            array('unit_test_generated_content_type', 'json', 'content_type', 'all', null, false, 'create'),
            array('unit_test_generated_content_type_group', null, 'content_type_group', 'contenttypegroup_identifier', 'Content'),
            array('unit_test_generated_content_type_group', 'yml', 'content_type_group', 'contenttypegroup_identifier', 'Content', false, 'update'),
            array('unit_test_generated_content_type_group', 'json', 'content_type_group', 'contenttypegroup_identifier', 'Content', false, 'delete'),
            array('unit_test_generated_content', null, 'language', 'all', null),
            array('unit_test_generated_content', 'yml', 'language', 'all', null, false, 'update'),
            array('unit_test_generated_content', 'json', 'language', 'all', null, false, 'delete'),
            array('unit_test_generated_object_state', null, 'object_state', 'all', null),
            array('unit_test_generated_object_state', 'yml', 'object_state', 'all', null, false, 'update'),
            array('unit_test_generated_object_state', 'json', 'object_state', 'all', null, false, 'delete'),
            array('unit_test_generated_object_state_group', null, 'object_state_group', 'objectstategroup_identifier', 'ez_lock'),
            array('unit_test_generated_object_state_group', 'yml', 'object_state_group', 'objectstategroup_identifier', 'ez_lock', false, 'update'),
            array('unit_test_generated_object_state_group', 'json', 'object_state_group', 'objectstategroup_identifier', 'ez_lock', false, 'delete'),
            array('unit_test_generated_role', null, 'role', 'identifier', 'Anonymous'),
            array('unit_test_generated_role', 'yml', 'role', 'identifier', 'Anonymous', false, 'update'),
            array('unit_test_generated_role', 'json', 'role', 'identifier', 'Anonymous', false, 'delete'),
            array('unit_test_generated_role', 'yml', 'role', 'identifier', 'Anonymous,Administrator', true, 'delete'),
            array('unit_test_generated_role', 'json', 'role', 'all', null, false, 'create'),
            array('unit_test_generated_section', null, 'section', 'section_identifier', 'standard'),
            array('unit_test_generated_section', 'yml', 'section', 'section_identifier', 'standard', false, 'update'),
            array('unit_test_generated_section', 'json', 'section', 'section_identifier', 'standard', false, 'delete'),
            array('unit_test_generated_content', null, 'language', 'all', null),
            array('unit_test_generated_content', 'yml', 'language', 'all', null, false, 'update'),
            array('unit_test_generated_content', 'json', 'language', 'all', null, false, 'delete'),
        );

        /// @todo we should create some tags before running these...
        // try to make this work across phpunit versions, which run this before/after calling setUp()
        $container = $this->getContainer() == null ? $this->bootContainer() : $this->getContainer();
        $bundles = $container->getParameter('kernel.bundles');
        if (isset($bundles['NetgenTagsBundle'])) {
            $out[] = array('unit_test_generated_tags', null, 'tag', 'all', null);
            $out[] = array('unit_test_generated_tags', 'yml', 'tag', 'all', null, false, 'update');
            $out[] = array('unit_test_generated_tags', 'json', 'tag', 'all', null, false, 'delete');
        }

        return $out;
    }

    protected function saveGeneratedFile($output)
    {
        if (preg_match('/Generated new migration file: +(.*)/', $output, $matches)) {
            $this->leftovers[] = $matches[1];
            return $matches[1];
        }

        return null;
    }

    protected function checkGeneratedFile($filePath, $mode)
    {
        // Check that the generated file can be parsed as valid Migration Definition
        $output = $this->runCommand('kaliop:migration:status');
        $this->assertRegExp('?\| ' . basename($filePath) . ' +\| not executed +\|?', $output);

        // We should really test generated migrations by executing them, but for the moment we have a few problems:
        // 1. we should patch them after generation, eg. replacing 'folder' w. something else (to be able to create and delete the content-type)
        // 2. generated migration for 'anon' user has a limitation with borked siteaccess
        // 3. generated migration for 'folder' contenttype has a borked field-settings definition
        if (false) {
            $output = $this->runCommand('kaliop:migration:migrate', array('--path' => array($filePath), '-n' => null));
            $this->assertRegexp('?\| ' . basename($filePath) . ' +\| +\|?', $output);
        }
    }
}
