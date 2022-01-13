<?php
namespace Sitegeist\MagicWand\Command;

/*                                                                        *
 * This script belongs to the Neos Flow package "Sitegeist.MagicWand".    *
 *                                                                        *
 *                                                                        */

use Neos\Flow\Annotations as Flow;
use Neos\Utility\Files as FileUtils;
use Neos\Flow\Core\Bootstrap;
use Sitegeist\MagicWand\DBAL\SimpleDBAL;

/**
 * @Flow\Scope("singleton")
 */
class StashCommandController extends AbstractCommandController
{
    /**
     * @Flow\Inject
     * @var SimpleDBAL
     */
    protected $dbal;

    /**
     * Creates a new stash entry with the given name.
     *
     * @param string $name The name for the new stash entry
     * @return void
     */
    public function createCommand($name)
    {
        $startTimestamp = time();

        #######################
        #     Build Paths     #
        #######################

        $basePath = $this->getStashEntryPath($name);

        $databaseDestination = $basePath . '/database.sql';
        $persistentDestination = $basePath . '/persistent/';

        FileUtils::createDirectoryRecursively($basePath);

        #######################
        # Check Configuration #
        #######################

        $this->checkConfiguration();

        ##################
        # Define Secrets #
        ##################

        $this->addSecret($this->databaseConfiguration['user']);
        $this->addSecret($this->databaseConfiguration['password']);
        $this->addSecret(escapeshellcmd($this->databaseConfiguration['password']));
        $this->addSecret(escapeshellarg(escapeshellcmd($this->databaseConfiguration['password'])));

        ################################################
        # Fallback to default MySQL port if not given. #
        ################################################
        if (!isset($this->databaseConfiguration['port'])) {
            $this->databaseConfiguration['port'] = $this->dbal->getDefaultPort($this->databaseConfiguration['driver']);
        }

        ######################
        #  Write Manifest    #
        ######################
        $this->renderHeadLine('Write Manifest');
        $presetName = $this->configurationService->getCurrentPreset();
        $presetConfiguration = $this->configurationService->getCurrentConfiguration();
        $remoteDumpCommand = $presetConfiguration['dumpCommand'] ?? null;
        $cloneTimestamp = $this->configurationService->getMostRecentCloneTimeStamp();
        $stashTimestamp = time();

        $this->writeStashEntryManifest($name, [
            'preset' => [
                'name' => $presetName,
                'configuration' => $presetConfiguration
            ],
            'cloned_at' => $cloneTimestamp,
            'stashed_at' => $stashTimestamp
        ]);

        ######################
        #  Backup Database   #
        ######################

        $this->renderHeadLine('Backup Database');

        $this->executeLocalShellCommand(
            $this->dbal->buildDataDumpCmd(
                $this->databaseConfiguration['driver'],
                $this->databaseConfiguration['host'],
                (int)$this->databaseConfiguration['port'],
                $this->databaseConfiguration['user'],
                $this->databaseConfiguration['password'],
                $this->databaseConfiguration['dbname'],
                $remoteDumpCommand
            ) . ' > ' . $databaseDestination
        );

        ###############################
        # Backup Persistent Resources #
        ###############################

        $this->renderHeadLine('Backup Persistent Resources');
        $this->executeLocalShellCommand(
            'cp -al %s %s',
            [
                FLOW_PATH_ROOT . 'Data/Persistent',
                $persistentDestination
            ]
        );

        #################
        # Final Message #
        #################

        $endTimestamp = time();
        $duration = $endTimestamp - $startTimestamp;

        $this->renderHeadLine('Done');
        $this->renderLine('Successfuly stashed %s in %s seconds', [$name, $duration]);
    }

