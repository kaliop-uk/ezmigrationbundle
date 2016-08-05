<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use eZ\Bundle\EzPublishCoreBundle\FieldType\Page\PageService;
use eZ\Publish\Core\FieldType\Page\Value as PageValue;
use eZ\Publish\Core\FieldType\Page\Type as PageType;
use eZ\Publish\Core\FieldType\Page\HashConverter;
use eZ\Publish\Core\FieldType\Page\Parts\Page;
use Kaliop\eZMigrationBundle\API\ComplexFieldInterface;

class EzPage extends AbstractComplexField implements ComplexFieldInterface
{
    /** @var PageService $pageService */
    protected $pageService;

    public function __construct(PageService $pageService)
    {
        $this->pageService = $pageService;
    }

    /**
     * Creates a value object to use as the field value when setting an ez page field type.
     *
     * @param array $fieldValueArray The definition of teh field value, structured in the yml file
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return PageValue
     */
    public function createValue(array $fieldValueArray, array $context = array())
    {
        $layout = $fieldValueArray['layout'];

        $hashConverter = new HashConverter();
        $pageType = new PageType($this->pageService, $hashConverter);

        /** @var PageValue $pageValue */
        $pageValue = $pageType->getEmptyValue();

        $pageValue->page = new Page(array(
            'zones' =>array(),
            'zonesById' => array(),
            'layout' => $layout
        ));

        return $pageValue;
    }
}
