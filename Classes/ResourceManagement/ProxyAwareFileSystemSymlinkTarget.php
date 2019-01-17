<?php
namespace Sitegeist\MagicWand\ResourceManagement;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\ResourceManagement\CollectionInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\Target\Exception;
use Neos\Flow\ResourceManagement\Target\FileSystemSymlinkTarget;
use Sitegeist\MagicWand\Domain\Service\ResourceProxyConfigurationService;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Flow\Http\HttpRequestHandlerInterface;
use Neos\Flow\Mvc\ActionRequest;

class ProxyAwareFileSystemSymlinkTarget extends FileSystemSymlinkTarget
{
    /**
     * @var ResourceProxyConfigurationService
     * @Flow\Inject
     */
    protected $resourceProxyConfigurationService;

    /**
     * @var UriBuilder
     * @Flow\Inject
     */
    protected $uriBuilder;

    public function initializeObject() {
        // intialize uribuilder with request
        $requestHandler = $this->bootstrap->getActiveRequestHandler();
        if ($requestHandler instanceof HttpRequestHandlerInterface) {
            $request = new ActionRequest($requestHandler->getHttpRequest());
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
        $resourceProxyConfiguration = $this->resourceProxyConfigurationService->getCurrentResourceProxyConfiguration();
        if ($resourceProxyConfiguration === null) {
            return parent::publishCollection($collection, $callback);
        }

        foreach ($collection->getObjects($callback) as $object) {
            /** @var StorageObject $object */
            $sourceStream = $object->getStream();
            if ($sourceStream !== false) {
                $this->publishFile($sourceStream, $this->getRelativePublicationPathAndFilename($object));
                fclose($sourceStream);
            }

        }
    }

    /**
     * @param PersistentResource $resource
     * @param CollectionInterface $collection
     */
    public function publishResource(PersistentResource $resource, CollectionInterface $collection)
    {
        $resourceProxyConfiguration = $this->resourceProxyConfigurationService->getCurrentResourceProxyConfiguration();
        if ($resourceProxyConfiguration === null) {
            return parent::publishResource($resource, $collection);
        }

        $sourceStream = $resource->getStream();
        if ($sourceStream !== false) {
            return parent::publishResource($resource, $collection);
            fclose($sourceStream);
        }
    }

    /**
     * @param resource $sourceStream
     * @param string $relativeTargetPathAndFilename
     * @throws Exception
     */
    protected function publishFile($sourceStream, $relativeTargetPathAndFilename)
    {
        $resourceProxyConfiguration = $this->resourceProxyConfigurationService->getCurrentResourceProxyConfiguration();
        if ($resourceProxyConfiguration === null) {
            return parent::publishFile($sourceStream, $relativeTargetPathAndFilename);
        }

        if ($sourceStream === false) {
            return;
        } else {
            parent::publishFile($sourceStream, $relativeTargetPathAndFilename);
        }
    }

    /**
     * @param PersistentResource $resource
     * @return string
     * @throws Exception
     */
    public function getPublicPersistentResourceUri(PersistentResource $resource)
    {
        $resourceProxyConfiguration = $this->resourceProxyConfigurationService->getCurrentResourceProxyConfiguration();
        if ($resourceProxyConfiguration === null) {
            return parent::getPublicPersistentResourceUri($resource);
        }

        return $this->uriBuilder->uriFor(
            'index',
            ['resourceIdentifier' => $resource],
            'Resource',
            'Sitegeist.MagicWand'
        );
    }
}
