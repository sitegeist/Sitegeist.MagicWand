<?php
declare(strict_types=1);

namespace Sitegeist\MagicWand\Helper;

use RenokiCo\PhpK8s\Kinds\K8sDeployment;
use RenokiCo\PhpK8s\Kinds\K8sPod;
use RenokiCo\PhpK8s\KubernetesCluster;
use Sitegeist\MagicWand\DBAL\SimpleDBAL;

class KubernetesHelper {

    const TMP_DUMP_FILE = '/tmp/k8s-dump.sql';

    /**
     * Get Pod from Kubernetes
     *
     * @param string $k8sConfigFilePath
     * @param string $k8sConfigContext
     * @param string $k8sNamespace
     * @param string $k8sPodLabelSelector
     * @return K8sPod
     */
    static public function getPod(
        string $k8sConfigFilePath,
        string $k8sConfigContext,
        string $k8sNamespace,
        string $k8sPodLabelSelector,
    ) : K8sPod
    {

        //Get Cluster from config
        $cluster = KubernetesCluster::fromKubeConfigYamlFile(
            $k8sConfigFilePath,
            $k8sConfigContext
        );

        //Configure Pod-selection
        K8sDeployment::selectPods(function (K8sDeployment $dep) use ($k8sPodLabelSelector)
        {
            $labelSelectors = explode(',', $k8sPodLabelSelector);

            $selectors = [];
            foreach ($labelSelectors as $labelSelector) {
                list($key, $value) = explode('=', $labelSelector);
                $selectors[$key] = $value;
            }
            return $selectors;
        });

        /** @var K8sPod $pod */
        return $cluster->deployment()->whereNamespace($k8sNamespace)->first()->getPods()->first();

    }

    /**
     * @param K8sPod $pod
     * @param string $containerName
     * @param SimpleDBAL $dbal
     * @param array $remotePersistenceConfiguration
     * @param string|null $remoteDumpCommand
     * @param array $tableContentToSkip
     * @return string
     * @throws \RenokiCo\PhpK8s\Exceptions\KubernetesAPIException
     * @throws \RenokiCo\PhpK8s\Exceptions\KubernetesExecException
     */
    static public function downloadDataDump(
        K8sPod $pod,
        string $containerName,
        SimpleDBAL $dbal,
        array $remotePersistenceConfiguration,
        string $remoteDumpCommand = null,
        array $tableContentToSkip
    ) : string
    {
        /** @var array $execResponse */
        $execResponse = $pod->exec([
            '/bin/bash', '-c',
            $dbal->buildDataDumpCmd(
                $remotePersistenceConfiguration['driver'],
                $remotePersistenceConfiguration['host'],
                (int)$remotePersistenceConfiguration['port'],
                $remotePersistenceConfiguration['user'],
                escapeshellcmd($remotePersistenceConfiguration['password']),
                $remotePersistenceConfiguration['dbname'],
                $remoteDumpCommand,
                $tableContentToSkip
            ),
        ], $containerName);

        if (file_exists(self::TMP_DUMP_FILE)) {
            unlink(self::TMP_DUMP_FILE);
        }

        foreach ($execResponse as $res) {
            file_put_contents(self::TMP_DUMP_FILE, $res['output'], FILE_APPEND);
        }

        return self::TMP_DUMP_FILE;
    }

    /**
     * @param K8sPod $pod
     * @param string $containerName
     * @param SimpleDBAL $dbal
     * @param array $remotePersistenceConfiguration
     * @param string|null $remoteDumpCommand
     * @param array $tableContentToSkip
     * @return string
     * @throws \RenokiCo\PhpK8s\Exceptions\KubernetesAPIException
     * @throws \RenokiCo\PhpK8s\Exceptions\KubernetesExecException
     */
    static public function downloadSchemaDump(
        K8sPod $pod,
        string $containerName,
        SimpleDBAL $dbal,
        array $remotePersistenceConfiguration,
        string $remoteDumpCommand = null,
        array $tableContentToSkip
    ) : string
    {
        /** @var array $execResponse */
        $execResponse = $pod->exec([
            '/bin/bash', '-c',
            $dbal->buildDataDumpCmd(
                $remotePersistenceConfiguration['driver'],
                $remotePersistenceConfiguration['host'],
                (int)$remotePersistenceConfiguration['port'],
                $remotePersistenceConfiguration['user'],
                escapeshellcmd($remotePersistenceConfiguration['password']),
                $remotePersistenceConfiguration['dbname'],
                $remoteDumpCommand,
                $tableContentToSkip
            ),
        ], $containerName);

        if (file_exists(self::TMP_DUMP_FILE)) {
            unlink(self::TMP_DUMP_FILE);
        }

        foreach ($execResponse as $res) {
            file_put_contents(self::TMP_DUMP_FILE, $res['output'], FILE_APPEND);
        }

        return self::TMP_DUMP_FILE;
    }
}
