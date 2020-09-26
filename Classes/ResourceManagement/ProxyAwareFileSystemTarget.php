<?php
declare(strict_types=1);

namespace Sitegeist\MagicWand\ResourceManagement;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\ResourceManagement\Target\FileSystemTarget;
use Sitegeist\MagicWand\Domain\Service\ConfigurationService;

class ProxyAwareFileSystemTarget extends FileSystemTarget implements ProxyAwareTargetInterface
{
    use ProxyAwareTargetTrait;

    /**
     * @Flow\Inject
     * @var Bootstrap
     */
    protected $bootstrap;

    /**
     * @var UriBuilder
     * @Flow\Inject
     */
    protected $uriBuilder;

    /**
     * @var ResourceManager
     * @Flow\Inject
     */
    protected $resourceManager;

    /**
     * @var ConfigurationService
     * @Flow\Inject
     */
    protected $configurationService;
}
