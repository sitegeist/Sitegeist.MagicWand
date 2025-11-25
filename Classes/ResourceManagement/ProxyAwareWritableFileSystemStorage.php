<?php
namespace Sitegeist\MagicWand\ResourceManagement;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Client\Browser;
use Neos\Flow\Http\Client\CurlEngine;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\ResourceManagement\ResourceMetaDataInterface;
use Neos\Flow\ResourceManagement\Storage\WritableFileSystemStorage;
use Psr\Log\LoggerInterface;
use Sitegeist\MagicWand\Domain\Service\ConfigurationService;
use Neos\Utility\Files;

class ProxyAwareWritableFileSystemStorage extends WritableFileSystemStorage implements ProxyAwareStorageInterface
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

    #[Flow\Inject]
    protected LoggerInterface $logger;

    /**
     * @param ResourceMetaDataInterface $resource
     * @return bool
     */
    public function resourceIsPresentInStorage(ResourceMetaDataInterface $resource): bool
    {
        $path = $this->getStoragePathAndFilenameByHash($resource->getSha1());
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
            $this->logger->warning('No resource proxy configuration was found. Falling back to WritableFileSystemStorage', LogEnvironment::fromMethodName(__METHOD__));
            return parent::getStreamByResource($resource);
        }

        $collection = $this->resourceManager->getCollection($resource->getCollectionName());
        $target = $collection->getTarget();
        if (!$target instanceof ProxyAwareFileSystemSymlinkTarget) {
            return parent::getStreamByResource($resource);
        }

        $curlEngine = new CurlEngine();
        $curlOptions = $resourceProxyConfiguration['curlOptions'] ?? [];
        foreach ($curlOptions as $key => $value) {
            $curlEngine->setOption(constant($key), $value);
        }

        $browser = new Browser();
        $browser->setRequestEngine($curlEngine);

        $subDirectory = $resourceProxyConfiguration['subDirectory'] ?? '_Resources/Persistent/';
        $subdivideHashPathSegment = $resourceProxyConfiguration['subdivideHashPathSegment'] ?? false;
        if ($subdivideHashPathSegment) {
            $sha1Hash = $resource->getSha1();
            $uri = $resourceProxyConfiguration['baseUri'] . '/' . $subDirectory . $sha1Hash[0] . '/' . $sha1Hash[1] . '/' . $sha1Hash[2] . '/' . $sha1Hash[3] . '/' . $sha1Hash . '/' . rawurlencode($resource->getFilename());
        } else {
            $uri = $resourceProxyConfiguration['baseUri'] . '/' . $subDirectory . $resource->getSha1() . '/' . rawurlencode($resource->getFilename());
        }

        $response = $browser->request($uri);

        if ($response->getStatusCode() == 200) {
            $stream = $response->getBody()->detach();
            $targetPathAndFilename = $this->getStoragePathAndFilenameByHash($resource->getSha1());

            if (!file_exists(dirname($targetPathAndFilename))) {
                Files::createDirectoryRecursively(dirname($targetPathAndFilename));
            }

            file_put_contents($targetPathAndFilename, stream_get_contents($stream));
            $this->fixFilePermissions($targetPathAndFilename);
            $target->publishResource($resource, $collection);

            $this->logger->info(sprintf('Successfully downloaded asset "%s" from remote and published it to "%s"', $uri, $targetPathAndFilename), LogEnvironment::fromMethodName(__METHOD__));
            return $stream;
        } else {
            $this->logger->error(sprintf('Got status code %s while trying, to fetch asset from URL %s', $response->getStatusCode(), $uri), LogEnvironment::fromMethodName(__METHOD__));
        }

        throw new ResourceNotFoundException(
            sprintf('Resource from uri %s returned status %s', $uri, $response->getStatusCode())
        );
    }
}
