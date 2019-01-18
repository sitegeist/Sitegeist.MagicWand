<?php
namespace Sitegeist\MagicWand\Domain\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Cache\Frontend\VariableFrontend;
use Sitegeist\MagicWand\Domain\ValueObjects\ResourceProxyConfiguration;

class ResourceProxyConfigurationService
{
    /**
     * @Flow\Inject
     * @var VariableFrontend
     */
    protected $resourceProxyConfigurationCache;

    /**
     * @return ResourceProxyConfiguration | null
     */
    public function getCurrentResourceProxyConfiguration() {
        $configuration = $this->resourceProxyConfigurationCache->get('current');
        if ($configuration) {
            return new ResourceProxyConfiguration(
                $configuration['baseUri'],
                $configuration['curlOptions'] ?? []
            );
        } else {
            return null;
        }
    }

    /**
     * @return boolean
     */
    public function hasCurrentResourceProxyConfiguration() {
        return $this->resourceProxyConfigurationCache->has('current');
    }

    /**
     *
     */
    public function setCurrentResourceProxyConfiguration($configuration) {
        $this->resourceProxyConfigurationCache->set('current', $configuration);
    }
}
