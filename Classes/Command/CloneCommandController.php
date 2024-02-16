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
use RenokiCo\PhpK8s\Kinds\K8sDeployment;
use RenokiCo\PhpK8s\Kinds\K8sPod;
use RenokiCo\PhpK8s\KubernetesCluster;
use Sitegeist\MagicWand\DBAL\SimpleDBAL;
use Sitegeist\MagicWand\Helper\KubernetesHelper;
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

                if (!empty($configuration['k8sConfigFile'])) {

                    if (!isset($configuration['resourceProxy'])) {
                       $this->outputLine('<error>Kubernetes clone does not support Resource-Download. You have to use ResourceProxy instead!</error>');
                       $this->quit(1);
                    }
                    $this->cloneKubernetesHost(
                        $configuration['k8sConfigFile'],
                        $configuration['k8sContextName'],
                        $configuration['k8sNamespace'],
                        $configuration['k8sPodLabelSelector'],
                        $configuration['k8sContainerName'],
                        $configuration['path'],
                        $configuration['context'],
                        $configuration['clone'] ?? null,
                        $configuration['postClone'] ?? null,
                        $yes,
                        $keepDb,
                        $configuration['flowCommand'] ?? null,
                        $configuration['dumpCommand'] ?? null,
                    );
                } else {
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
                        $configuration['dumpCommand'] ?? null,
                        $configuration['sshOptions'] ?? ''
                    );
                }
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
     * @param string|null $remoteFlowCommand the flow command to execute on the remote system
     * @param string|null $remoteDumpCommand the dump command to execute on the remote system
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
        $remoteDumpCommand = null,
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

        $remotePersistenceConfiguration = $this->getConfigurationFromYaml($remotePersistenceConfigurationYaml);

        $remoteDataPersistentPath = $path . '/Data/Persistent';

        #################
        # Are you sure? #
        #################

        if (!$yes) {
            $this->requestConfirmation();
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
            $this->recreateLocalDatabase();
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
                    $remoteDumpCommand,
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
                        $remoteDumpCommand,
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

        $this->clearCaches();
        $this->migrateLocalDb($remotePersistenceConfiguration);
        $this->publishResources();


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

    protected function cloneKubernetesHost(
        $k8sConfigFile,
        $k8sContextName,
        $k8sNamespace,
        $k8sPodLabelSelector,
        $k8sContainerName,
        $path,
        $context = 'Production',
        $clone = null,
        $postClone = null,
        $yes = false,
        $keepDb = false,
        $remoteFlowCommand = null,
        $remoteDumpCommand = null
    ) {

        if ($remoteFlowCommand === null) {
            $remoteFlowCommand = $this->flowCommand;
        }

        $pod = KubernetesHelper::getPod(
            $k8sConfigFile,
            $k8sContextName,
            $k8sNamespace,
            $k8sPodLabelSelector
        );


        //Fetch configuration from Pod
        /** @var array $execResponse */
        $execResponse = $pod->exec([
            '/bin/bash', '-c',
            'cd ' . $path .' && FLOW_CONTEXT=' . $context . ' ' . $remoteFlowCommand . ' configuration:show --type Settings --path Neos.Flow.persistence.backendOptions'
        ], $k8sContainerName);

        $remotePersistenceConfiguration = $this->getConfigurationFromYaml(last($execResponse)['output']);

        //Request confirmation
        if (!$yes) {
            $this->requestConfirmation();
        }

        $startTimestamp = time();

        $this->addSecret($this->databaseConfiguration['user']);
        $this->addSecret($this->databaseConfiguration['password']);
        $this->addSecret(escapeshellcmd($this->databaseConfiguration['password']));
        $this->addSecret(escapeshellarg(escapeshellcmd($this->databaseConfiguration['password'])));
        $this->addSecret($remotePersistenceConfiguration['user']);
        $this->addSecret($remotePersistenceConfiguration['password']);
        $this->addSecret(escapeshellcmd($remotePersistenceConfiguration['password']));
        $this->addSecret(escapeshellarg(escapeshellcmd($remotePersistenceConfiguration['password'])));


        $this->checkConfiguration($remotePersistenceConfiguration);

        if (!isset($remotePersistenceConfiguration['port'])) {
            $remotePersistenceConfiguration['port'] = $this->dbal->getDefaultPort($remotePersistenceConfiguration['driver']);
        }

        if (!isset($this->databaseConfiguration['port'])) {
            $this->databaseConfiguration['port'] = $this->dbal->getDefaultPort($this->databaseConfiguration['driver']);
        }

        //Reset Database
        if ($keepDb == false) {
            $this->recreateLocalDatabase();
        } else {
            $this->renderHeadLine('Skipped (Drop and Recreate DB)');
        }


        ######################
        #  Transfer Database #
        ######################

        $tableContentToSkip = $clone['database']['excludeTableContent'] ?? [];
        $this->renderHeadLine('Transfer Database');

        $dumpFilePath = KubernetesHelper::downloadDataDump(
            $pod,
            $k8sContainerName,
            $this->dbal,
            $remotePersistenceConfiguration,
            $remoteDumpCommand,
            $tableContentToSkip
        );


        $importCmd = $this->dbal->buildCmd(
            $this->databaseConfiguration['driver'],
            $this->databaseConfiguration['host'],
            (int)$this->databaseConfiguration['port'],
            $this->databaseConfiguration['user'],
            $this->databaseConfiguration['password'],
            $this->databaseConfiguration['dbname']
        ) . ' < ' . $dumpFilePath;

        $this->executeLocalShellCommand($importCmd);
        unlink($dumpFilePath);


        if (count($tableContentToSkip) > 0) {

            KubernetesHelper::downloadSchemaDump(
                $pod,
                $k8sContainerName,
                $this->dbal,
                $remotePersistenceConfiguration,
                $remoteDumpCommand,
                $tableContentToSkip
            );

            $importCmd = $this->dbal->buildCmd(
                    $this->databaseConfiguration['driver'],
                    $this->databaseConfiguration['host'],
                    (int)$this->databaseConfiguration['port'],
                    $this->databaseConfiguration['user'],
                    $this->databaseConfiguration['password'],
                    $this->databaseConfiguration['dbname']
                ) . ' < ' . $dumpFilePath;

            $this->executeLocalShellCommand($importCmd);
            unlink($dumpFilePath);
        }

        //Cleanup
        $this->clearCaches();
        $this->migrateLocalDb($remotePersistenceConfiguration);

        if (!($clone['skipResourcePublishStep'] ?? false)) {
            $this->publishResources();
        }


        //Post clone command
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


    /**
     * @param string $configurationYaml
     * @return mixed|void
     * @throws StopCommandException
     */
    protected function getConfigurationFromYaml(string $configurationYaml) {
        if (!empty($configurationYaml)) {
            return Yaml::parse($configurationYaml);
        } else {
            $this->renderLine("<error>The remote configuration for %s@%s could not be read. Please check the configuration and ensure the correct ssh key was added.</error>", [$user, $host]);
            $this->quit(1);
        }
    }

    /**
     * @return void
     * @throws StopCommandException
     */
    protected function requestConfirmation() : void
    {
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

    /**
     * @return void
     */
    protected function recreateLocalDatabase()
    {
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
    }

    /**
     * @return void
     */
    protected function clearCaches() : void
    {
        $this->renderHeadLine('Clear Caches');
        $this->executeLocalFlowCommand('flow:cache:flush');
    }

    /**
     * @param array $remotePersistenceConfiguration
     * @return void
     */
    protected function migrateLocalDb(array $remotePersistenceConfiguration): void
    {
        if ($this->databaseConfiguration['driver'] == 'pdo_mysql' && $remotePersistenceConfiguration['charset'] != 'utf8mb4') {
            $this->renderHeadLine('Set DB charset');
            $this->executeLocalFlowCommand('database:setcharset');
        }

        $this->renderHeadLine('Migrate cloned DB');
        $this->executeLocalFlowCommand('doctrine:migrate');
    }

    /**
     * @return void
     */
    protected function publishResources() : void
    {
        $this->renderHeadLine('Publish Resources');
        $this->executeLocalFlowCommand('resource:publish');
    }
}
