<?php

namespace Kaliop\eZMigrationBundle\Tests\behat;

use eZ\Publish\Core\Repository\Values\ContentType\ContentType;
use eZ\Publish\Core\Repository\Values\ContentType\ContentTypeGroup;
use eZ\Publish\Core\Repository\Values\ContentType\FieldDefinition;
use eZ\Publish\SPI\Persistence\Content\Location;
use Symfony\Component\HttpKernel\KernelInterface;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Exception\UndefinedException;

use PHPUnit_Framework_Assert as Assertion;
use DateTime;

/**
 * Feature context.
 */
class FeatureContext /*extends BehatContext*/ implements KernelAwareContext
{
    private $kernel;

    const DEFAULT_LANGUAGE_CODE = 'eng-GB';

    /**
     * The parsed DSL instructions
     *
     * @var array
     */
    private $dsl = array();

    /**
     * Variable to store the step results in if needed.
     *
     * @var mixed
     */
    private $stepResults;

    /**
     * Sets HttpKernel instance.
     * This method will be automatically called by Symfony2Extension ContextInitializer.
     *
     * @param KernelInterface $kernel
     */
    public function setKernel(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @Given /^I have the parsed DSL:$/
     */
    public function iHaveTheParsedDsl( TableNode $table )
    {
        $hash = $table->getHash();
        $this->dsl = $hash[0];
    }

    /**
     * @Given /^the attributes:$/
     */
    public function theAttributes( TableNode $table )
    {
        $this->dsl['attributes'] = $table->getHash();
    }

    /**
     * @When /^I create a "([^"]*)"$/
     */
    public function iCreateA( $className )
    {
        $this->stepResults = $this->createObject( $className );

    }

    /**
     * @Then /^I should get an object of "([^"]*)"$/
     */
    public function iShouldGetAnObjectOf( $className )
    {
        switch( $className ) {
            case 'ContentCreateStruct':
                Assertion::assertInstanceOf( 'eZ\Publish\Core\Repository\Values\Content\ContentCreateStruct', $this->stepResults );
                break;
            default:
                throw new PendingException( 'Handling for class name: ' . $className . ' not implemented.' );
        }

    }

    /**
     * @Given /^it should have (\d+) fields of type "([^"]*)"$/
     */
    public function itShouldHaveFieldsOfType( $numberOfFields, $className )
    {
        switch( $className )
        {
            case 'Field':
                $fieldType = 'eZ\Publish\API\Repository\Values\Content\Field';
                break;
            default:
                throw new PendingException( 'Handling for class ' . $className . ' not implemented' );
        }

        $fieldCount = count( $this->stepResults->fields );

        Assertion::assertEquals( $numberOfFields, $fieldCount );
        foreach( $this->stepResults->fields as $field )
        {
            Assertion::assertInstanceOf( $fieldType, $field );
        }
    }

    /**
     * @Given /^the fields should have the values:$/
     */
    public function theFieldsShouldHaveTheValues(TableNode $table )
    {
        throw new PendingException();
    }

    private function createObject( $className )
    {
        $object = null;

        switch( $className )
        {
            case 'ContentCreateStruct':
                /** @var $repository \eZ\Publish\API\Repository\Repository */
                $repository = $this->kernel->getContainer()->get( 'ezpublish.api.repository' );

                $contentService = $repository->getContentService();

                $contentType = $this->createArticleContentType();

                $object = $contentService->newContentCreateStruct( $contentType, self::DEFAULT_LANGUAGE_CODE );

                foreach( $this->dsl['attributes'][0] as $field => $value )
                {
                    $object->setField( $field, $value );
                }

                break;
            default:
                throw new UndefinedException( $className );
        }

        return $object;
    }


    /* HELPER FUNCTIONS */

    private function createArticleContentType()
    {
        $contentTypeGroups[] = $this->createContentTypeGroup();
        $fieldDefinitions = $this->createArticleFieldDefinitions();

        $dateTime = new DateTime();
        $dateTime->setTimestamp( time() );

        return new ContentType(
            array(
                "names" => 'Article',
                "descriptions" => '',
                "contentTypeGroups" => $contentTypeGroups,
                "fieldDefinitions" => $fieldDefinitions,
                "id" => 1,
                "status" => 0,
                "identifier" => 'article',
                "creationDate" => $dateTime,
                "modificationDate" => $dateTime,
                "creatorId" => 14,
                "modifierId" => 14,
                "remoteId" => 'remoteid',
                "urlAliasSchema" => '<title>',
                "nameSchema" => '<title>',
                "isContainer" => false,
                "mainLanguageCode" => self::DEFAULT_LANGUAGE_CODE,
                "defaultAlwaysAvailable" => true,
                "defaultSortField" => Location::SORT_FIELD_PUBLISHED,
                "defaultSortOrder" => Location::SORT_ORDER_DESC
            )
        );
    }

    /**
     * Helper function to create the needed field definitions for the article class.
     */
    private function createArticleFieldDefinitions()
    {
        /** @var $fieldType \eZ\Publish\SPI\FieldType\FieldType */
        $fieldDefinitions[] = new FieldDefinition(
            array(
                "names" => 'Title',
                "descriptions" => '',
                "id" => 1,
                "identifier" => 'title',
                "fieldGroup" => 'default',
                "position" => 10,
                "fieldTypeIdentifier" => 'ezstring',
                "isTranslatable" => false,
                "isRequired" => true,
                "isInfoCollector" => false,
                "defaultValue" => '',
                "isSearchable" => true,
                "fieldSettings" => array(),
                "validatorConfiguration" => array(),
            )
        );

        /** @var $fieldType \eZ\Publish\SPI\FieldType\FieldType */
        $fieldDefinitions[] = new FieldDefinition(
            array(
                "names" => 'Author',
                "descriptions" => '',
                "id" => 2,
                "identifier" => 'author',
                "fieldGroup" => 'default',
                "position" => 20,
                "fieldTypeIdentifier" => 'ezstring',
                "isTranslatable" => false,
                "isRequired" => true,
                "isInfoCollector" => false,
                "defaultValue" => '',
                "isSearchable" => true,
                "fieldSettings" => array(),
                "validatorConfiguration" => array(),
            )
        );

        /** @var $fieldType \eZ\Publish\SPI\FieldType\FieldType */
        $fieldDefinitions[] = new FieldDefinition(
            array(
                "names" => 'Teaser',
                "descriptions" => '',
                "id" => 3,
                "identifier" => 'intro',
                "fieldGroup" => 'default',
                "position" => 30,
                "fieldTypeIdentifier" => 'ezxmltext',
                "isTranslatable" => false,
                "isRequired" => true,
                "isInfoCollector" => false,
                "defaultValue" => '',
                "isSearchable" => true,
                "fieldSettings" => array(),
                "validatorConfiguration" => array(),
            )
        );

        /** @var $fieldType \eZ\Publish\SPI\FieldType\FieldType */
        $fieldDefinitions[] = new FieldDefinition(
            array(
                "names" => 'Body',
                "descriptions" => '',
                "id" => 4,
                "identifier" => 'body',
                "fieldGroup" => 'default',
                "position" => 40,
                "fieldTypeIdentifier" => 'ezxmltext',
                "isTranslatable" => false,
                "isRequired" => true,
                "isInfoCollector" => false,
                "defaultValue" => '',
                "isSearchable" => true,
                "fieldSettings" => array(),
                "validatorConfiguration" => array(),
            )
        );

        return $fieldDefinitions;
    }

    /**
     * Helper function to create a dummy content type group object.
     *
     * return
     */
    private function createContentTypeGroup()
    {
        $dateTime = new DateTime();
        $dateTime->setTimestamp( time() );

        return new ContentTypeGroup(
            array(
                "id" => 1,
                "identifier" => 'content',
                "creationDate" => $dateTime,
                "modificationDate" => $dateTime,
                "creatorId" => 14,
                "modifierId" => 14,
                "names" => "Content",
                "descriptions" => ""
            )
        );
    }
}
