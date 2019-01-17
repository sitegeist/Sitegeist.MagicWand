<?php
namespace Sitegeist\MagicWand\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceRepository;
use Sitegeist\MagicWand\ResourceManagement\ResourceNotFoundException;

class ResourceController extends ActionController
{

    /**
     * @var ResourceRepository
     * @Flow\Inject
     */
    protected $resourceRepository;

    /**
     * @param string $resourceIdentifier
     */
    public function indexAction(string $resourceIdentifier) {
        /**
         * @var PersistentResource $resource
         */
        $resource = $this->resourceRepository->findByIdentifier($resourceIdentifier);
        if ($resource) {
            $headers = $this->response->getHeaders();
            $headers->set('Content-Type', $resource->getMediaType(), true);
            $this->response->setHeaders($headers);
            $sourceStream = $resource->getStream();
            $streamContent = stream_get_contents($sourceStream);
            fclose($sourceStream);
            return $streamContent;
        } else {
            throw new ResourceNotFoundException("Unkonwn Resource");
        }
    }
}
