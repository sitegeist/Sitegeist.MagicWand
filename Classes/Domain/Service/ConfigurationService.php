<?php

namespace Sitegeist\MagicWand\Domain\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Utility\Arrays;
use Psr\Log\LoggerInterface;

class ConfigurationService
{
    /**
     * @Flow\Inject
     * @var VariableFrontend
     */
    protected $clonePresetInformationCache;

    #[Flow\Inject]
    protected LoggerInterface $logger;

    /**
     * @var string
     * @Flow\InjectConfiguration("clonePresets")
     */
    protected $clonePresets;

    /**
     * @return string
     */
    public function getCurrentPreset(): ?string
    {
        $clonePresetInformation = $this->clonePresetInformationCache->get('current');

        if (!$clonePresetInformation || !is_array($clonePresetInformation)) {
            $this->logger->warning('MagicWand did not find any preset information in the cache. Run the flow command "clone:preset" first to fill the clonePresetInformationCache.', LogEnvironment::fromMethodName(__METHOD__));
            return null;
        }

        if (isset($clonePresetInformation['presetName'])) {
            return $clonePresetInformation['presetName'];
        }

        return null;
    }

    /**
     * @return integer
     */
    public function getMostRecentCloneTimeStamp(): ?int
    {
        $clonePresetInformation = $this->clonePresetInformationCache->get('current');

        if ($clonePresetInformation && is_array($clonePresetInformation) && isset($clonePresetInformation['cloned_at'])) {
            return intval($clonePresetInformation['cloned_at']);
        }

        return null;
    }

    /**
     * @return array
     */
    public function getCurrentConfiguration(): array
    {
        if ($presetName = $this->getCurrentPreset()) {
            if (is_array($this->clonePresets) && array_key_exists($presetName, $this->clonePresets)) {
                return $this->clonePresets[$presetName];
            }
        }

        return [];
    }

    /**
     * @return mixed
     */
    public function getCurrentConfigurationByPath($path)
    {
        $currentConfiguration = $this->getCurrentConfiguration();
        $value = Arrays::getValueByPath($currentConfiguration, $path);

        if ($value === null) {
            $this->logger->warning(sprintf('Tried to get value at path "%s" from cached configuration with preset name "%s". But the path does not exists. Existing entries are "%s" ', $path, $this->getCurrentPreset(), json_encode($currentConfiguration)), LogEnvironment::fromMethodName(__METHOD__));
        }

        return $value;
    }

    /**
     * @return boolean
     */
    public function hasCurrentPreset(): bool
    {
        if ($this->clonePresetInformationCache->has('current')) {
            return true;
        }

        $clonePresetInformation = $this->clonePresetInformationCache->get('current');

        if ($clonePresetInformation && is_array($clonePresetInformation) && isset($clonePresetInformation['presetName'])) {
            return true;
        }

        return false;
    }

    /**
     * @param $presetName string
     * @return void
     * @throws \Neos\Cache\Exception
     */
    public function setCurrentPreset(string $presetName): void
    {
        $this->clonePresetInformationCache->set('current', [
            'presetName' => $presetName,
            'cloned_at' => time()
        ]);

        $this->logger->info(sprintf('Set "%s" as current preset name to the cache', $presetName), LogEnvironment::fromMethodName(__METHOD__));
    }

    /**
     * @param string $stashEntryName
     * @param array $stashEntryManifest
     * @return void
     * @throws \Neos\Cache\Exception
     */
    public function setCurrentStashEntry(string $stashEntryName, array $stashEntryManifest): void
    {
        if (!isset($stashEntryManifest['preset']['name'])) {
            return;
        }

        if (!isset($stashEntryManifest['cloned_at'])) {
            return;
        }

        $presetName = $stashEntryManifest['preset']['name'];
        $clonedAt = $stashEntryManifest['cloned_at'];

        $this->clonePresetInformationCache->set('current', [
            'presetName' => $presetName,
            'cloned_at' => $clonedAt
        ]);
    }
}
