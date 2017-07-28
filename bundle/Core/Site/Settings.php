<?php

namespace Netgen\Bundle\EzPlatformSiteApiBundle\Core\Site;

use eZ\Publish\API\Repository\Exceptions\PropertyNotFoundException;
use eZ\Publish\API\Repository\Exceptions\PropertyReadOnlyException;
use eZ\Publish\Core\MVC\ConfigResolverInterface;
use Netgen\EzPlatformSiteApi\API\Settings as BaseSettings;

final class Settings extends BaseSettings
{
    /**
     * @var bool
     */
    private $useAlwaysAvailable;

    /**
     * @var \eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ConfigResolver
     */
    private $configResolver;

    /**
     * @param \eZ\Publish\Core\MVC\ConfigResolverInterface $configResolver
     * @param bool $useAlwaysAvailable
     */
    public function __construct(ConfigResolverInterface $configResolver, $useAlwaysAvailable)
    {
        $this->configResolver = $configResolver;
        $this->useAlwaysAvailable = $useAlwaysAvailable;
    }

    public function __get($property)
    {
        switch ($property) {
            case 'prioritizedLanguages':
                return $this->configResolver->getParameter('languages');
            case 'useAlwaysAvailable':
                return $this->useAlwaysAvailable;
            case 'rootLocationId':
                return $this->configResolver->getParameter('content.tree_root.location_id');
        }

        throw new PropertyNotFoundException($property, get_class($this));
    }

    public function __set($property, $value)
    {
        throw new PropertyReadOnlyException($property, get_class($this));
    }

    public function __isset($property)
    {
        switch ($property) {
            case 'prioritizedLanguages':
            case 'useAlwaysAvailable':
            case 'rootLocationId':
                return true;
        }

        throw new PropertyNotFoundException($property, get_class($this));
    }
}
