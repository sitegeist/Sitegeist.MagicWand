<?php
namespace Sitegeist\MagicWand\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sitegeist.MagicWand".   *
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Cli\CommandController;
use TYPO3\Flow\Utility\Files as FileUtils;
use TYPO3\Flow\Core\Bootstrap;

/**
 * @Flow\Scope("singleton")
 */
class StashCommandController extends CommandController
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

        $databaseDestination = $basePath .'/database.sql';
        $persistentDestination = $basePath .'/persistent/';

        FileUtils::createDirectoryRecursively($basePath);

        #######################
        # Check Configuration #
        #######################

        $this->checkConfiguration();

        ######################
    		#  Backup Database   #
    		######################

        $this->outputHeadLine('2. Backup Database');

        $mysqlDumpCommand = sprintf('mysqldump --add-drop-table --host="%s" --user="%s" --password="%s" %s > %s',
            $this->databaseConfiguration['host'],
            $this->databaseConfiguration['user'],
            $this->databaseConfiguration['password'],
            $this->databaseConfiguration['dbname'],
            $databaseDestination
        );

        $mysqlDumpOutput = shell_exec($mysqlDumpCommand);

        $this->outputLine($mysqlDumpOutput);
        $this->outputLine(' - Database exported ...');

        ###############################
    		# Backup Persistent Resources #
    		###############################

        $this->outputHeadLine('3. Backup Persistent Resources');

        FileUtils::copyDirectoryRecursively(FLOW_PATH_ROOT . 'Data/Persistent', $persistentDestination);

        $this->outputLine(' - Persistent Resources exported ...');

        $this->outputLine();
        $this->outputLine();
        $this->outputLine('<b>Done!</b> Successfuly stashed %s', [$identifier]);
    }

    /**
     * Restores the last anonymous stash entry
     *
     * @param boolean $yes confirm execution without further input
     * @return void
     */
    public function popCommand($yes = false)
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

        $this->restoreStashEntry($entries[0], basename($entries[0]), $yes, false);
    }

    /**
     * Shows the number of anonymous entries in the stash
     *
     * @return void
     */
    public function countCommand() {
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
    public function listCommand() {
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
    public function clearCommand($type = 'all') {
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
     * @return void
     */
    public function restoreCommand($name, $yes = false)
    {
        $basePath = sprintf(FLOW_PATH_ROOT . 'Data/MagicWandStash/named/%s', $name);
        $this->restoreStashEntry($basePath, $name, $yes);
    }

    /**
     * Remove a named stash entry
     *
     * @param string $name The name of the stash entry that will be removed
     * @param boolean $yes confirm execution without further input
     * @return void
     */
    public function removeCommand($name, $yes = false) {
        $directory = FLOW_PATH_ROOT . 'Data/MagicWandStash/named/' . $name;

        if (!is_dir($directory)) {
            $this->outputLine('<error>%s does not exist</error>', [$identifier]);
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
     * @return void
     */
    protected function restoreStashEntry($source, $identifier, $force = false, $preserve = true)
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

        ######################
    		#  Restore Database  #
    		######################

        $this->outputHeadLine('2. Restore Database');

        $mysqlImportCommand = sprintf('mysql  --host="%s" --user="%s" --password="%s" %s < %s',
            $this->databaseConfiguration['host'],
            $this->databaseConfiguration['user'],
            $this->databaseConfiguration['password'],
            $this->databaseConfiguration['dbname'],
            $source . '/database.sql'
        );

        $mysqlImportOutput = shell_exec($mysqlImportCommand);

        $this->outputLine(' - Database imported ...');

        ################################
    		# Restore Persistent Resources #
    		################################

        $this->outputHeadLine('3. Restore Persistent Resources');

        FileUtils::removeDirectoryRecursively(FLOW_PATH_ROOT . 'Data/Persistent');
        FileUtils::copyDirectoryRecursively($source . '/persistent', FLOW_PATH_ROOT . 'Data/Persistent');

        $this->outputLine(' - Persistent Resources imported ...');

        if (!$preserve) {
            FileUtils::removeDirectoryRecursively($source);
        }

        ################
    		# Clear Caches #
    		################

    		$this->outputHeadLine('4. Clear Caches');
    		$flushCachesCommand = 'FLOW_CONTEXT=' . $this->bootstrap->getContext() . ' ./flow flow:cache:flush';
    		$this->outputLine($flushCachesCommand);
    		$flushCachesResult = shell_exec($flushCachesCommand);
    		$this->outputLine($flushCachesResult);

    		##############
    		# Migrate DB #
    		##############

    		$this->outputHeadLine('5. Migrate cloned DB');
    		$migrateDbCommand = 'FLOW_CONTEXT=' . $this->bootstrap->getContext() . ' ./flow doctrine:migrate';
    		$this->outputLine($migrateDbCommand);
    		$migrateDbResult = shell_exec($migrateDbCommand);
    		$this->outputLine($migrateDbResult);

    		#####################
    		# Publish Resources #
    		#####################

    		$this->outputHeadLine('6. Publish Resources');
    		$publishResourcesCommand = 'FLOW_CONTEXT=' . $this->bootstrap->getContext() . ' ./flow resource:publish';
    		$this->outputLine($publishResourcesCommand);
    		$publishResourcesResult = shell_exec($publishResourcesCommand);
    		$this->outputLine($publishResourcesResult);

        $this->outputLine();
        $this->outputLine();
        $this->outputLine('<b>Done!</b> Successfuly restored %s', [$identifier]);
    }

    protected function checkConfiguration()
    {
        $this->outputHeadLine('1. Check Configuration');

        if ($this->databaseConfiguration['driver'] !== 'pdo_mysql') {
            $this->outputLine(' only mysql is supported');
            $this->quit(1);
        }

        $this->outputLine(' - Configuration seems ok ...');
    }

    protected function outputHeadLine($line)
    {
  		$this->outputLine();
  		$this->outputLine($line);
  		$this->outputLine();
  	}
}