    /**
     * Lists all entries
     *
     * @return void
     */
    public function listCommand()
    {
        $head = ['Name', 'Stashed At', 'From Preset', 'Cloned At'];
        $rows = [];
        $basePath = sprintf(FLOW_PATH_ROOT . 'Data/MagicWandStash');

        if (!is_dir($basePath)) {
            $this->renderLine('Stash is empty.');
            $this->quit(1);
        }

        $baseDir = new \DirectoryIterator($basePath);
        $anyEntry = false;

        foreach ($baseDir as $entry) {
            if (!in_array($entry, ['.', '..'])) {
                $stashEntryName = $entry->getFilename();
                $manifest = $this->readStashEntryManifest($stashEntryName) ?: [];

                $rows[] = [
                    $stashEntryName,
                    $manifest['stashed_at'] ? date('Y-m-d H:i:s', $manifest['stashed_at']) : 'N/A',
                    isset($manifest['preset']['name']) ? $manifest['preset']['name'] : 'N/A',
                    $manifest['cloned_at'] ? date('Y-m-d H:i:s', $manifest['cloned_at']) : 'N/A',
                ];

                $anyEntry = true;
            }
        }

        if (!$anyEntry) {
            $this->renderLine('Stash is empty.');
            $this->quit(1);
        }

        $this->output->outputTable($rows, $head);
    }

    /**
     * Clear the whole stash
     *
     * @return void
     */
    public function clearCommand()
    {
        $startTimestamp = time();

        $path = FLOW_PATH_ROOT . 'Data/MagicWandStash';
        FileUtils::removeDirectoryRecursively($path);

        #################
        # Final Message #
        #################

        $endTimestamp = time();
        $duration = $endTimestamp - $startTimestamp;

        $this->renderHeadLine('Done');
        $this->renderLine('Cleanup successful in %s seconds', [$duration]);
    }

    /**
     * Restores stash entries
     *
     * @param string $name The name of the stash entry that will be restored
     * @param boolean $yes confirm execution without further input
     * @param boolean $keepDb skip dropping of database during sync
     * @return void
     */
    public function restoreCommand($name, $yes = false, $keepDb = false)
    {
        $basePath = $this->getStashEntryPath($name);
        $this->restoreStashEntry($basePath, $name, $yes, true, $keepDb);
    }

    /**
     * Remove a named stash entry
     *
     * @param string $name The name of the stash entry that will be removed
     * @param boolean $yes confirm execution without further input
     * @return void
     */
    public function removeCommand($name, $yes = false)
    {
        $directory = FLOW_PATH_ROOT . 'Data/MagicWandStash/' . $name;

        if (!is_dir($directory)) {
            $this->renderLine('<error>%s does not exist</error>', [$name]);
            $this->quit(1);
        }

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

        ###############
        # Start Timer #
        ###############


        $startTimestamp = time();

        FileUtils::removeDirectoryRecursively($directory);

        #################
        # Final Message #
        #################

        $endTimestamp = time();
        $duration = $endTimestamp - $startTimestamp;

        $this->renderHeadLine('Done');
        $this->renderLine('Cleanup removed stash %s in %s seconds', [$name, $duration]);
    }

