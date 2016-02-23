<?php
namespace Sitegeist\MagicWand\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sitegeist.MagicWand".   *
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Core\Bootstrap;
use TYPO3\Flow\Cli\CommandController;

abstract class AbstractCommandController extends CommandController
{
    /**
     * @Flow\InjectConfiguration(path="persistence.backendOptions", package="TYPO3.Flow")
     * @var array
     */
    protected $databaseConfiguration;

    /**
     * @Flow\Inject
     * @var Bootstrap
     */
    protected $bootstrap;

    /**
     * @var int
     */
    protected $headlineNumber = 0;

    /**
     * @param string $title
     * @param string $commands
     * @param array $arguments
     */
    protected function executeShellCommandWithFeedback($title, $command, $arguments, $secret = NULL)
    {
        $this->outputHeadLine($title);
        $customizedCommand = call_user_func_array('sprintf', array_merge([$command], $arguments));
        $this->outputLine($customizedCommand);
        $customizedCommandResult = shell_exec($customizedCommand);
        $this->outputLine($customizedCommandResult);
        return $customizedCommandResult;
    }

    /**
     * @param $line
     */
    protected function outputHeadLine($line)
    {
        $this->headlineNumber ++;
        $this->outputLine();
        $this->outputLine($this->headlineNumber . '. ' . $line);
        $this->outputLine();
    }
}