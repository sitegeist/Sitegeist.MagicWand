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
        $cloneInformations = $this->clonePresetInformationCache->get('current');
        if ($cloneInformations && is_array($this->clonePresets) && array_key_exists($cloneInformations['presetName'], $this->clonePresets)) {
            return $this->clonePresets[$cloneInformations['presetName']];
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
