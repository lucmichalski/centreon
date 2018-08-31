<?php

namespace CentreonRemote\Application\Webservice;

use CentreonRemote\Application\Validator\WizardConfigurationRequestValidator;
use CentreonRemote\Domain\Service\ConfigurationWizard\LinkedPollerConfigurationService;
use Centreon\Domain\Entity\Task;
use CentreonRemote\Domain\Service\ConfigurationWizard\PollerConfigurationRequestBridge;
use CentreonRemote\Domain\Service\ConfigurationWizard\ServerConnectionConfigurationService;
use CentreonRemote\Domain\Value\ServerWizardIdentity;

class CentreonConfigurationRemote extends CentreonWebServiceAbstract
{

    /**
     * Name of web service object
     * 
     * @return string
     */
    public static function getName(): string
    {
        return 'centreon_configuration_remote';
    }

    /**
     * @SWG\Post(
     *   path="/centreon/api/internal.php",
     *   operationId="getWaitList",
     *   @SWG\Parameter(
     *       in="query",
     *       name="object",
     *       type="string",
     *       description="the name of the API object class",
     *       required=true,
     *       enum="centreon_configuration_remote",
     *   ),
     *   @SWG\Parameter(
     *       in="query",
     *       name="action",
     *       type="string",
     *       description="the name of the action in the API class",
     *       required=true,
     *       enum="getWaitList",
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="JSON with the IPs inside the waitlist"
     *   )
     * )
     *
     * Get remotes servers waitlist
     * 
     * @return array
     */
    public function postGetWaitList(): array
    {
        $statement = $this->pearDB->query('SELECT ip, version FROM `remote_servers` WHERE `is_connected` = 0');

        return $statement->fetchAll();
    }

    /**
     * @SWG\Post(
     *   path="/centreon/api/internal.php",
     *   operationId="getRemotesList",
     *   @SWG\Parameter(
     *       in="query",
     *       name="object",
     *       type="string",
     *       description="the name of the API object class",
     *       required=true,
     *       enum="centreon_configuration_remote",
     *   ),
     *   @SWG\Parameter(
     *       in="query",
     *       name="action",
     *       type="string",
     *       description="the name of the action in the API class",
     *       required=true,
     *       enum="getRemotesList",
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="JSON with the IPs of connected remotes"
     *   )
     * )
     *
     * Get list with connected remotes
     *
     * @return array
     */
    public function postGetRemotesList(): array
    {
        $statement = $this->pearDB->query('SELECT ip FROM `remote_servers` WHERE `is_connected` = 1');

        return $statement->fetchAll();
    }

