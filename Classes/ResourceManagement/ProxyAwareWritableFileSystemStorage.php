<?php
namespace Sitegeist\MagicWand\ResourceManagement;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Client\Browser;
use Neos\Flow\Http\Client\CurlEngine;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceManager;
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
     * @return bool|resource
     */
    public function getStreamByResource(PersistentResource $resource)
    {
        $resourceProxyConfiguration = $this->resourceProxyConfigurationService->getCurrentResourceProxyConfiguration();
        if (!$resourceProxyConfiguration) {
            return parent::getStreamByResource($resource);
        }

        $localResourceStream = parent::getStreamByResource($resource);
        if ($localResourceStream) {
            return $localResourceStream;
        } else {
            $curlEngine = new CurlEngine();
            foreach($resourceProxyConfiguration->getCurlOptions() as $key => $value) {
                $curlEngine->setOption(constant($key), $value);
            }

            $browser = new Browser();
            $browser->setRequestEngine($curlEngine);

            $uri = $resourceProxyConfiguration->getBaseUri() .'/_Resources/Persistent/' . $resource->getSha1() . '/' . $resource->getFilename();

            $response = $browser->request($uri);

            if ($response->getStatusCode() == 200 ) {
                $response->getContent();

                $stream = fopen('php://memory', 'r+');
                fwrite($stream, $response->getContent());
                rewind($stream);

                parent::importResource($stream, $resource->getCollectionName());

                return $stream;
            } else {
                throw new ResourceNotFoundException('Kennichnich');
            }
        }
    }
}