    /**
     * Actual restore logic
     *
     * @param string $source
     * @param string $name
     * @param boolean $force
     * @param boolean $keepDb
     * @return void
     */
    protected function restoreStashEntry($source, $name, $force = false, $preserve = true, $keepDb = false)
    {
        if (!is_dir($source)) {
            $this->renderLine('<error>%s does not exist</error>', [$name]);
            $this->quit(1);
        }

        #################
        # Are you sure? #
        #################

        if (!$force) {
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

        #######################
        # Check Configuration #
        #######################

        $this->checkConfiguration();

        ##################
        # Define Secrets #
        ##################

        $this->addSecret($this->databaseConfiguration['user']);
        $this->addSecret($this->databaseConfiguration['password']);
        $this->addSecret(escapeshellcmd($this->databaseConfiguration['password']));
        $this->addSecret(escapeshellarg(escapeshellcmd($this->databaseConfiguration['password'])));

        ################################################
        # Fallback to default MySQL port if not given. #
        ################################################
        if (!isset($this->databaseConfiguration['port'])) {
            $this->databaseConfiguration['port'] = $this->dbal->getDefaultPort($this->databaseConfiguration['driver']);
        }

        ########################
        # Drop and Recreate DB #
        ########################

        if ($keepDb == false) {
            $this->renderHeadLine('Drop and Recreate DB');

            $emptyLocalDbSql = $this->dbal->flushDbSql(
                $this->databaseConfiguration['driver'],
                $this->databaseConfiguration['dbname']
            );

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
        #  Restore Database  #
        ######################

        $this->renderHeadLine('Restore Database');

        $this->executeLocalShellCommand(
            $this->dbal->buildCmd(
                $this->databaseConfiguration['driver'],
                $this->databaseConfiguration['host'],
                (int)$this->databaseConfiguration['port'],
                $this->databaseConfiguration['user'],
                $this->databaseConfiguration['password'],
                $this->databaseConfiguration['dbname']
            ) . ' < ' . $source . '/database.sql'
        );

        ################################
        # Restore Persistent Resources #
        ################################

        $this->renderHeadLine('Restore Persistent Resources');
        $this->executeLocalShellCommand(
            'rm -rf %s/* && cp -al %s/* %1$s',
            [
                FLOW_PATH_ROOT . 'Data/Persistent',
                $source . '/persistent'
            ]
        );


        if (!$preserve) {
            FileUtils::removeDirectoryRecursively($source);
        }

        ################
        # Clear Caches #
        ################

        $this->renderHeadLine('Clear Caches');
        $this->executeLocalFlowCommand('flow:cache:flush');


        ##############
        # Migrate DB #
        ##############

        $this->renderHeadLine('Migrate DB');
        $this->executeLocalFlowCommand('doctrine:migrate');

        #####################
        # Publish Resources #
        #####################

        $this->renderHeadLine('Publish Resources');
        $this->executeLocalFlowCommand('resource:publish');

        #############################
        # Restore Clone Information #
        #############################
        if($manifest = $this->readStashEntryManifest($name)) {
            $this->configurationService->setCurrentStashEntry($name, $manifest);
        }

        #################
        # Final Message #
        #################

        $endTimestamp = time();
        $duration = $endTimestamp - $startTimestamp;

        $this->renderHeadLine('Done');
        $this->renderLine('Successfuly restored %s in %s seconds', [$name, $duration]);
    }

    /**
     * @throws \Neos\Flow\Mvc\Exception\StopActionException
     */
    protected function checkConfiguration()
    {
        $this->renderHeadLine('Check Configuration');

        if ($this->databaseConfiguration['driver'] !== 'pdo_mysql') {
            $this->renderLine(' only mysql is supported');
            $this->quit(1);
        }

        $this->renderLine(' - Configuration seems ok ...');
    }

    /**
     * @param string $stashEntryName
     * @return string
     */
    protected function getStashEntryPath(string $stashEntryName): string
    {
        return sprintf(
            FLOW_PATH_ROOT . 'Data/MagicWandStash/%s',
            $stashEntryName
        );
    }

    /**
     * @param string $stashEntryName
     * @return array|null
     */
    protected function readStashEntryManifest(string $stashEntryName): ?array
    {
        $manifestDestination = $this->getStashEntryPath($stashEntryName) . '/manifest.json';

        if (file_exists($manifestDestination)) {
            if ($manifest = json_decode(file_get_contents($manifestDestination), true)) {
                if (is_array($manifest)) {
                    return $manifest;
                }
            }

            $this->outputLine('<error>Manifest file has been corrupted.</error>');
        }

        return null;
    }

    /**
     * @param string $stashEntryName
     * @param array $manifest
     * @return void
     */
    protected function writeStashEntryManifest(string $stashEntryName, array $manifest): void
    {
        $manifestDestination = $this->getStashEntryPath($stashEntryName) . '/manifest.json';

        // Create directory, if not exists
        if (!file_exists(dirname($manifestDestination))) {
            FileUtils::createDirectoryRecursively(dirname($manifestDestination));
        }

        // Write manifest file
        file_put_contents($manifestDestination, json_encode($manifest, JSON_PRETTY_PRINT));

        $this->outputLine('Wrote "%s"', [$manifestDestination]);
    }
}
