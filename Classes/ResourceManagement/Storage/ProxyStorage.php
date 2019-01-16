<?php
namespace Sitegeist\MagicWand\ResourceManagement\Storage;

use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;
use Neos\Flow\ResourceManagement\CollectionInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\Storage\Exception as StorageException;
use Neos\Flow\ResourceManagement\Storage\WritableStorageInterface;

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

    public function __construct($name, $options = [])
    {
        $this->name = $name;
        if (!isset($options['localStorage'])) {
            throw new Exception(sprintf('localStorage-option is required in storage %s', $name), 1547635803);
        }
        $this->localStorageName = $options['localStorage'];
    }

    public function initializeObject() {
        $localStorageDefinition = Arrays::getValueByPath($this->settings, 'storages.' . $this->localStorageName );

        if (!isset($localStorageDefinition['storage'])) {
            throw new Exception(sprintf('The configuration for the resource storage "%s" defined in your settings has no valid "storage" option. Please check the configuration syntax and make sure to specify a valid storage class name.', $this->localStorageName), 1361467211);
        }
        if (!class_exists($localStorageDefinition['storage'])) {
            throw new Exception(sprintf('The configuration for the resource storage "%s" defined in your settings has not defined a valid "storage" option. Please check the configuration syntax and make sure that the specified class "%s" really exists.', $this->localStorageName, $localStorageDefinition['storage']), 1361467212);
        }
        $localStorageOptions = (isset($localStorageDefinition['storageOptions']) ? $localStorageDefinition['storageOptions'] : []);
        $this->localStorage = new $localStorageDefinition['storage']($this->localStorageName, $localStorageOptions);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    public function getStreamByResource(PersistentResource $resource)
    {
        return $this->localStorage->getStreamByResourcePath($resource);
    }

    public function getStreamByResourcePath($relativePath)
    {
        return $this->localStorage->getStreamByResourcePath($relativePath);
    }

    public function getObjects()
    {
        return $this->localStorage->getObjects();
    }

    public function getObjectsByCollection(CollectionInterface $collection)
    {
        return $this->localStorage->getObjectsByCollection($collection);
    }

    public function importResource($source, $collectionName)
    {
        return $this->localStorage->importResource($source, $collectionName);
    }

    public function importResourceFromContent($content, $collectionName)
    {
        return $this->localStorage->importResourceFromContent($content, $collectionName);
    }

    public function deleteResource(PersistentResource $resource)
    {
        return $this->localStorage->deleteResource($resource);
    }
}
