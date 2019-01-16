<?php
namespace Sitegeist\MagicWand\ResourceManagement\Storage;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Client\Browser;
use Neos\Flow\Http\Client\CurlEngine;
use Neos\Flow\ResourceManagement\Storage\StorageInterface;
use Neos\Utility\Arrays;
use Neos\Flow\ResourceManagement\CollectionInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\Storage\Exception as StorageException;
use Neos\Flow\ResourceManagement\Storage\WritableStorageInterface;
use Sitegeist\MagicWand\ResourceManagement\ResourceNotFoundException;

class ProxyStorage implements WritableStorageInterface
{
    /**
     * @var
     * @Flow\InjectConfiguration(package="Neos.Flow", path="resource")
     */
    protected $settings;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $localStorageName;

    /**
     * @var WritableStorageInterface
     */
    protected $localStorage;

    /**
     * ProxyStorage constructor.
     * @param $name string
     * @param array $options
     */
    public function __construct($name, $options = [])
    {
        $this->name = $name;
        if (!isset($options['localStorage'])) {
            throw new Exception(sprintf('localStorage-option is required in storage %s', $name), 1547635803);
        }
        $this->localStorageName = $options['localStorage'];
    }

    public function initializeObject()
    {
        $this->localStorage = $this->initializeStorage($this->localStorageName);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param PersistentResource $resource
     * @return bool|resource
     */
    public function getStreamByResource(PersistentResource $resource)
    {
        $localResourceStream = $this->localStorage->getStreamByResource($resource);
        if ($localResourceStream) {
            return $localResourceStream;
        } else {

            $curlEngine = new CurlEngine();

            $browser = new Browser();
            $browser->setRequestEngine($curlEngine);

            $uri = $baseUri . '/_Resources/Persistent/' . $resource->getSha1() . '/' . $resource->getFilename();

            $response = $browser->request($uri);
            if ($response->getStatusCode() == 200 ) {
                $response->getContent();

                $stream = fopen('php://memory', 'r+');
                fwrite($stream, $response->getContent());
                rewind($stream);

                $this->localStorage->importResource($stream, $resource->getCollectionName());

                return $stream;
            } else {

                \Neos\Flow\var_dump($uri);
                throw new ResourceNotFoundException('Kennichnich');
            }
        }
    }

    /**
     * @param string $relativePath
     * @return bool|resource
     */
    public function getStreamByResourcePath($relativePath)
    {
        return $this->localStorage->getStreamByResourcePath($relativePath);
    }

    /**
     * @return \Generator
     */
    public function getObjects()
    {
        return $this->localStorage->getObjects();
    }

    /**
     * @param CollectionInterface $collection
     * @return \Generator
     */
    public function getObjectsByCollection(CollectionInterface $collection)
    {
        return $this->localStorage->getObjectsByCollection($collection);
    }

    /**
     * @param resource|string $source
     * @param string $collectionName
     * @return PersistentResource
     * @throws StorageException
     */
    public function importResource($source, $collectionName)
    {
        return $this->localStorage->importResource($source, $collectionName);
    }

    /**
     * @param string $content
     * @param string $collectionName
     * @return PersistentResource
     * @throws StorageException
     */
    public function importResourceFromContent($content, $collectionName)
    {
        return $this->localStorage->importResourceFromContent($content, $collectionName);
    }

    /**
     * @param PersistentResource $resource
     * @return bool
     * @throws \Exception
     */
    public function deleteResource(PersistentResource $resource)
    {
        if ($this->isLocal($resource)) {
            return $this->localStorage->deleteResource($resource);
        } else {
            throw new \Exception('proxy-resources cannot be deleted');
        }
    }

    /**
     * @param PersistentResource $resource
     * @return bool
     */
    protected function isLocal(PersistentResource $resource)
    {
        return true;
    }

    /**
     * @param string $name
     * @return StorageInterface
     * @see \Neos\Flow\ResourceManagement\ResourceManager::initializeStorages
     */
    protected function initializeStorage($name)
    {
        $storageDefinition = Arrays::getValueByPath($this->settings, 'storages.' . $name);

        if (!isset($storageDefinition['storage'])) {
            throw new Exception(sprintf('The configuration for the resource storage "%s" defined in your settings has no valid "storage" option. Please check the configuration syntax and make sure to specify a valid storage class name.', $name), 1361467211);
        }
        if (!class_exists($storageDefinition['storage'])) {
            throw new Exception(sprintf('The configuration for the resource storage "%s" defined in your settings has not defined a valid "storage" option. Please check the configuration syntax and make sure that the specified class "%s" really exists.', $name, $storageDefinition['storage']), 1361467212);
        }
        $options = (isset($storageDefinition['storageOptions']) ? $storageDefinition['storageOptions'] : []);
        return new $storageDefinition['storage']($this->localStorageName, $options);
    }
}
