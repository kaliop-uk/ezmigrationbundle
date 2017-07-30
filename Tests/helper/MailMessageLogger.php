<?php

namespace Kaliop\eZMigrationBundle\Tests\helper;

use Swift_Plugins_MessageLogger;
use Swift_Events_SendEvent;

class MailMessageLogger extends Swift_Plugins_MessageLogger
{
    /**
     * @todo make the name of the written file configurable, and/or do set references to it on saving
     * @param Swift_Events_SendEvent $evt
     */
    public function sendPerformed(Swift_Events_SendEvent $evt)
    {
        $msg = $evt->getMessage();
        file_put_contents('/tmp/mail.txt', $msg->toString());
    }
}
