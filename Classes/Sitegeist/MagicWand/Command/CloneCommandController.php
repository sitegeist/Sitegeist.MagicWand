<?php
namespace Sitegeist\MagicWand\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sitegeist.MagicWand".   *
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Configuration\ConfigurationManager;
use TYPO3\Flow\Utility\Arrays;
use TYPO3\Flow\Core\Bootstrap;

/**
 * @Flow\Scope("singleton")
 */
class CloneCommandController extends AbstractCommandController
{

    /**
     * @Flow\Inject
     * @var Bootstrap
     */
    protected $bootstrap;

    /**
     * @Flow\Inject
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @var string
     * @Flow\InjectConfiguration("clonePresets")
     */
    protected $clonePresets;

    /**
     * Show the list of predefined clone configurations
     */
    public function listCommand()
    {
        if ($this->clonePresets) {
            foreach ($this->clonePresets as $presetName => $presetConfiguration) {
                $this->outputHeadLine($presetName);
                foreach ($presetConfiguration as $key => $value) {
                    $this->outputLine(' - ' . $key . ': ' . $value);
                }
            }
        }
    }

    /**
     * Clone a flow setup as specified in Settings.yaml (Sitegeist.MagicWand.clonePresets ...)
     *
     * @param string $presetName name of the preset from the settings
     * @param boolean $yes confirm execution without further input
     * @param boolean $keepDb skip dropping of database during sync
     */
    public function presetCommand($presetName, $yes = false, $keepDb = false)
    {
        if ($this->clonePresets && array_key_exists($presetName, $this->clonePresets)) {
            $this->outputLine('Clone by preset ' . $presetName);
            $this->remoteHostCommand(
                $this->clonePresets[$presetName]['host'],
                $this->clonePresets[$presetName]['user'],
                $this->clonePresets[$presetName]['port'],
                $this->clonePresets[$presetName]['path'],
                $this->clonePresets[$presetName]['context'],
                $yes,
                $keepDb
            );
        } else {
            $this->outputLine('The preset ' . $presetName . ' was not found!');
            $this->quit(1);
        }
    }

    /**
     * Clone a Flow Setup via detailed hostname
     *
     * @param string $host ssh host
     * @param string $user ssh user
     * @param string $port ssh port
     * @param string $path path on the remote server
     * @param string $context flow_context on the remote server
     * @param boolean $yes confirm execution without further input
     * @param boolean $keepDb skip dropping of database during sync
     */
    public function remoteHostCommand(
        $host,
        $user,
        $port,
        $path,
        $context = 'Production',
        $yes = false,
        $keepDb = false
    ) {
        // read local configuration
        $this->outputHeadLine('Read local configuration');
        $localPersistenceConfiguration = $this->databaseConfiguration;
        $this->outputLine(\Symfony\Component\Yaml\Yaml::dump($localPersistenceConfiguration));
        $localDataPersistentPath = FLOW_PATH_ROOT . 'Data/Persistent';

        // read remote configuration
        $remotePersistenceConfigurationYaml = $this->executeShellCommandWithFeedback(
            'Fetch remote configuration',
            'ssh -p %s  %s@%s  "cd %s; FLOW_CONTEXT=%s ./flow configuration:show --type Settings --path TYPO3.Flow.persistence.backendOptions;"',
            [
                $port,
                $user,
                $host,
                $path,
                $context
            ]
        );

        if ($remotePersistenceConfigurationYaml) {
            $remotePersistenceConfiguration = \Symfony\Component\Yaml\Yaml::parse($remotePersistenceConfigurationYaml);
        }
        $remoteDataPersistentPath = $path . '/Data/Persistent';

        #################
        # Are you sure? #
        #################

        if (!$yes) {
            $this->outputLine("Are you sure you want to do this?  Type 'yes' to continue: ");
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            if (trim($line) != 'yes') {
                $this->outputLine('exit');
                $this->quit(1);
            } else {
                $this->outputLine();
                $this->outputLine();
            }
        }

        #######################
        # Check Configuration #
        #######################

        $this->outputHeadLine('Check Configuration');
        if ($remotePersistenceConfiguration['driver'] != 'pdo_mysql' && $localPersistenceConfiguration['driver'] != 'pdo_mysql') {
            $this->outputLine(' only mysql is supported');
            $this->quit(1);
        }
        if ($remotePersistenceConfiguration['charset'] != $localPersistenceConfiguration['charset']) {
            $this->outputLine(' the databases have to use the same charset');
            $this->quit(1);
        }
        $this->outputLine(' - Configuration seems ok ...');

        ########################
        # Drop and Recreate DB #
        ########################

        if ($keepDb == false) {
            $emptyLocalDbSql = 'DROP DATABASE ' . $localPersistenceConfiguration['dbname'] . '; CREATE DATABASE ' . $localPersistenceConfiguration['dbname'] . ' collate utf8_unicode_ci;';
            $this->executeShellCommandWithFeedback(
                'Drop and Recreate DB',
                'echo %s | mysql --host=%s --user=%s --password=%s',
                [
                    escapeshellarg($emptyLocalDbSql),
                    $localPersistenceConfiguration['host'],
                    $localPersistenceConfiguration['user'],
                    $localPersistenceConfiguration['password']
                ]
            );
        } else {
            $this->outputHeadLine('Skipped (Drop and Recreate DB)');
        }

        ######################
        #  Transfer Database #
        ######################

        $this->executeShellCommandWithFeedback(
            'Transfer Database',
            'ssh -p %s %s@%s "mysqldump --add-drop-table --host=%s --user=%s --password=%s %s" | mysql --host=%s --user=%s --password=%s %s',
            [
                $port,
                $user,
                $host,
                $remotePersistenceConfiguration['host'],
                $remotePersistenceConfiguration['user'],
                $remotePersistenceConfiguration['password'],
                $remotePersistenceConfiguration['dbname'],
                $localPersistenceConfiguration['host'],
                $localPersistenceConfiguration['user'],
                $localPersistenceConfiguration['password'],
                $localPersistenceConfiguration['dbname']
            ]
        );

        ##################
        # Transfer Files #
        ##################

        $this->executeShellCommandWithFeedback(
            'Transfer Files',
            'rsync -e "ssh -p %s" -kLr %s@%s:%s/* %s',
            [
                $port,
                $user,
                $host,
                $remoteDataPersistentPath,
                $localDataPersistentPath
            ]
        );

        ################
        # Clear Caches #
        ################

        $this->executeShellCommandWithFeedback(
            'Clear Caches',
            'FLOW_CONTEXT=%s ./flow flow:cache:flush',
            [$this->bootstrap->getContext()]
        );

        ##############
        # Migrate DB #
        ##############

        $this->executeShellCommandWithFeedback(
            'Migrate cloned DB',
            'FLOW_CONTEXT=%s ./flow doctrine:migrate',
            [$this->bootstrap->getContext()]
        );

        #####################
        # Publish Resources #
        #####################

        $this->executeShellCommandWithFeedback(
            'Migrate cloned DB',
            'FLOW_CONTEXT=%s ./flow resource:publish',
            [$this->bootstrap->getContext()]
        );
    }
}
