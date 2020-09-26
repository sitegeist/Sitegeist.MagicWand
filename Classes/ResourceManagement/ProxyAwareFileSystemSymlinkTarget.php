<?php
namespace Sitegeist\MagicWand\ResourceManagement;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\ResourceManagement\CollectionInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\ResourceManagement\ResourceRepository;
use Neos\Flow\ResourceManagement\Target\Exception;
use Neos\Flow\ResourceManagement\Target\FileSystemSymlinkTarget;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Flow\Http\HttpRequestHandlerInterface;
use Neos\Flow\Mvc\ActionRequest;
use Sitegeist\MagicWand\Domain\Service\ConfigurationService;
use Sitegeist\MagicWand\ResourceManagement\ProxyAwareWritableFileSystemStorage;
use Neos\Flow\ResourceManagement\Storage\StorageObject;

class ProxyAwareFileSystemSymlinkTarget extends FileSystemSymlinkTarget
{
    /**
     * @Flow\Inject
     * @var Bootstrap
     */
    protected $bootstrap;

    /**
     * @var ConfigurationService
     * @Flow\Inject
     */
    protected $configurationService;

    /**
     * @var UriBuilder
     * @Flow\Inject
     */
    protected $uriBuilder;

    /**
     * @var ResourceRepository
     * @Flow\Inject
     */
    protected $resourceRepository;

    /**
     * @var ResourceManager
     * @Flow\Inject
     */
    protected $resourceManager;

    public function initializeObject()
    {
        // initialize uriBuilder with request
        $requestHandler = $this->bootstrap->getActiveRequestHandler();
        if ($requestHandler instanceof HttpRequestHandlerInterface) {
            $request = ActionRequest::fromHttpRequest($requestHandler->getComponentContext()->getHttpRequest());
            $this->uriBuilder->setRequest($request);
        }
        parent::initializeObject();
    }

    /**
     * Publishes the whole collection to this target
     *
     * @param CollectionInterface $collection The collection to publish
     * @param callable $callback Function called after each resource publishing
     * @return void
     */
    public function publishCollection(CollectionInterface $collection, callable $callback = null)
    {
        if (!$this->configurationService->getCurrentConfigurationByPath('resourceProxy')) {
            return parent::publishCollection($collection, $callback);
        }

        /**
         * @var ProxyAwareWritableFileSystemStorage $storage
         */
        $storage = $collection->getStorage();
        if (!$storage instanceof ProxyAwareWritableFileSystemStorage) {
            return parent::publishCollection($collection, $callback);
        }

        foreach ($collection->getObjects($callback) as $object) {
            /** @var StorageObject $object */
            if ($storage->resourceIsPresentInStorage($object) === false) {
                // this storage ignores resources that are not yet in the filesystem as they
                // are optimistically created during read operations
                continue;
            }
            $sourceStream = $object->getStream();
            $this->publishFile($sourceStream, $this->getRelativePublicationPathAndFilename($object));
            fclose($sourceStream);
        }
    }

    /**
     * @param PersistentResource $resource
     * @return string
     * @throws Exception
     */
    public function getPublicPersistentResourceUri(PersistentResource $resource)
    {
        if (!$this->configurationService->getCurrentConfigurationByPath('resourceProxy')) {
            return parent::getPublicPersistentResourceUri($resource);
        }

        $collection = $this->resourceManager->getCollection($resource->getCollectionName());
        $storage = $collection->getStorage();

        if (!$storage instanceof ProxyAwareWritableFileSystemStorage) {
            return parent::getPublicPersistentResourceUri($resource);
        }

        if ($storage->resourceIsPresentInStorage($resource)) {
            return parent::getPublicPersistentResourceUri($resource);
        }

        // build uri to resoucre controller that will fetch and publish
        // the resource asynchronously
        return $this->uriBuilder->uriFor(
            'index',
            ['resourceIdentifier' => $resource],
            'Resource',
            'Sitegeist.MagicWand'
        );
    }
}
