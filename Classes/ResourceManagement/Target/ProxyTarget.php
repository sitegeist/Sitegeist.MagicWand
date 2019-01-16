<?php
namespace Sitegeist\MagicWand\ResourceManagement\Target;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Request;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Utility\Arrays;
use Neos\Flow\ResourceManagement\CollectionInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\Target\Exception;
use Neos\Flow\ResourceManagement\Target\TargetInterface;

use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Http\HttpRequestHandlerInterface;

class ProxyTarget implements TargetInterface
{
    /**
     * @var Bootstrap
     * @Flow\Inject
     */
    protected $bootstrap;

    /**
     * @var
     * @Flow\InjectConfiguration(package="Neos.Flow", path="resource")
     */
    protected $settings;

    /**
     * @var UriBuilder
     * @Flow\Inject
     */
    protected $uriBuilder;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var TargetInterface
     */
    protected $localTarget;

    /**
     * @var string
     */
    protected $localTargetName;

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
        // intialize uribuilder with request
        $requestHandler = $this->bootstrap->getActiveRequestHandler();
        if ($requestHandler instanceof HttpRequestHandlerInterface) {
            $request = new ActionRequest($requestHandler->getHttpRequest());
            $this->uriBuilder->setRequest($request);
        }

        // initialize targets
        $this->localTarget = $this->initalizeTarget($this->localTargetName);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param CollectionInterface $collection
     */
    public function publishCollection(CollectionInterface $collection)
    {
        return $this->localTarget->publishCollection($collection);
    }

    /**
     * @param PersistentResource $resource
     * @param CollectionInterface $collection
     * @throws Exception
     */
    public function publishResource(PersistentResource $resource, CollectionInterface $collection)
    {
        return $this->localTarget->publishResource($resource, $collection);
    }

    /**
     * @param PersistentResource $resource
     */
    public function unpublishResource(PersistentResource $resource)
    {
        return $this->localTarget->unpublishResource($resource);
    }

    /**
     * @param string $relativePathAndFilename
     * @return string
     */
    public function getPublicStaticResourceUri($relativePathAndFilename)
    {
        return $this->localTarget->getPublicStaticResourceUri($relativePathAndFilename);
    }

    /**
     * @param PersistentResource $resource
     * @return string
     * @throws Exception
     */
    public function getPublicPersistentResourceUri(PersistentResource $resource)
    {
        //return $this->localTarget->getPublicPersistentResourceUri($resource);
        return $this->uriBuilder->uriFor(
            'index',
            ['resourceIdentifier' => $resource],
            'Resource',
            'Sitegeist.MagicWand'
        );
    }

    /**
     * @param string $name
     * @return TargetInterface
     * @throws Exception
     * @see \Neos\Flow\ResourceManagement\ResourceManager::initializeTargets
     */
    protected function initalizeTarget($name)
    {
        $targetDefinition = Arrays::getValueByPath($this->settings, 'targets.' . $name);
        if (!isset($targetDefinition['target'])) {
            throw new Exception(sprintf('The configuration for the resource target "%s" defined in your settings has no valid "target" option. Please check the configuration syntax and make sure to specify a valid target class name.', $name), 1361467838);
        }
        if (!class_exists($targetDefinition['target'])) {
            throw new Exception(sprintf('The configuration for the resource target "%s" defined in your settings has not defined a valid "target" option. Please check the configuration syntax and make sure that the specified class "%s" really exists.', $name, $targetDefinition['target']), 1361467839);
        }
        $options = (isset($targetDefinition['targetOptions']) ? $targetDefinition['targetOptions'] : []);
        return new $targetDefinition['target']($this->localTargetName, $options);
    }

}
