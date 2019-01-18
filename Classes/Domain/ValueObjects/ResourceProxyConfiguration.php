<?php
namespace Sitegeist\MagicWand\Domain\ValueObjects;

class ResourceProxyConfiguration
{
    /**
     * @var string
     */
    protected $baseUri;

    /**
     * @var array
     */
    protected $curlOptions;

    public function __construct(string $baseUri, array $curlOptions = [])
    {
        $this->baseUri = $baseUri;
        $this->curlOptions = $curlOptions;
    }

    /**
     * @return string
     */
    public function getBaseUri(): string
    {
        return $this->baseUri;
    }

    /**
     * @return array
     */
    public function getCurlOptions(): array
    {
        return $this->curlOptions;
    }
}
