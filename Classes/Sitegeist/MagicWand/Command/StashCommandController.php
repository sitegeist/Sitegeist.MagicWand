<?php
namespace Sitegeist\MagicWand\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sitegeist.MagicWand".   *
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Utility\Files as FileUtils;
use TYPO3\Flow\Core\Bootstrap;

/**
 * @Flow\Scope("singleton")
 */
class StashCommandController extends AbstractCommandController
{



    /**
     * Creates a new stash entry.
     *
     * If you provide this command with a name, you can reference the new entry via stash:restore. Otherwise the new
     * entry can be restored with stash:pop, while multiple entries are handled LIFO-wise.
     *
     * @param string $name The name for the new stash entry
     * @return void
     */
    public function pushCommand($name = '')
    {
        #######################
        #     Build Paths     #
        #######################

        $identifier = $name ? $name : md5(time());

        $basePath = sprintf(FLOW_PATH_ROOT . 'Data/MagicWandStash/%s/%s',
            $name ? 'named' : 'anonymous',
            $identifier
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

        $this->outputHeadLine('Backup Database');
        $this->executeLocalShellCommand(
            'mysqldump --add-drop-table --host="%s" --user="%s" --password="%s" %s > %s',
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

        $this->outputHeadLine('Backup Persistent Resources');
        $this->executeLocalShellCommand(
            'cp -al %s %s',
            [
                FLOW_PATH_ROOT . 'Data/Persistent',
                $persistentDestination
            ]
        );

        $this->outputLine('<b>Done!</b> Successfuly stashed %s', [$identifier]);
    }

    /**
     * Restores the last anonymous stash entry
     *
     * @param boolean $yes confirm execution without further input
     * @param boolean $keepDb skip dropping of database during sync
     * @return void
     */
    public function popCommand($yes = false, $keepDb = false)
    {
        $basePath = sprintf(FLOW_PATH_ROOT . 'Data/MagicWandStash/anonymous');

        if (!is_dir($basePath)) {
            $this->outputLine('Stash is empty.');
            $this->quit(1);
        }

        $entries = [];
        $baseDir = new \DirectoryIterator($basePath);

        foreach ($baseDir as $entry) {
            if (!in_array($entry, ['.', '..'])) {
                $entries[] = $entry->getPathname();
            }
        }

        uasort($entries, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $this->restoreStashEntry($entries[0], basename($entries[0]), $yes, false, $keepDb);
    }

    /**
     * Shows the number of anonymous entries in the stash
     *
     * @return void
     */
    public function countCommand()
    {
        $basePath = sprintf(FLOW_PATH_ROOT . 'Data/MagicWandStash/anonymous');

        if (!is_dir($basePath)) {
            $this->outputLine('Stash is empty.');
            $this->quit(1);
        }

        $count = 0;
        $baseDir = new \DirectoryIterator($basePath);

        foreach ($baseDir as $entry) {
            if (!in_array($entry, ['.', '..'])) {
                $count++;
            }
        }

        $this->outputLine('<b>%d</b> anonymous %s.', [$count, $count === 1 ? 'entry' : 'entries']);
    }

    /**
     * Lists all named stash entries
     *
     * @return void
     */
    public function listCommand()
    {
        $basePath = sprintf(FLOW_PATH_ROOT . 'Data/MagicWandStash/named');

        if (!is_dir($basePath)) {
            $this->outputLine('Stash is empty.');
            $this->quit(1);
        }

        $baseDir = new \DirectoryIterator($basePath);
        $anyEntry = false;

        foreach ($baseDir as $entry) {
            if (!in_array($entry, ['.', '..'])) {
                $this->outputLine(' • %s', [$entry->getFilename()]);
                $anyEntry = true;
            }
        }

        if (!$anyEntry) {
            $this->outputLine('Stash is empty.');
            $this->quit(1);
        }
    }

    /**
     * Clear the stash
     *
     * If provided with the optional parameter $type, this command will remove only anonymous entries ($type=anonymous),
     * named entries ($type=named), or by default all (or $type=all)
     *
     * @param string $type
     * @return void
     */
    public function clearCommand($type = 'all')
    {
        switch ($type) {
            case 'all':
                $path = FLOW_PATH_ROOT . 'Data/MagicWandStash';
                break;

            case 'anonymous':
            case 'named':
                $path = FLOW_PATH_ROOT . 'Data/MagicWandStash/' . $type;
                break;

            default:
                $this->outputLine('<error>You have to provide a correct type (all|anonymous|named)</error>');
                $this->quit(1);

        }

        FileUtils::removeDirectoryRecursively($path);

        $this->outputLine('<b>Done!</b> Cleanup successful.');
    }

    /**
     * Restores named stash entries
     *
     * @param string $name The name of the stash entry that will be restored
     * @param boolean $yes confirm execution without further input
     * @param boolean $keepDb skip dropping of database during sync
     * @return void
     */
    public function restoreCommand($name, $yes = false, $keepDb = false)
    {
        $basePath = sprintf(FLOW_PATH_ROOT . 'Data/MagicWandStash/named/%s', $name);
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
        $directory = FLOW_PATH_ROOT . 'Data/MagicWandStash/named/' . $name;

        if (!is_dir($directory)) {
            $this->outputLine('<error>%s does not exist</error>', [$name]);
            $this->quit(1);
        }

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

        FileUtils::removeDirectoryRecursively($directory);

        $this->outputLine('<b>Done!</b> Successfuly removed %s', [$name]);
    }

    /**
     * Actual resore logic
     *
     * @param string $source
     * @param string $identifier
     * @param boolean $force
     * @param boolean $keepDb
     * @return void
     */
    protected function restoreStashEntry($source, $identifier, $force = false, $preserve = true, $keepDb = false)
    {
        if (!is_dir($source)) {
            $this->outputLine('<error>%s does not exist</error>', [$identifier]);
            $this->quit(1);
        }

        #################
        # Are you sure? #
        #################

        if (!$force) {
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
            $this->outputHeadLine('Drop and Recreate DB');
            $emptyLocalDbSql = 'DROP DATABASE ' . $this->databaseConfiguration['dbname'] . '; CREATE DATABASE ' . $this->databaseConfiguration['dbname'] . ' collate utf8_unicode_ci;';
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
            $this->outputHeadLine('Skipped (Drop and Recreate DB)');
        }

        ######################
        #  Restore Database  #
        ######################

        $this->outputHeadLine('Restore Database');
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

        $this->outputHeadLine('Restore Persistent Resources');
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

        $this->outputHeadLine('Clear Caches');
        $this->executeLocalFlowCommand('flow:cache:flush');


        ##############
        # Migrate DB #
        ##############

        $this->outputHeadLine('Migrate DB');
        $this->executeLocalFlowCommand('doctrine:migrate');

        #####################
        # Publish Resources #
        #####################

        $this->outputHeadLine('Publish Resources');
        $this->executeLocalFlowCommand('resource:publish');

        $this->outputLine('<b>Done!</b> Successfuly restored %s', [$identifier]);
    }

    /**
     * @throws \TYPO3\Flow\Mvc\Exception\StopActionException
     */
    protected function checkConfiguration()
    {
        $this->outputHeadLine('Check Configuration');

        if ($this->databaseConfiguration['driver'] !== 'pdo_mysql') {
            $this->outputLine(' only mysql is supported');
            $this->quit(1);
        }

        $this->outputLine(' - Configuration seems ok ...');
    }

}
