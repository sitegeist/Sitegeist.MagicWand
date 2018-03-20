<?php
namespace Sitegeist\MagicWand\Status;

use Neos\Flow\Annotations as Flow;
use Neos\Utility\Files;

/**
 * @Flow\Scope("singleton")
 */
class Service
{
    /**
     * @var Manifest
     */
    protected $current;

    /**
     * @Flow\InjectConfiguration(path="pathToMetadata")
     * @var string
     */
    protected $pathToMetadata;

    /**
     * @var string
     */
    protected $pathToStatusManifestFile;

    /**
     * @return void
     */
    protected function initializeObject()
    {
        if (!is_writable($this->pathToMetadata)) {
            @Files::createDirectoryRecursively($this->pathToMetadata);
        }

        $this->pathToStatusManifestFile = Files::concatenatePaths([$this->pathToMetadata, 'status']);
    }

    /**
     * Get current status manifest
     *
     * @return Manifest
     */
    public function getCurrentManifest()
    {
        if (!$this->current) {
            $this->current = $this->getFromDisk() ?? $this->getEmpty();
        }

        return $this->current;
    }

    /**
     * Get stashed manifest
     * @param string $stashName
     * @return Manifest
     */
    public function getStashedManifest($stashName)
    {
        $manifestFile = Files::concatenatePaths([
            FLOW_PATH_ROOT,
            'Data/MagicWandStash',
            $stashName,
            '.magicwand/status'
        ]);

        if (file_exists($manifestFile)) {
            return Manifest::createFromDisk($manifestFile);
        }
    }

    /**
     * Get persisted Manifest from Disk
     *
     * @return Manifest
     */
    protected function getFromDisk()
    {
        if (file_exists($this->pathToStatusManifestFile)) {
            return Manifest::createFromDisk($this->pathToStatusManifestFile);
        }
    }

    /**
     * Create new Manifest
     *
     * @return Manifest
     */
    protected function getEmpty()
    {
        return Manifest::createEmpty();
    }

    /**
     * Save manifest to disk
     *
     * @param Manifest $manifest
     * @return void
     */
    public function saveToDisk(Manifest $manifest)
    {
        $json = json_encode($manifest);
        file_put_contents($this->pathToStatusManifestFile, $json);
    }
}
