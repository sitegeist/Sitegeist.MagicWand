<?php
namespace Sitegeist\MagicWand\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sitegeist.MagicWand".   *
 *                                                                        *
 *                                                                        */

use Flowpack\Behat\Tests\Behat\FlowContext;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Configuration\ConfigurationManager;
use TYPO3\Flow\Utility\Arrays;
use TYPO3\Flow\Core\Bootstrap;

/**
 * @Flow\Scope("singleton")
 */
class CloneCommandController extends \TYPO3\Flow\Cli\CommandController {

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
	public function listCommand() {
		if ($this->clonePresets) {
			foreach ($this->clonePresets as $presetName => $presetConfiguration) {
				$this->outputHeadLine($presetName);
				foreach ($presetConfiguration as $key => $value){
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
	 */
	public function presetCommand($presetName, $yes = FALSE) {
		if ($this->clonePresets && array_key_exists($presetName, $this->clonePresets)) {
			$this->outputLine('Clone by preset ' . $presetName);
			$this->remoteHostCommand(
				$this->clonePresets[$presetName]['host'],
				$this->clonePresets[$presetName]['user'],
				$this->clonePresets[$presetName]['port'],
				$this->clonePresets[$presetName]['path'],
				$this->clonePresets[$presetName]['context'],
				$yes
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
	 */
	public function remoteHostCommand($host, $user, $port, $path, $context='Production', $yes=FALSE) {
		// read local configuration
		$localPersistenceConfiguration = $this->configurationManager->getConfiguration('Settings', 'TYPO3.Flow.persistence.backendOptions');
		$localDataPersistentPath = FLOW_PATH_ROOT . 'Data/Persistent';

		// read remote configuration
		$fetchRemotePersistenceConfigurationViaSSH = 'ssh -p ' . $port . ' ' . $user . '@'. $host . ' "cd ' . $path . '; FLOW_CONTEXT=' . $context . ' ./flow configuration:show --type Settings --path TYPO3.Flow.persistence.backendOptions;"';
		$this->outputHeadLine($fetchRemotePersistenceConfigurationViaSSH);
		$remotePersistenceConfigurationYaml =  shell_exec($fetchRemotePersistenceConfigurationViaSSH);
		if ($remotePersistenceConfigurationYaml){
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

		$this->outputHeadLine('1. Check Configuration');
		if ($remotePersistenceConfiguration['driver'] != 'pdo_mysql' && $localPersistenceConfiguration['driver'] != 'pdo_mysql' ) {
			$this->outputLine(' only mysql is supported');
			$this->quit(1);
		}
		if ($remotePersistenceConfiguration['charset'] != $localPersistenceConfiguration['charset']) {
			$this->outputLine(' the databases have to use the same charset');
			$this->quit(1);
		}
		$this->outputLine(' - Configuration seems ok ...');

		######################
		#  Transfer Database #
		######################

		$this->outputHeadLine('2. Transfer Database');
		$transferDatabaseCommand = 'ssh -p ' . $port . ' ' . $user . '@'. $host . ' "mysqldump --add-drop-table --host=' . $remotePersistenceConfiguration['host'] . ' --user=' . $remotePersistenceConfiguration['user'] . ' --password=' . $remotePersistenceConfiguration['password'] . ' ' . $remotePersistenceConfiguration['dbname'] . '" | mysql --host=' . $localPersistenceConfiguration['host'] . ' --user=' . $localPersistenceConfiguration['user'] . ' --password=' . $localPersistenceConfiguration['password'] . ' ' . $localPersistenceConfiguration['dbname'];
		$this->outputLine($transferDatabaseCommand);
		$databaseTransferResult = shell_exec($transferDatabaseCommand);
		$this->outputLine($databaseTransferResult);

		##################
		# Transfer Files #
		##################

		$this->outputHeadLine('3. Transfer Files');
		$transferFilesCommand = 'rsync -e "ssh -p ' . $port . '" -kLr ' . $user . '@'. $host . ':' . $remoteDataPersistentPath . '/* ' . $localDataPersistentPath;
		$this->outputLine($transferFilesCommand);
		$transferFilesResult = shell_exec($transferFilesCommand);
		$this->outputLine($transferFilesResult);

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
	}

	protected function outputHeadLine($line) {
		$this->outputLine();
		$this->outputLine($line);
		$this->outputLine();
	}

}