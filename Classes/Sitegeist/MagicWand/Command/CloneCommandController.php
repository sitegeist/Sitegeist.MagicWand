<?php
namespace Sitegeist\MagicWand\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sitegeist.MagicWand".   *
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Configuration\ConfigurationManager;
use TYPO3\Flow\Utility\Arrays;

/**
 * @Flow\Scope("singleton")
 */
class CloneCommandController extends \TYPO3\Flow\Cli\CommandController {

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
	 * Clone a flow setup as specified in Settings.yaml (Sitegeist.MagicWand.clonePresets ...)
	 *
	 * @param string $presetName
	 */
	public function presetCommand($presetName) {
		if ($this->clonePresets && array_key_exists($presetName, $this->clonePresets)) {
			$this->outputLine('Clone by preset ' . $presetName);
			$this->remoteHostCommand(
				$this->clonePresets[$presetName]['host'],
				$this->clonePresets[$presetName]['user'],
				$this->clonePresets[$presetName]['path'],
				$this->clonePresets[$presetName]['port'],
				$this->clonePresets[$presetName]['context']
			);
		} else {
			$this->outputLine('The preset ' . $presetName . ' was not found!');
			$this->quit(1);
		}
	}

	/**
	 * Clone a Flow Setup via detailed hostname
	 *
	 * @param string $host
	 * @param string $user
	 * @param string $path
	 * @param string $port
	 * @param string $context
	 */
	public function remoteHostCommand($host, $user, $path, $port=22, $context='Production') {

		$this->outputLine(' - Host: ' . $host);
		$this->outputLine(' - User: ' . $user);
		$this->outputLine(' - Port: ' . $port);
		$this->outputLine(' - Path: ' . $path);
		$this->outputLine(' - Context: ' . $context);

		$this->outputLine( "Are you sure you want to do this?  Type 'yes' to continue: ");
		$handle = fopen ("php://stdin","r");
		$line = fgets($handle);
		if(trim($line) != 'yes'){
			$this->outputLine('exit');
			$this->quit(1);
		} else {
			$this->outputLine();
			$this->outputLine();
		}

		$localSettings = $this->configurationManager->getConfiguration('Settings');

		// local configuration
		$localPersistenceConfiguration = Arrays::getValueByPath($localSettings, 'TYPO3.Flow.persistence.backendOptions');
		$localDataPersistentPath = FLOW_PATH_ROOT . '/Data/Persistent';
		$this->outputHeadLine('LocalSettings:');
		$this->outputLine(' - PersistentDataPath: ' . $localDataPersistentPath);
		foreach ($localPersistenceConfiguration as $key => $value){
			$this->outputLine(' - ' . $key . ': ' . $value);
		}

		// remote configuration
		$fetchRemotePersistenceConfigurationViaSSH = 'ssh -p ' . $port . ' ' . $user . '@'. $host . ' "cd ' . $path . '; FLOW_CONTEXT=' . $context . ' ./flow configuration:show --type Settings --path TYPO3.Flow.persistence.backendOptions;"';
		$this->outputHeadLine($fetchRemotePersistenceConfigurationViaSSH);
		$remotePersistenceConfigurationYaml =  shell_exec($fetchRemotePersistenceConfigurationViaSSH);
		if ($remotePersistenceConfigurationYaml){
			$remotePersistenceConfiguration = \Symfony\Component\Yaml\Yaml::parse($remotePersistenceConfigurationYaml);
		}

		$remoteDataPersistentPath = $path . '/Data/Persistent';
		$this->outputHeadLine('RemoteSettings:');
		$this->outputLine(' - PersistentDataPath: ' . $remoteDataPersistentPath);
		foreach ($remotePersistenceConfiguration as $key => $value){
			$this->outputLine(' - ' . $key . ': ' . $value);
		}

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

		$this->outputHeadLine('2. Clear local database');

		$mysqli = new \mysqli($localPersistenceConfiguration['host'], $localPersistenceConfiguration['user'], $localPersistenceConfiguration['password'], $localPersistenceConfiguration['dbname']);
		if ($mysqli->connect_errno) {
			$this->outputLine( "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
			$this->quit(1);
		}
		$tableNameQuery = $mysqli->query("SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE()");
		for ($rowNumber = $tableNameQuery->num_rows - 1; $rowNumber >= 0; $rowNumber--) {
			$tableNameQuery->data_seek($rowNumber);
			$tableNameRow = $tableNameQuery->fetch_assoc();
			$tableName = $tableNameRow['name'];
			$mysqli->query('SET foreign_key_checks = 0');
			$mysqli->query('DROP TABLE '. $tableName);
			$this->outputLine( ' -  delete table ' . $tableName);
		}

		$this->outputHeadLine('3. Transfer database Contents');
		$transferDatabaseViaSSH = 'ssh -p ' . $port . ' ' . $user . '@'. $host . ' "mysqldump --host=' . $remotePersistenceConfiguration['host'] . ' --user=' . $remotePersistenceConfiguration['user'] . ' --password=' . $remotePersistenceConfiguration['password'] . ' ' . $remotePersistenceConfiguration['dbname'] . '" | mysql --host=' . $localPersistenceConfiguration['host'] . ' --user=' . $localPersistenceConfiguration['user'] . ' --password=' . $localPersistenceConfiguration['password'] . ' ' . $localPersistenceConfiguration['dbname'];
		$this->outputLine($transferDatabaseViaSSH);
		$databaseTransferResult = shell_exec($transferDatabaseViaSSH);
		$this->outputLine($databaseTransferResult);

		$this->outputHeadLine('4. Rsync Data/Persistent folders');
		// $transferDatabaseViaSSH = 'ssh -p ' . $port . ' ' . $user . '@'. $host . ' "mysqldump --host=' . $remotePersistenceConfiguration['host'] . ' --user=' . $remotePersistenceConfiguration['user'] . ' --password=' . $remotePersistenceConfiguration['password'] . ' ' . $remotePersistenceConfiguration['dbname'] . '" | mysql --host=' . $localPersistenceConfiguration['host'] . ' --user=' . $localPersistenceConfiguration['user'] . ' --password=' . $localPersistenceConfiguration['password'] . ' ' . $localPersistenceConfiguration['dbname'];
		// $databaseTransferResult = shell_exec($transferDatabaseViaSSH);


		$this->outputHeadLine('5. clear caches');

	}

	protected function outputHeadLine($line) {
		$this->outputLine();
		$this->outputLine($line);
		$this->outputLine();
	}

}