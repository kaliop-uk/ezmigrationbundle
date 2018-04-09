<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;
use Kaliop\eZMigrationBundle\Core\ReferenceResolver\PrefixBasedResolverInterface;
use Swift_Message;
use Swift_Attachment;

class MailExecutor extends AbstractExecutor
{
    use IgnorableStepExecutorTrait;

    protected $supportedStepTypes = array('mail');
    protected $supportedActions = array('send');

    protected $mailService;
    /** @var ReferenceResolverInterface $referenceResolver */
    protected $referenceResolver;

    public function __construct($mailService, PrefixBasedResolverInterface $referenceResolver)
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
            throw new \Exception("Invalid step definition: missing 'mode'");
        }

        $action = $step->dsl['mode'];

        if (!in_array($action, $this->supportedActions)) {
            throw new \Exception("Invalid step definition: value '$action' is not allowed for 'mode'");
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

        $message = Swift_Message::newInstance();

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
            $path = $this->resolveReferencesRecursively($dsl['attach']);
            // we use the same logic as for the image/file fields in content: look up file 1st relative to the migration
            $attachment = dirname($context['path']) . '/' . $path;
            if (!is_file($attachment)) {
                $attachment = $path;
            }
            $message->attach(Swift_Attachment::fromPath($attachment));
        }

        if (isset($dsl['priority'])) {
            $message->setPriority($this->resolveReferencesRecursively($dsl['priority']));
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
     * @deprecated should be moved into the reference resolver classes
     */
    protected function resolveReferencesRecursively($match)
    {
        if (is_array($match)) {
            foreach ($match as $condition => $values) {
                $match[$condition] = $this->resolveReferencesRecursively($values);
            }
            return $match;
        } else {
            return $this->referenceResolver->resolveReference($match);
        }
    }

    /**
     * Replaces any references inside a string
     *
     * @param string
     * @return string
     */
    protected function resolveReferencesInText($text)
    {
        // we need to alter the regexp we get from the resolver, as it will be used to match parts of text, not the whole string
        $regexp = substr($this->referenceResolver->getRegexp(), 1, -1);
        // NB: here we assume that all regexp resolvers give us a regexp with a very specific format...
        $regexp = '/\[' . preg_replace(array('/^\^/'), array('', ''), $regexp) . '[^]]+\]/';

        $count = preg_match_all($regexp, $text, $matches);
        // $matches[0][] will have the matched full string eg.: [reference:example_reference]
        if ($count) {
            foreach ($matches[0] as $referenceIdentifier) {
                $reference = $this->referenceResolver->getReferenceValue(substr($referenceIdentifier, 1, -1));
                $text = str_replace($referenceIdentifier, $reference, $text);
            }
        }

        return $text;
    }
}
