<?php
namespace Sitegeist\MagicWand\Command;

/*                                                                        *
 * This script belongs to the Neos Flow package "Sitegeist.MagicWand".    *
 *                                                                        *
 *                                                                        */

use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;
use Neos\Flow\Core\Bootstrap;

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
                    if (is_array($value)) {
                        $this->outputLine(' - ' . $key . ':');

                        foreach ($value as $line) {
                            $this->outputLine('        ' . $line);
                        }

                        continue;
                    }

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
        if (count($this->clonePresets)>0 ) {
            if ($this->clonePresets && array_key_exists($presetName, $this->clonePresets)) {
                $this->outputLine('Clone by preset ' . $presetName);
                $this->remoteHostCommand(
                    $this->clonePresets[$presetName]['host'],
                    $this->clonePresets[$presetName]['user'],
                    $this->clonePresets[$presetName]['port'],
                    $this->clonePresets[$presetName]['path'],
                    $this->clonePresets[$presetName]['context'],
                    (isset($this->clonePresets[$presetName]['postClone']) ? $this->clonePresets[$presetName]['postClone'] : null),
                    $yes,
                    $keepDb,
                    (isset($this->clonePresets[$presetName]['flowCommand']) ? $this->clonePresets[$presetName]['flowCommand'] : null)
                );
            } else {
                $this->outputLine('The preset ' . $presetName . ' was not found!');
                $this->quit(1);
            }
        } else {
            $this->outputLine('No presets found!');
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
     * @param mixded $postClone command or array of commands to be executed after cloning
     * @param boolean $yes confirm execution without further input
     * @param boolean $keepDb skip dropping of database during sync
     * @param string $remoteFlowCommand the flow command to execute on the remote system
     */
    public function remoteHostCommand(
        $host,
        $user,
        $port,
        $path,
        $context = 'Production',
        $postClone = null,
        $yes = false,
        $keepDb = false,
        $remoteFlowCommand = null
    ) {
        // fallback
        if ($remoteFlowCommand === null) {
            $remoteFlowCommand = $this->flowCommand;
        }

        // read local configuration
        $this->outputHeadLine('Read local configuration');

        $localDataPersistentPath = FLOW_PATH_ROOT . 'Data/Persistent';

        // read remote configuration
        $this->outputHeadLine('Fetch remote configuration');
        $remotePersistenceConfigurationYaml = $this->executeLocalShellCommand(
            'ssh -p %s  %s@%s  "cd %s; FLOW_CONTEXT=%s ' . $remoteFlowCommand . ' configuration:show --type Settings --path Neos.Flow.persistence.backendOptions;"',
            [
                $port,
                $user,
                $host,
                $path,
                $context
            ],
            [
                self::HIDE_RESULT
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

        ######################
        # Measure Start Time #
        ######################

        $startTimestamp = time();

        ##################
        # Define Secrets #
        ##################

        $this->addSecret($this->databaseConfiguration['user']);
        $this->addSecret($this->databaseConfiguration['password']);
        $this->addSecret($remotePersistenceConfiguration['user']);
        $this->addSecret($remotePersistenceConfiguration['password']);

        #######################
        # Check Configuration #
        #######################

        $this->checkConfiguration($remotePersistenceConfiguration);

        ################################################
        # Fallback to default MySQL port if not given. #
        ################################################

        if ( ! isset($this->databaseConfiguration['port'])) {
            $this->databaseConfiguration['port'] = 3306;
        }

        ########################
        # Drop and Recreate DB #
        ########################

        if ($keepDb == false) {
            $this->outputHeadLine('Drop and Recreate DB');

            $emptyLocalDbSql = 'DROP DATABASE `' . $this->databaseConfiguration['dbname'] . '`; CREATE DATABASE `' . $this->databaseConfiguration['dbname'] . '` collate utf8_unicode_ci;';
            $this->executeLocalShellCommand(
                'echo %s | mysql --host=\'%s\' --port=\'%s\' --user=\'%s\' --password=\'%s\'',
                [
                    escapeshellarg($emptyLocalDbSql),
                    $this->databaseConfiguration['host'],
                    $this->databaseConfiguration['port'],
                    $this->databaseConfiguration['user'],
                    $this->databaseConfiguration['password']
                ]
            );
        } else {
            $this->outputHeadLine('Skipped (Drop and Recreate DB)');
        }

        ######################
        #  Transfer Database #
        ######################

        $this->outputHeadLine('Transfer Database');
        $this->executeLocalShellCommand(
            'ssh -p %s %s@%s \'mysqldump --add-drop-table --host=\'"\'"\'%s\'"\'"\' --user=\'"\'"\'%s\'"\'"\' --password=\'"\'"\'%s\'"\'"\' \'"\'"\'%s\'"\'"\'\' | mysql --host=\'%s\' --port=\'%s\' --user=\'%s\' --password=\'%s\' \'%s\'',
            [
                $port,
                $user,
                $host,
                $remotePersistenceConfiguration['host'],
                $remotePersistenceConfiguration['user'],
                $remotePersistenceConfiguration['password'],
                $remotePersistenceConfiguration['dbname'],
                $this->databaseConfiguration['host'],
                $this->databaseConfiguration['port'],
                $this->databaseConfiguration['user'],
                $this->databaseConfiguration['password'],
                $this->databaseConfiguration['dbname']
            ]
        );

        ##################
        # Transfer Files #
        ##################

        $this->outputHeadLine('Transfer Files');
        $this->executeLocalShellCommand(
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

        $this->outputHeadLine('Clear Caches');
        $this->executeLocalFlowCommand('flow:cache:flush');

        ##############
        # Migrate DB #
        ##############

        $this->outputHeadLine('Migrate cloned DB');
        $this->executeLocalFlowCommand('doctrine:migrate');

        #####################
        # Publish Resources #
        #####################

        $this->outputHeadLine('Publish Resources');
        $this->executeLocalFlowCommand('resource:publish');

        ##############
        # Post Clone #
        ##############

        if ($postClone) {
            $this->outputHeadLine('Execute post_clone commands');
            if (is_array($postClone)) {
                foreach($postClone as $postCloneCommand) {
                    $this->executeLocalShellCommandWithFlowContext($postCloneCommand);
                }
            } else {
                $this->executeLocalShellCommandWithFlowContext($postClone);
            }
        }

        #################
        # Final Message #
        #################

        $endTimestamp = time();
        $duration = $endTimestamp - $startTimestamp;

        $this->outputHeadLine('Done');
        $this->outputLine('Successfully cloned in %s seconds', [$duration]);
    }

    /**
     * @param $remotePersistenceConfiguration
     * @param $this ->databaseConfiguration
     * @throws \Neos\Flow\Mvc\Exception\StopActionException
     */
    protected function checkConfiguration($remotePersistenceConfiguration)
    {
        $this->outputHeadLine('Check Configuration');
        if ($remotePersistenceConfiguration['driver'] != 'pdo_mysql' && $this->databaseConfiguration['driver'] != 'pdo_mysql') {
            $this->outputLine(' only mysql is supported');
            $this->quit(1);
        }
        if ($remotePersistenceConfiguration['charset'] != $this->databaseConfiguration['charset']) {
            $this->outputLine(' the databases have to use the same charset');
            $this->quit(1);
        }
        $this->outputLine(' - Configuration seems ok ...');
    }
}
