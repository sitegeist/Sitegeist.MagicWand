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
            } else {
                throw new ResourceNotFoundException(sprintf('Could not read stream of resource with id %s ', $resourceIdentifier));
            }
        }

        throw new ResourceNotFoundException(sprintf('Could not find any resource with id %s in local database', $resourceIdentifier));
    }
}
