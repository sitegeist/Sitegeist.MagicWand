<?php
namespace Sitegeist\MagicWand\ResourceManagement;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Client\Browser;
use Neos\Flow\Http\Client\CurlEngine;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\ResourceManagement\ResourceMetaDataInterface;
use Neos\Flow\ResourceManagement\Storage\WritableFileSystemStorage;
use Sitegeist\MagicWand\Domain\Service\ConfigurationService;
use Neos\Utility\Files;

class ProxyAwareWritableFileSystemStorage extends WritableFileSystemStorage
{
    /**
     * @var ConfigurationService
     * @Flow\Inject
     */
    protected $configurationService;

    /**
     * @var ResourceManager
     * @Flow\Inject
     */
    protected $resourceManager;

    /**
     * @param PersistentResource $resource
     * @return string
     */
    public function resourceIsPresentInStorage(ResourceMetaDataInterface $resource) {
        $path =  $this->getStoragePathAndFilenameByHash($resource->getSha1());
        return file_exists($path);
    }

    /**
     * @param PersistentResource $resource
     * @return bool|resource
     */
    public function getStreamByResource(PersistentResource $resource)
    {
        if ($this->resourceIsPresentInStorage($resource)) {
            return parent::getStreamByResource($resource);
        }

        $resourceProxyConfiguration = $this->configurationService->getCurrentConfigurationByPath('resourceProxy');
        if (!$resourceProxyConfiguration) {
            return parent::getStreamByResource($resource);
        }

        $collection = $this->resourceManager->getCollection($resource->getCollectionName());
        $target = $collection->getTarget();
        if (!$target instanceof ProxyAwareFileSystemSymlinkTarget) {
            return parent::getStreamByResource($resource);
        }

        $curlEngine = new CurlEngine();
        $curlOptions = $resourceProxyConfiguration['curlOptions'] ?? [];
        foreach($curlOptions as $key => $value) {
            $curlEngine->setOption(constant($key), $value);
        }

        $browser = new Browser();
        $browser->setRequestEngine($curlEngine);

        $subdivideHashPathSegment = $resourceProxyConfiguration['subdivideHashPathSegment'] ?? false;
        if ($subdivideHashPathSegment) {
            $sha1Hash = $resource->getSha1();
            $uri = $resourceProxyConfiguration['baseUri'] .'/_Resources/Persistent/' . $sha1Hash[0] . '/' . $sha1Hash[1] . '/' . $sha1Hash[2] . '/' . $sha1Hash[3] . '/' . $sha1Hash . '/' . $resource->getSha1() . '/' . $resource->getFilename();;
        } else {
            $uri = $resourceProxyConfiguration['baseUri'] .'/_Resources/Persistent/' . $resource->getSha1() . '/' . $resource->getFilename();
        }

        $response = $browser->request($uri);

        if ($response->getStatusCode() == 200 ) {
            $stream = $response->getBody()->detach();
            $targetPathAndFilename =  $this->getStoragePathAndFilenameByHash($resource->getSha1());
            if (!file_exists(dirname($targetPathAndFilename))) {
                Files::createDirectoryRecursively(dirname($targetPathAndFilename));
            }
            file_put_contents($targetPathAndFilename, stream_get_contents($stream));
            $this->fixFilePermissions($targetPathAndFilename);
            $target->publishResource($resource, $collection);
            return $stream;
        }

        throw new ResourceNotFoundException(
            sprintf('Resource from uri %s returned status %s', $uri, $response->getStatusCode())
        );
    }
}
