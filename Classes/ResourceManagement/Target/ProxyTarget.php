<?php
namespace Sitegeist\MagicWand\ResourceManagement\Target;

use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;
use Neos\Flow\ResourceManagement\CollectionInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\Target\Exception;
use Neos\Flow\ResourceManagement\Target\TargetInterface;

class ProxyTarget implements TargetInterface
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
    protected $localTargetName;

    /**
     * @var TargetInterface
     */
    protected $localTarget;

    /**
     * ProxyTarget constructor.
     * @param string $name
     * @param array $options
     */
    public function __construct($name, $options)
    {
        $this->name = $name;

        if (!isset($options['localTarget'])) {
            throw new Exception(sprintf('localTarget-option is required in target %s', $name), 1547635863);
        }

        $this->localTargetName = $options['localTarget'];
    }

    public function initializeObject() {
        $localTargetDefinition = Arrays::getValueByPath($this->settings, 'targets.' . $this->localTargetName );
        if (!isset($localTargetDefinition['target'])) {
            throw new Exception(sprintf('The configuration for the resource target "%s" defined in your settings has no valid "target" option. Please check the configuration syntax and make sure to specify a valid target class name.', $targetName), 1361467838);
        }
        if (!class_exists($localTargetDefinition['target'])) {
            throw new Exception(sprintf('The configuration for the resource target "%s" defined in your settings has not defined a valid "target" option. Please check the configuration syntax and make sure that the specified class "%s" really exists.', $targetName, $targetDefinition['target']), 1361467839);
        }
        $localTargetOptions = (isset($localTargetDefinition['targetOptions']) ? $localTargetDefinition['targetOptions'] : []);

        $this->localTarget = new $localTargetDefinition['target']($this->localTargetName, $localTargetOptions);
    }

    public function getName()
    {
        return $this->name;
    }

    public function publishCollection(CollectionInterface $collection)
    {
        return $this->localTarget->publishCollection($collection);
    }

    public function publishResource(PersistentResource $resource, CollectionInterface $collection)
    {
        return $this->localTarget->publishResource($resource, $collection);
    }

    public function unpublishResource(PersistentResource $resource)
    {
        return $this->localTarget->unpublishResource($resource);
    }

    public function getPublicStaticResourceUri($relativePathAndFilename)
    {
        return $this->localTarget->getPublicStaticResourceUri($relativePathAndFilename);
    }

    public function getPublicPersistentResourceUri(PersistentResource $resource)
    {
        return $this->localTarget->getPublicPersistentResourceUri($resource);
    }

}
