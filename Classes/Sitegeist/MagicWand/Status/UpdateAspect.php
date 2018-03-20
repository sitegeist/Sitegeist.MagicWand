<?php
namespace Sitegeist\MagicWand\Status;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;

/**
 * @Flow\Aspect
 * @Flow\Scope("singleton")
 */
class UpdateAspect
{
    /**
     * @Flow\Inject
     * @var Service
     */
    protected $service;

    /**
     * @Flow\After("method(Sitegeist\MagicWand\Command\CloneCommandController->presetCommand())")
     * @param JoinPointInterface $joinPoint
     * @return void
     */
    public function updateCloneStatus(JoinPointInterface $joinPoint)
    {
        $presetName = $joinPoint->getMethodArgument('presetName');

        $this->service->getCurrentManifest()->set('clone', 'preset', $presetName);
        $this->service->saveToDisk($this->service->getCurrentManifest());
    }

    /**
     * @Flow\After("method(Sitegeist\MagicWand\Command\StashCommandController->restoreCommand())")
     * @param JoinPointInterface $joinPoint
     * @return void
     */
    public function updateStashStatus(JoinPointInterface $joinPoint)
    {
        $name = $joinPoint->getMethodArgument('name');

        $this->service->getCurrentManifest()->set('stash', 'name', $name);
        $this->service->saveToDisk($this->service->getCurrentManifest());
    }

    /**
     * @Flow\After("method(Sitegeist\MagicWand\Command\StashCommandController->clearCommand())")
     * @param JoinPointInterface $joinPoint
     * @return void
     */
    public function clearStashStatus(JoinPointInterface $joinPoint)
    {
        $this->service->getCurrentManifest()->set('stash', 'name', null);
        $this->service->saveToDisk($this->service->getCurrentManifest());
    }

    /**
     * @Flow\After("method(Sitegeist\MagicWand\Command\StashCommandController->removeCommand())")
     * @param JoinPointInterface $joinPoint
     * @return void
     */
    public function clearStashStatusForEntry(JoinPointInterface $joinPoint)
    {
        $currentName = $this->service->getCurrentManifest()->get('stash', 'name');
        $name = $joinPoint->getMethodArgument('name');

        if ($currentName === $name) {
            $this->service->getCurrentManifest()->set('stash', 'name', null);
            $this->service->saveToDisk($this->service->getCurrentManifest());
        }
    }
}
