<?php
namespace Sitegeist\MagicWand\Status;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinpointInterface;

/**
 * @Flow\Aspect
 * @Flow\Scope("singleton")
 */
class Aspect
{
    /**
     * @Flow\Inject
     * @var Service
     */
    protected $service;

    /**
     * @Flow\After("method(Sitegeist\MagicWand\CloneCommandController->presetCommand())")
     * @param JoinpointInterface $joinPoint
     * @return void
     */
    public function updateCloneStatus(JoinpointInterface $joinPoint)
    {
        $presetName = $joinPoint->getMethodArgument('preset');

        $this->service->getCurrentManifest()->set('clone', 'preset', $presetName);
        $this->service->saveToDisk($this->service->getCurrentManifest());
    }

    /**
     * @Flow\After("method(Sitegeist\MagicWand\StashCommandController->restoreCommand())")
     * @param JoinpointInterface $joinPoint
     * @return void
     */
    public function updateStashStatus(JoinpointInterface $joinPoint)
    {
        $name = $joinPoint->getMethodArgument('name');

        $this->service->getCurrentManifest()->set('stash', 'name', $name);
        $this->service->saveToDisk($this->service->getCurrentManifest());
    }

    /**
     * @Flow\After("method(Sitegeist\MagicWand\StashCommandController->clearCommand())")
     * @param JoinpointInterface $joinPoint
     * @return void
     */
    public function clearStashStatus(JoinpointInterface $joinPoint)
    {
        $this->service->getCurrentManifest()->set('stash', 'name', null);
        $this->service->saveToDisk($this->service->getCurrentManifest());
    }

    /**
     * @Flow\After("method(Sitegeist\MagicWand\StashCommandController->removeCommand())")
     * @param JoinpointInterface $joinPoint
     * @return void
     */
    public function clearStashStatusForEntry(JoinpointInterface $joinPoint)
    {
        $currentName = $this->service->getCurrentManifest()->get('stash', 'name');
        $name = $joinPoint->getMethodArgument('name');

        if ($currentName === $name) {
            $this->service->getCurrentManifest()->set('stash', 'name', null);
            $this->service->saveToDisk($this->service->getCurrentManifest());
        }
    }
}
