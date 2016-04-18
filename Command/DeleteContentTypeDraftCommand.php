<?php
namespace Kaliop\eZMigrationBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup;

class DeleteContentTypeDraftCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('kaliop:delete:content-type-draft')
            ->setDescription('Delete all Content Type drafts in the system');
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->printMessage('<info>Removing Content Type Drafts</info>', $output);

        $repository = $this->getContainer()->get('ezpublish.api.repository');

        //Login the admin user
        //Drafts can only be loaded for the currently logged in user. The assumption is
        //that the admin user created the drafts and not another user.
        $repository->setCurrentUser($repository->getUserService()->loadUserByLogin('admin'));

        $contentTypeService = $repository->getContentTypeService();

        $contentTypeGroups = $contentTypeService->loadContentTypeGroups();

        foreach ($contentTypeGroups as $contentTypeGroup) {
            $this->printMessage('<info>Group: </info>' . $contentTypeGroup->identifier, $output);
            $this->cleanUpContentTypeDraftsInGroup($contentTypeGroup, $contentTypeService, $output);
        }
    }

    /**
     * Loop through all content types in a group and remove their drafts if
     * those exist
     *
     * @param ContentTypeGroup $contentTypeGroup
     * @param ContentTypeService $contentTypeService
     * @param OutputInterface $output
     */
    private function cleanUpContentTypeDraftsInGroup($contentTypeGroup, ContentTypeService $contentTypeService, OutputInterface $output)
    {
        // Get all the content types in a group
        $contentTypes = $contentTypeService->loadContentTypes($contentTypeGroup);

        foreach ($contentTypes as $contentType) {
            // Load a content type draft if any
            $this->printMessage('Checking <comment>' . $contentType->getName('eng-GB') . '</comment> type for drafts.', $output);
            $this->loadAndRemoveDraft($contentType->id, $contentTypeService, $output);
        }
    }

    /**
     * Load a content type's draft and delete it if it exists
     *
     * @param int $contentTypeId
     * @param ContentTypeService $contentTypeService
     * @param OutputInterface $output
     */
    private function loadAndRemoveDraft($contentTypeId, ContentTypeService $contentTypeService, OutputInterface $output)
    {
        try {
            $contentTypeDraft = $contentTypeService->loadContentTypeDraft($contentTypeId);

            $this->printMessage('<comment>  Removing draft</comment>', $output);
            $contentTypeService->deleteContentType($contentTypeDraft);
        } catch (NotFoundException $e) {
            // Do nothing as there was no draft
            $this->printMessage('<comment>  No drafts found.</comment>', $output);
        }
    }

    /**
     * @param string $message
     * @param OutputInterface $output
     */
    private function printMessage($message, OutputInterface $output)
    {
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln($message);
        }
    }
}