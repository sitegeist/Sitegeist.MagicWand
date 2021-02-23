<?php
namespace Sitegeist\MagicWand\Command;

/*                                                                        *
 * This script belongs to the Neos Flow package "Sitegeist.MagicWand".    *
 *                                                                        *
 *                                                                        */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\Exception\StopCommandException;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Utility\Arrays;
use Neos\Flow\Core\Bootstrap;
use Sitegeist\MagicWand\DBAL\SimpleDBAL;
use Symfony\Component\Yaml\Yaml;

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
     * @var string
     * @Flow\InjectConfiguration("defaultPreset")
     */
    protected $defaultPreset;

    /**
     * @Flow\Inject
     * @var SimpleDBAL
     */
    protected $dbal;

    /**
     * Show the list of predefined clone configurations
     */
    public function listCommand()
    {
        if ($this->clonePresets) {
            foreach ($this->clonePresets as $presetName => $presetConfiguration) {
                $this->renderHeadLine($presetName);
                $presetConfigurationAsYaml = Yaml::dump($presetConfiguration);
                $lines = explode(PHP_EOL, $presetConfigurationAsYaml);
                foreach ($lines as $line) {
                    $this->renderLine($line);
                }
            }
        }
    }

    /**
     * Clones the default preset
     *
     * @param boolean $yes confirm execution without further input
     * @param boolean $keepDb skip dropping of database during sync
     */
    public function defaultCommand(bool $yes = false, bool $keepDb = false): void
    {
        if ($this->defaultPreset === null || $this->defaultPreset === '') {
            $this->renderLine('There is no default preset configured!');
            $this->quit(1);
        }

        $this->presetCommand($this->defaultPreset, $yes, $keepDb);
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
        if (count($this->clonePresets) > 0) {
            if ($this->clonePresets && array_key_exists($presetName, $this->clonePresets)) {

                $this->configurationService->setCurrentPreset($presetName);
                $configuration = $this->configurationService->getCurrentConfiguration();

                $this->renderLine('Clone by preset ' . $presetName);
                $this->cloneRemoteHost(
                    $configuration['host'],
                    $configuration['user'],
                    $configuration['port'],
                    $configuration['path'],
                    $configuration['context'],
                    $configuration['clone'] ?? null,
                    $configuration['postClone'] ?? null,
                    $yes,
                    $keepDb,
                    $configuration['flowCommand'] ?? null,
                    $configuration['sshOptions'] ?? ''
                );
            } else {
                $this->renderLine('The preset ' . $presetName . ' was not found!');
                $this->quit(1);
            }
        } else {
            $this->renderLine('No presets found!');
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
     * @param null $clone
     * @param null $postClone command or array of commands to be executed after cloning
     * @param boolean $yes confirm execution without further input
     * @param boolean $keepDb skip dropping of database during sync
     * @param null $remoteFlowCommand the flow command to execute on the remote system
     * @param string $sshOptions additional options for the ssh command
     * @throws StopCommandException
     * @throws StopActionException
     */
    protected function cloneRemoteHost(
        $host,
        $user,
        $port,
        $path,
        $context = 'Production',
        $clone = null,
        $postClone = null,
        $yes = false,
        $keepDb = false,
        $remoteFlowCommand = null,
        $sshOptions = ''
    )
    {
        // fallback
        if ($remoteFlowCommand === null) {
            $remoteFlowCommand = $this->flowCommand;
        }

        // read local configuration
        $this->renderHeadLine('Read local configuration');

        $localDataPersistentPath = FLOW_PATH_ROOT . 'Data/Persistent';

        // read remote configuration
        $this->renderHeadLine('Fetch remote configuration');
        $remotePersistenceConfigurationYaml = $this->executeLocalShellCommand(
            'ssh -p %s %s %s@%s "cd %s; FLOW_CONTEXT=%s '
            . $remoteFlowCommand
            . ' configuration:show --type Settings --path Neos.Flow.persistence.backendOptions;"',
            [
                $port,
                $sshOptions,
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
            $remotePersistenceConfiguration = Yaml::parse($remotePersistenceConfigurationYaml);
        }
        $remoteDataPersistentPath = $path . '/Data/Persistent';

        #################
        # Are you sure? #
        #################

        if (!$yes) {
            $this->renderLine("Are you sure you want to do this?  Type 'yes' to continue: ");
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            if (trim($line) != 'yes') {
                $this->renderLine('exit');
                $this->quit(1);
            } else {
                $this->renderLine();
                $this->renderLine();
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
        $this->addSecret(escapeshellcmd($this->databaseConfiguration['password']));
        $this->addSecret(escapeshellarg(escapeshellcmd($this->databaseConfiguration['password'])));
        $this->addSecret($remotePersistenceConfiguration['user']);
        $this->addSecret($remotePersistenceConfiguration['password']);
        $this->addSecret(escapeshellcmd($remotePersistenceConfiguration['password']));
        $this->addSecret(escapeshellarg(escapeshellcmd($remotePersistenceConfiguration['password'])));

        #######################
        # Check Configuration #
        #######################

        $this->checkConfiguration($remotePersistenceConfiguration);

        ################################################
        # Fallback to default MySQL port if not given. #
        ################################################

        if (!isset($remotePersistenceConfiguration['port'])) {
            $remotePersistenceConfiguration['port'] = $this->dbal->getDefaultPort($remotePersistenceConfiguration['driver']);
        }

        if (!isset($this->databaseConfiguration['port'])) {
            $this->databaseConfiguration['port'] = $this->dbal->getDefaultPort($this->databaseConfiguration['driver']);
        }

        ########################
        # Drop and Recreate DB #
        ########################

        if ($keepDb == false) {
            $this->renderHeadLine('Drop and Recreate DB');

            $emptyLocalDbSql = $this->dbal->flushDbSql($this->databaseConfiguration['driver'], $this->databaseConfiguration['dbname']);

            $this->executeLocalShellCommand(
                'echo %s | %s',
                [
                    escapeshellarg($emptyLocalDbSql),
                    $this->dbal->buildCmd(
                        $this->databaseConfiguration['driver'],
                        $this->databaseConfiguration['host'],
                        (int)$this->databaseConfiguration['port'],
                        $this->databaseConfiguration['user'],
                        $this->databaseConfiguration['password'],
                        $this->databaseConfiguration['dbname']
                    )
                ]
            );
        } else {
            $this->renderHeadLine('Skipped (Drop and Recreate DB)');
        }

        ######################
        #  Transfer Database #
        ######################

        $tableContentToSkip = $clone['database']['excludeTableContent'] ?? [];

        $this->renderHeadLine('Transfer Database');
        $this->executeLocalShellCommand(
            'ssh -p %s %s %s@%s -- %s | %s',
            [
                $port,
                $sshOptions,
                $user,
                $host,
                $this->dbal->buildDataDumpCmd(
                    $remotePersistenceConfiguration['driver'],
                    $remotePersistenceConfiguration['host'],
                    (int)$remotePersistenceConfiguration['port'],
                    $remotePersistenceConfiguration['user'],
                    escapeshellcmd($remotePersistenceConfiguration['password']),
                    $remotePersistenceConfiguration['dbname'],
                    $tableContentToSkip
                ),
                $this->dbal->buildCmd(
                    $this->databaseConfiguration['driver'],
                    $this->databaseConfiguration['host'],
                    (int)$this->databaseConfiguration['port'],
                    $this->databaseConfiguration['user'],
                    $this->databaseConfiguration['password'],
                    $this->databaseConfiguration['dbname']
                )
            ]
        );

        if (count($tableContentToSkip) > 0) {
            $this->executeLocalShellCommand(
                'ssh -p %s %s %s@%s -- %s | %s',
                [
                    $port,
                    $sshOptions,
                    $user,
                    $host,
                    $this->dbal->buildSchemaDumpCmd(
                        $remotePersistenceConfiguration['driver'],
                        $remotePersistenceConfiguration['host'],
                        (int)$remotePersistenceConfiguration['port'],
                        $remotePersistenceConfiguration['user'],
                        escapeshellcmd($remotePersistenceConfiguration['password']),
                        $remotePersistenceConfiguration['dbname'],
                        $tableContentToSkip
                    ),
                    $this->dbal->buildCmd(
                        $this->databaseConfiguration['driver'],
                        $this->databaseConfiguration['host'],
                        (int)$this->databaseConfiguration['port'],
                        $this->databaseConfiguration['user'],
                        $this->databaseConfiguration['password'],
                        $this->databaseConfiguration['dbname']
                    )
                ]
            );
        }

        ##################
        # Transfer Files #
        ##################

        $resourceProxyConfiguration = $this->configurationService->getCurrentConfigurationByPath('resourceProxy');

        if (!$resourceProxyConfiguration) {
            $this->renderHeadLine('Transfer Files');
            $this->executeLocalShellCommand(
                'rsync -e "ssh -p %s %s" -kLr %s@%s:%s/* %s',
                [
                    $port,
                    addslashes($sshOptions),
                    $user,
                    $host,
                    $remoteDataPersistentPath,
                    $localDataPersistentPath
                ]
            );
        } else {
            $this->renderHeadLine('Transfer Files - without Resources because a resourceProxyConfiguration is found');
            $this->executeLocalShellCommand(
                'rsync -e "ssh -p %s %s" --exclude "Resources/*" -kLr %s@%s:%s/* %s',
                [
                    $port,
                    addslashes($sshOptions),
                    $user,
                    $host,
                    $remoteDataPersistentPath,
                    $localDataPersistentPath
                ]
            );
        }

        #########################
        # Transfer Translations #
        #########################

        $this->renderHeadLine('Transfer Translations');

        $remoteDataTranslationsPath = $path . '/Data/Translations';
        $localDataTranslationsPath = FLOW_PATH_ROOT . 'Data/Translations';

        // If the translation directory is available print true - because we didn't get the return value here
        $translationsAvailable = trim(
            $this->executeLocalShellCommand(
                'ssh -p %s %s %s@%s "[ -d %s ] && echo true"',
                [
                    $port,
                    $sshOptions,
                    $user,
                    $host,
                    $remoteDataTranslationsPath]
            )
        );

        if ($translationsAvailable === 'true') {
            $this->executeLocalShellCommand(
                'rsync -e "ssh -p %s %s" -kLr %s@%s:%s/* %s',
                [
                    $port,
                    addslashes($sshOptions),
                    $user,
                    $host,
                    $remoteDataTranslationsPath,
                    $localDataTranslationsPath
                ]
            );
        }

        ################
        # Clear Caches #
        ################

        $this->renderHeadLine('Clear Caches');
        $this->executeLocalFlowCommand('flow:cache:flush');

        ##################
        # Set DB charset #
        ##################
        if ($this->databaseConfiguration['driver'] == 'pdo_mysql' && $remotePersistenceConfiguration['charset'] != 'utf8mb4') {
            $this->renderHeadLine('Set DB charset');
            $this->executeLocalFlowCommand('database:setcharset');
        }

        ##############
        # Migrate DB #
        ##############

        $this->renderHeadLine('Migrate cloned DB');
        $this->executeLocalFlowCommand('doctrine:migrate');

        #####################
        # Publish Resources #
        #####################
        if (!($clone['skipResourcePublishStep'] ?? false)) {
            $this->renderHeadLine('Publish Resources');
            $this->executeLocalFlowCommand('resource:publish');
        }

        ##############
        # Post Clone #
        ##############

        if ($postClone) {
            $this->renderHeadLine('Execute post_clone commands');
            if (is_array($postClone)) {
                foreach ($postClone as $postCloneCommand) {
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

        $this->renderHeadLine('Done');
        $this->renderLine('Successfully cloned in %s seconds', [$duration]);
    }

    /**
     * @param $remotePersistenceConfiguration
     * @param $this ->databaseConfiguration
     * @throws StopCommandException
     */
    protected function checkConfiguration($remotePersistenceConfiguration): void
    {
        $this->renderHeadLine('Check Configuration');
        if (!$this->dbal->driverIsSupported($remotePersistenceConfiguration['driver'])
            && !$this->dbal->driverIsSupported($this->databaseConfiguration['driver'])) {
            $this->renderLine(sprintf('<error>ERROR:</error> Only pdo_pgsql and pdo_mysql drivers are supported! Remote: "%s" Local: "%s" configured.', $remotePersistenceConfiguration['driver'], $this->databaseConfiguration['driver']));
            $this->quit(1);
        }
        if ($remotePersistenceConfiguration['driver'] !== $this->databaseConfiguration['driver']) {
            $this->renderLine('<error>ERROR:</error> Remote and local databases must use the same driver!');
            $this->quit(1);
        }
        if (in_array($remotePersistenceConfiguration['charset'], ['utf8', 'utf8mb4']) && in_array($this->databaseConfiguration['charset'], ['utf8', 'utf8mb4'])) {
            // we accept utf8 and utf8mb4 being similar enough
        } else if ($remotePersistenceConfiguration['charset'] != $this->databaseConfiguration['charset']) {
            $this->renderLine(sprintf('<error>ERROR:</error> Remote and local databases must use the same charset! Remote: "%s", Local: "%s" configured.', $remotePersistenceConfiguration['charset'], $this->databaseConfiguration['charset']));
            $this->quit(1);
        }
        $this->renderLine(' - Configuration seems ok ...');
    }
}