    /**
     * @SWG\Post(
     *   path="/centreon/api/internal.php",
     *   operationId="linkCentreonRemoteServer",
     *   @SWG\Parameter(
     *       in="query",
     *       name="object",
     *       type="string",
     *       description="the name of the API object class",
     *       required=true,
     *       enum="centreon_configuration_remote",
     *   ),
     *   @SWG\Parameter(
     *       in="query",
     *       name="action",
     *       type="string",
     *       description="the name of the action in the API class",
     *       required=true,
     *       enum="linkCentreonRemoteServer",
     *   ),
     *   @SWG\Parameter(
     *       in="formData",
     *       name="open_broker_flow",
     *       type="string",
     *       description="if the connection should be made with open broker flow",
     *       required=false,
     *   ),
     *   @SWG\Parameter(
     *       in="formData",
     *       name="manage_broker_configuration",
     *       type="string",
     *       description="if broker configuration of poller should be managed",
     *       required=false,
     *   ),
     *   @SWG\Parameter(
     *       in="formData",
     *       name="server_ip",
     *       type="string",
     *       description="the remote server ip address",
     *       required=true,
     *   ),
     *   @SWG\Parameter(
     *       in="formData",
     *       name="centreon_central_ip",
     *       type="string",
     *       description="the centreon central ip address",
     *       required=true,
     *   ),
     *   @SWG\Parameter(
     *       in="formData",
     *       name="server_name",
     *       type="string",
     *       description="the remote centreon instance name",
     *       required=true,
     *   ),
     *   @SWG\Parameter(
     *       in="formData",
     *       name="db_user",
     *       type="string",
     *       description="database username",
     *       required=false,
     *   ),
     *   @SWG\Parameter(
     *       in="formData",
     *       name="db_password",
     *       type="string",
     *       description="database password",
     *       required=false,
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="JSON"
     *   )
     * )
     *
     * Link centreon remote server
     *
     * @return string
     *
     * @throws \RestBadRequestException
     */
    public function postLinkCentreonRemoteServer()
    {
        $openBrokerFlow = isset($_POST['open_broker_flow']);
        $manageBrokerConfiguration = isset($_POST['manage_broker_configuration']);
        $isRemoteConnection = ServerWizardIdentity::requestConfigurationIsRemote();
        $configurationServiceName = $isRemoteConnection ?
            'centreon_remote.remote_connection_service' :
            'centreon_remote.poller_connection_service';

        WizardConfigurationRequestValidator::validate();

        /** @var $pollerConfigurationService LinkedPollerConfigurationService */
        /** @var $pollerConfigurationBridge PollerConfigurationRequestBridge */
        /** @var $serverConfigurationService ServerConnectionConfigurationService */
        $pollerConfigurationService = $this->getDi()['centreon_remote.poller_config_service'];
        $serverConfigurationService = $this->getDi()[$configurationServiceName];
        $pollerConfigurationBridge = $this->getDi()['centreon_remote.poller_config_bridge'];
        $pollerConfigurationBridge->collectDataFromRequest();

        $serverIP = $_POST['server_ip'];
        $serverName = substr($_POST['server_name'], 0, 40);

        $serverConfigurationService->setCentralIp($_POST['centreon_central_ip']);
        $serverConfigurationService->setServerIp($serverIP);
        $serverConfigurationService->setName($serverName);

        if ($isRemoteConnection) {
            $serverConfigurationService->setDbUser($_POST['db_user']);
            $serverConfigurationService->setDbPassword($_POST['db_password']);
        }

        // Add configuration of the new server in the database
        try {
            $serverID = $serverConfigurationService->insert();
        } catch(\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }

        // If you want to link pollers to a remote
        //todo: update api map and doc
//        if ($pollerConfigurationBridge->hasPollersForUpdating()) {
//            $pollerConfigurationBridge->setServerID($serverID);
//
//            //todo: give this data to LinkedPollerConfigurationService
//            $pollerConfigurationBridge->getLinkedPollersSelectedForUpdate();
//            $pollerConfigurationBridge->getRemoteServerForConfiguration();
//        }

        // Finish server configuration by:
        // - $openBrokerFlow?
        // - $manageBrokerConfiguration?
        // - update informations table set isRemote=yes in the slave server

        if ($isRemoteConnection) {
            $this->addServerToListOfRemotes($serverIP);
            $this->setCentreonInstanceAsCentral();
        }

        //todo: update return based on success/fail
        // $this->createExportTask($serverIP);

        return ['success' => true];
    }

    /**
     * Authorize to access to the action
     *
     * @param string $action The action name
     * @param \CentreonUser $user The current user
     * @param boolean $isInternal If the api is call in internal
     *
     * @return boolean If the user has access to the action
     */
    public function authorize($action, $user, $isInternal = false)
    {
        if (parent::authorize($action, $user, $isInternal)) {
            return true;
        }

        return $user && $user->hasAccessRestApiConfiguration();
    }

    /**
     * Add server ip in table of remote servers
     *
     * @param $serverIP
     */
    private function addServerToListOfRemotes($serverIP)
    {
        $dbAdapter = $this->getDi()['centreon.db-manager']->getAdapter('configuration_db');
        $date = date('Y-m-d H:i:s');

        $sql = 'SELECT * FROM `remote_servers` WHERE `ip` = ?';
        $dbAdapter->query($sql, [$serverIP]);
        $hasIpInTable = (bool) $dbAdapter->count();

        if ($hasIpInTable) {
            $sql = 'UPDATE `remote_servers` SET `is_connected` = ?, `connected_at` = ? WHERE `ip` = ?';
            $data = ['1', $date, $serverIP];
            $dbAdapter->query($sql, $data);
        } else {
            $data = [
                'ip'           => $serverIP,
                'app_key'      => '',
                'version'      => '',
                'is_connected' => '1',
                'created_at'   => $date,
                'connected_at' => $date,
            ];
            $dbAdapter->insert('remote_servers', $data);
        }
    }

    /**
     * Set current centreon instance as central
     */
    private function setCentreonInstanceAsCentral()
    {
        $dbAdapter = $this->getDi()['centreon.db-manager']->getAdapter('configuration_db');

        $sql = "SELECT * FROM `informations` WHERE `key` = 'isCentral'";
        $dbAdapter->query($sql);
        $hasInfoRecord = (bool) $dbAdapter->count();

        if ($hasInfoRecord) {
            $sql = "UPDATE `informations` SET `value` = 'yes' WHERE `key` = 'isCentral'";
            $dbAdapter->query($sql);
        } else {
            $data = [
                'key'   => 'isCentral',
                'value' => 'yes',
            ];
            $dbAdapter->insert('informations', $data);
        }
    }

    /**
     * Create New Task for export
     * @var $toIps array
     * @return bool
     */
    private function createExportTask($toIps)
    {
        $result = $this->getDi()['centreon.taskservice']->addTask(Task::TYPE_EXPORT, array('ips'=>$toIps));

        return $result;
    }
}
