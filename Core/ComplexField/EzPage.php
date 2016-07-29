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
    /**
     * Creates a value object to use as the field value when setting an ez page field type.
     *
     * @return PageValue
     */
    public function createValue()
    {
        $layout = $this->fieldValueArray['layout'];

        /** @var PageService $pageService */
        $pageService = $this->container->get('ezpublish.fieldType.ezpage.pageService');
        $hashConverter = new HashConverter();
        $pageType = new PageType($pageService, $hashConverter);

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
