<?php
namespace Sitegeist\MagicWand\ResourceManagement;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Client\Browser;
use Neos\Flow\Http\Client\CurlEngine;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\ResourceManagement\ResourceMetaDataInterface;
use Neos\Flow\ResourceManagement\Storage\WritableFileSystemStorage;
use Sitegeist\MagicWand\Domain\Service\ResourceProxyConfigurationService;

class ProxyAwareWritableFileSystemStorage extends WritableFileSystemStorage
{
    /**
     * @var ResourceProxyConfigurationService
     * @Flow\Inject
     */
    protected $resourceProxyConfigurationService;

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
        if ($this->resourceProxyConfigurationService->hasCurrentResourceProxyConfiguration() === false) {
            return parent::getStreamByResource($resource);
        }

        $resourceProxyConfiguration = $this->resourceProxyConfigurationService->getCurrentResourceProxyConfiguration();
        $isPresent = $this->resourceIsPresentInStorage($resource);
        if ($isPresent) {
            return parent::getStreamByResource($resource);
        } else {
            $collection = $this->resourceManager->getCollection($resource->getCollectionName());
            /**
             * @var ProxyAwareFileSystemSymlinkTarget $target
             */
            $target = $collection->getTarget();

            $curlEngine = new CurlEngine();
            foreach($resourceProxyConfiguration->getCurlOptions() as $key => $value) {
                $curlEngine->setOption(constant($key), $value);
            }

            $browser = new Browser();
            $browser->setRequestEngine($curlEngine);

            if ($target instanceof ProxyAwareFileSystemSymlinkTarget && $target->isSubdivideHashPathSegment()) {
                $sha1Hash = $resource->getSha1();
                $uri = $resourceProxyConfiguration->getBaseUri() .'/_Resources/Persistent/' . $sha1Hash[0] . '/' . $sha1Hash[1] . '/' . $sha1Hash[2] . '/' . $sha1Hash[3] . '/' . $sha1Hash . '/' . $object->getFilename();
            } else {
                $uri = $resourceProxyConfiguration->getBaseUri() .'/_Resources/Persistent/' . $resource->getSha1() . '/' . $resource->getFilename();
            }

            $response = $browser->request($uri);

            if ($response->getStatusCode() == 200 ) {
                $response->getContent();

                $stream = fopen('php://memory', 'r+');
                fwrite($stream, $response->getContent());
                rewind($stream);

                $collection->importResource($stream);
                $target->publishResource($resource, $collection);

                return $stream;
            } else {
                throw new ResourceNotFoundException('Kennichnich');
            }
        }
    }
}
