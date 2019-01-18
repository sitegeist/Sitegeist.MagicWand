<?php
namespace Sitegeist\MagicWand\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceRepository;
use Neos\Flow\ResourceManagement\ResourceManager;
use Sitegeist\MagicWand\ResourceManagement\ResourceNotFoundException;

class ResourceController extends ActionController
{

    /**
     * @var ResourceRepository
     * @Flow\Inject
     */
    protected $resourceRepository;

    /**
     * @var ResourceManager
     * @Flow\Inject
     */
    protected $resourceManager;

    /**
     * @param string $resourceIdentifier
     */
    public function indexAction(string $resourceIdentifier) {
        /**
         * @var PersistentResource $resource
         */
        $resource = $this->resourceRepository->findByIdentifier($resourceIdentifier);
        if ($resource) {
            $sourceStream = $resource->getStream();
            if ($sourceStream !== false) {
                fclose($sourceStream);
                $this->redirectToUri($this->resourceManager->getPublicPersistentResourceUri($resource), 0, 302);
            }
        }
        throw new ResourceNotFoundException("Unknown resource");
    }
}
