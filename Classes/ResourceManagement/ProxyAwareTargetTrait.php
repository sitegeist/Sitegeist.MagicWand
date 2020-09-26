<?php

namespace Sitegeist\MagicWand\ResourceManagement;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Http\Exception as HttpException;
use Neos\Flow\Http\HttpRequestHandlerInterface;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Routing\Exception\MissingActionNameException;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Flow\ResourceManagement\CollectionInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\ResourceManagement\Storage\StorageObject;
use Neos\Flow\ResourceManagement\Target\Exception;
use Sitegeist\MagicWand\Domain\Service\ConfigurationService;

trait ProxyAwareTargetTrait
{
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
     * @param CollectionInterface $collection The collection to publish
     * @param callable $callback Function called after each resource publishing
     * @return void
     */
    public function publishCollection(CollectionInterface $collection, callable $callback = null)
    {
        if (!$this->configurationService->getCurrentConfigurationByPath('resourceProxy')) {
            parent::publishCollection($collection, $callback);
            return;
        }

        /**
         * @var ProxyAwareWritableFileSystemStorage $storage
         */
        $storage = $collection->getStorage();
        if (!$storage instanceof ProxyAwareStorageInterface) {
            parent::publishCollection($collection, $callback);
            return;
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
     * @throws HttpException
     * @throws MissingActionNameException
     */
    public function getPublicPersistentResourceUri(PersistentResource $resource)
    {
        if (!$this->configurationService->getCurrentConfigurationByPath('resourceProxy')) {
            return parent::getPublicPersistentResourceUri($resource);
        }

        $collection = $this->resourceManager->getCollection($resource->getCollectionName());
        $storage = $collection->getStorage();

        if (!$storage instanceof ProxyAwareStorageInterface) {
            return parent::getPublicPersistentResourceUri($resource);
        }

        if ($storage->resourceIsPresentInStorage($resource)) {
            return parent::getPublicPersistentResourceUri($resource);
        }

        // build uri to resource controller that will fetch and publish
        // the resource asynchronously
        return $this->uriBuilder->uriFor(
            'index',
            ['resourceIdentifier' => $resource],
            'Resource',
            'Sitegeist.MagicWand'
        );
    }
}
