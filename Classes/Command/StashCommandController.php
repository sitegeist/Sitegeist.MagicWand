<?php
namespace Sitegeist\MagicWand\Command;

/*                                                                        *
 * This script belongs to the Neos Flow package "Sitegeist.MagicWand".    *
 *                                                                        *
 *                                                                        */

use Neos\Flow\Annotations as Flow;
use Neos\Utility\Files as FileUtils;
use Neos\Flow\Core\Bootstrap;

/**
 * @Flow\Scope("singleton")
 */
class StashCommandController extends AbstractCommandController
{



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

        $basePath = sprintf(
            FLOW_PATH_ROOT . 'Data/MagicWandStash/%s',
            $name
        );

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

        ######################
        #  Backup Database   #
        ######################

        $this->renderHeadLine('Backup Database');
        $this->executeLocalShellCommand(
            'mysqldump --single-transaction --add-drop-table --host="%s" --user="%s" --password="%s" %s > %s',
            [
                $this->databaseConfiguration['host'],
                $this->databaseConfiguration['user'],
                $this->databaseConfiguration['password'],
                $this->databaseConfiguration['dbname'],
                $databaseDestination
            ]
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
        $basePath = sprintf(FLOW_PATH_ROOT . 'Data/MagicWandStash');

        if (!is_dir($basePath)) {
            $this->renderLine('Stash is empty.');
            $this->quit(1);
        }

        $baseDir = new \DirectoryIterator($basePath);
        $anyEntry = false;

        foreach ($baseDir as $entry) {
            if (!in_array($entry, ['.', '..'])) {
                $this->renderLine(' • %s', [$entry->getFilename()]);
                $anyEntry = true;
            }
        }

        if (!$anyEntry) {
            $this->renderLine('Stash is empty.');
            $this->quit(1);
        }
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
        $basePath = sprintf(FLOW_PATH_ROOT . 'Data/MagicWandStash/%s', $name);
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

        ########################
        # Drop and Recreate DB #
        ########################

        if ($keepDb == false) {
            $this->renderHeadLine('Drop and Recreate DB');

            $emptyLocalDbSql = 'DROP DATABASE `'
                . $this->databaseConfiguration['dbname']
                . '`; CREATE DATABASE `'
                . $this->databaseConfiguration['dbname']
                . '` collate utf8_unicode_ci;';
            
            $this->executeLocalShellCommand(
                'echo %s | mysql --host=%s --user=%s --password=%s',
                [
                    escapeshellarg($emptyLocalDbSql),
                    $this->databaseConfiguration['host'],
                    $this->databaseConfiguration['user'],
                    $this->databaseConfiguration['password']
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
            'mysql  --host="%s" --user="%s" --password="%s" %s < %s',
            [
                $this->databaseConfiguration['host'],
                $this->databaseConfiguration['user'],
                $this->databaseConfiguration['password'],
                $this->databaseConfiguration['dbname'],
                $source . '/database.sql'
            ]
        );

        ################################
        # Restore Persistent Resources #
        ################################

        $this->renderHeadLine('Restore Persistent Resources');
        $this->executeLocalShellCommand(
            'rm -rf %s && cp -al %s %1$s',
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
}
