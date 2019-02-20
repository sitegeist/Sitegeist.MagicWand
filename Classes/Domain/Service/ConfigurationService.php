<?php
namespace Sitegeist\MagicWand\Domain\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Utility\Arrays;

class ConfigurationService
{
    /**
     * @Flow\Inject
     * @var VariableFrontend
     */
    protected $clonePresetInformationCache;

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

        if ($clonePresetInformation && is_array($clonePresetInformation) && isset($clonePresetInformation['presetName'])) {
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
        return Arrays::getValueByPath($currentConfiguration, $path);
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
