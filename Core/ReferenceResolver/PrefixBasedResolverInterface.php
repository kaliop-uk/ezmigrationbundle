<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

/**
 * At the moment this interface does nothing more than its parent.
 * *However* we assume that all classes implementing this interface do produce a regexp which starts with a left anchoring: /^.../
 *
 * @deprecated in favour of EmbeddedReferenceResolverInterface
 */
interface PrefixBasedResolverInterface extends RegexpBasedResolverInterface
{
}
