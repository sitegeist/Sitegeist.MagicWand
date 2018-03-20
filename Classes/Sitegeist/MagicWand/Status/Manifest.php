<?php
namespace Sitegeist\MagicWand\Status;

/**
 * An object representing a manifest file that contains status information for the most recently
 * performed clone and stash operations
 */
class Manifest
{
    /**
     * Allowed sections for the manifest file
     *
     * @var array
     */
    const ALLOWED_SECTIONS = ['clone', 'stash'];

    /**
     * Array representation of the manifest file
     *
     * @var array
     */
    protected $manifest;

    /**
     * Private constructor: Use static create* factory methods!
     */
    private function __construct(array $manifest)
    {
        $this->manifest = $manifest;
    }

    /**
     * Create an empty manifest
     *
     * @return Manifest
     */
    public static function createEmpty()
    {
        return new static([
            'clone' => [
                'latest' => new \DateTime()
            ],
            'stash' => [
                'latest' => new \DateTime()
            ]
        ]);
    }

    /**
     * Create manifest object from a local file
     *
     * @param string $fileName
     * @return Manifest
     */
    public static function createFromDisk($fileName)
    {
        $contents = @file_get_contents($fileName);
        $json = @json_decode($fileName, true);

        if ($json) {
            return new static($json);
        }

        throw new \Exception(sprintf('Could not read "%s"', $fileName), 1521548892);
    }

    /**
     * Set a value in the manifest
     *
     * @param string $section
     * @param string $property
     * @param mixed $value
     */
    public function set($section, $property, $value)
    {
        if (!in_array($section, static::ALLOWED_SECTIONS)) {
            throw new \Exception(sprintf('Invalid section "%s"', $fileName), 1521548898);
        }

        if (!array_key_exists($section, $this->manifest)) {
            $this->manifest[$section] = [];
        }

        $this->manifest[$section][$property] = $value;
        $this->manifest[$section]['latest'] = new \DateTime();
    }

    /**
     * Get a value from the manifest
     *
     * @param string $section
     * @param string $property
     * @return mixed
     */
    public function get($section, $property)
    {
        if (!in_array($section, static::ALLOWED_SECTIONS)) {
            throw new \Exception(sprintf('Invalid section "%s"', $fileName), 1521548898);
        }

        if (!array_key_exists($section, $this->manifest)) {
            return null;
        }

        if (!array_key_exists($property, $this->manifest[$section])) {
            return null;
        }

        return $this->manifest[$section][$property];
    }
}
