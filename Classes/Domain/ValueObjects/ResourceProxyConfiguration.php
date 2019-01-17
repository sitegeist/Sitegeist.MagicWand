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

    /**
     * @var bool
     */
    protected $subdivideHashPathSegment;

    public function __construct(string $baseUri, array $curlOptions = [], bool $subdivideHashPathSegment = false)
    {
        $this->baseUri = $baseUri;
        $this->curlOptions = $curlOptions;
        $this->subdivideHashPathSegment = $subdivideHashPathSegment;
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

    /**
     * @return bool
     */
    public function isSubdivideHashPathSegment(): bool
    {
        return $this->subdivideHashPathSegment;
    }
}
