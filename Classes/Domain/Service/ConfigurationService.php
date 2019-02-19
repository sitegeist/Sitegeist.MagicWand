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
     * @return array
     */
    public function getCurrentConfiguration(): array
    {
        $cloneInformation = $this->clonePresetInformationCache->get('current');
        if ($cloneInformation && is_array($this->clonePresets) && array_key_exists($cloneInformation['presetName'], $this->clonePresets)) {
            return $this->clonePresets[$cloneInformation['presetName']];
        } else {
            return [];
        }
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
    public function hasConfiguration(): bool
    {
        return $this->clonePresetInformationCache->has('current');
    }

    /**
     * @param $presetName string
     * @throws \Neos\Cache\Exception
     */
    public function setCurrentPreset(string $presetName): void
    {
        $this->clonePresetInformationCache->set('current', ['presetName' => $presetName, 'timestamp' => time()]);
    }
}
