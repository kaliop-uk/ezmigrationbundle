<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\EmbeddedReferenceResolverBagInterface;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Swift_Message;
use Swift_Attachment;

/**
 * @property EmbeddedReferenceResolverBagInterface $referenceResolver
 */
class MailExecutor extends AbstractExecutor
{
    use IgnorableStepExecutorTrait;

    protected $supportedStepTypes = array('mail');
    protected $supportedActions = array('send');

    protected $mailService;

    /**
     * MailExecutor constructor.
     * @param $mailService
     * @param EmbeddedReferenceResolverBagInterface $referenceResolver must implement EmbeddedReferenceResolverInterface too
     */
    public function __construct($mailService, EmbeddedReferenceResolverBagInterface $referenceResolver)
    {
        $this->mailService = $mailService;
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
     * @return true
     * @throws \Exception
     */
    protected function send($dsl, $context)
    {
        // cater to Swiftmailer 5 and 6
        if (is_callable(array('Swift_Message', 'newInstance'))) {
            $message = Swift_Message::newInstance();
        } else {
            $message = new Swift_Message();
        }

        if (isset($dsl['from'])) {
            $message->setFrom($this->resolveReferencesRecursively($dsl['from']));
        }
        if (isset($dsl['to'])) {
            $message->setTo($this->resolveReferencesRecursively($dsl['to']));
        }
        if (isset($dsl['cc'])) {
            $message->setCc($this->resolveReferencesRecursively($dsl['cc']));
        }
        if (isset($dsl['bcc'])) {
            $message->setBcc($this->resolveReferencesRecursively($dsl['bcc']));
        }
        if (isset($dsl['subject'])) {
            $message->setSubject($this->resolveReferencesInText($dsl['subject']));
        }
        if (isset($dsl['body'])) {
            $message->setBody($this->resolveReferencesInText($dsl['body']));
        }
        if (isset($dsl['attach'])) {
            $paths = $this->resolveReferencesRecursively($dsl['attach']);
            foreach((array)$paths as $path) {
                // we use the same logic as for the image/file fields in content: look up file 1st relative to the migration
                $attachment = dirname($context['path']) . '/' . $path;
                if (!is_file($attachment)) {
                    $attachment = $path;
                }
                $message->attach(Swift_Attachment::fromPath($attachment));
            }
        }

        if (isset($dsl['priority'])) {
            $message->setPriority($this->resolveReference($dsl['priority']));
        }
        if (isset($dsl['read_receipt_to'])) {
            $message->setReadReceiptTo($this->resolveReferencesRecursively($dsl['read_receipt_to']));
        }
        if (isset($dsl['return_path'])) {
            $message->setReturnPath($this->resolveReferencesRecursively($dsl['return_path']));
        }
        if (isset($dsl['reply_to'])) {
            $message->setReplyTo($this->resolveReferencesRecursively($dsl['reply_to']));
        }
        if (isset($dsl['sender'])) {
            $message->setSender($this->resolveReferencesRecursively($dsl['sender']));
        }

        $this->mailService->send($message);

        // q: shall we set any reference?

        // q: what to return?
        return true;
    }

    /**
     * Replaces any references inside a string
     *
     * @param string
     * @return string
     * @throws \Exception
     */
    protected function resolveReferencesInText($text)
    {
        return $this->referenceResolver->resolveEmbeddedReferences($text);
    }
}
