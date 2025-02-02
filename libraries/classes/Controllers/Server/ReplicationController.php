<?php
/**
 * Server replications
 */

declare(strict_types=1);

namespace PhpMyAdmin\Controllers\Server;

use PhpMyAdmin\Controllers\AbstractController;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Http\ServerRequest;
use PhpMyAdmin\ReplicationGui;
use PhpMyAdmin\ReplicationInfo;
use PhpMyAdmin\ResponseRenderer;
use PhpMyAdmin\Template;
use PhpMyAdmin\Url;

use function is_array;

/**
 * Server replications
 */
class ReplicationController extends AbstractController
{
    /** @var ReplicationGui */
    private $replicationGui;

    /** @var DatabaseInterface */
    private $dbi;

    public function __construct(
        ResponseRenderer $response,
        Template $template,
        ReplicationGui $replicationGui,
        DatabaseInterface $dbi
    ) {
        parent::__construct($response, $template);
        $this->replicationGui = $replicationGui;
        $this->dbi = $dbi;
    }

    public function __invoke(ServerRequest $request): void
    {
        $GLOBALS['urlParams'] = $GLOBALS['urlParams'] ?? null;
        $GLOBALS['errorUrl'] = $GLOBALS['errorUrl'] ?? null;

        /** @var bool|null $replClearScr */
        $replClearScr = $request->getParsedBodyParam('repl_clear_scr');
        $replicaConfigure = $request->getParsedBodyParam('replica_configure');
        $primaryConfigure = $request->getParsedBodyParam('primary_configure');

        $GLOBALS['errorUrl'] = Url::getFromRoute('/');

        if ($this->dbi->isSuperUser()) {
            $this->dbi->selectDb('mysql');
        }

        $replicationInfo = new ReplicationInfo($this->dbi);
        /** @var string $primaryConnection */
        $primaryConnection = $request->getParsedBodyParam('primary_connection');
        $replicationInfo->load($primaryConnection);

        $primaryInfo = $replicationInfo->getPrimaryInfo();
        $replicaInfo = $replicationInfo->getReplicaInfo();

        $this->addScriptFiles(['server/privileges.js', 'replication.js', 'vendor/zxcvbn-ts.js']);

        $urlParams = $request->getParsedBodyParam('url_params');
        if (is_array($urlParams)) {
            $GLOBALS['urlParams'] = $urlParams;
        }

        if ($this->dbi->isSuperUser()) {
            /** @var string|null $srReplicaAction */
            $srReplicaAction = $request->getParsedBodyParam('sr_replica_action');
            /** @var string|int $srSkipErrorsCount */
            $srSkipErrorsCount = $request->getParsedBodyParam('sr_skip_errors_count', 1);
            /** @var string|null $srReplicaControlParam */
            $srReplicaControlParam = $request->getParsedBodyParam('sr_replica_control_param');

            $this->replicationGui->handleControlRequest(
                $request->getParsedBodyParam('sr_take_action') !== null,
                $request->getParsedBodyParam('replica_changeprimary') !== null,
                $request->getParsedBodyParam('sr_replica_server_control') !== null,
                $srReplicaAction,
                $request->getParsedBodyParam('sr_replica_skip_error') !== null,
                (int) $srSkipErrorsCount,
                $srReplicaControlParam,
                [
                    'username' => $GLOBALS['dbi']->escapeString($request->getParsedBodyParam('username')),
                    'pma_pw' => $GLOBALS['dbi']->escapeString($request->getParsedBodyParam('pma_pw')),
                    'hostname' => $GLOBALS['dbi']->escapeString($request->getParsedBodyParam('hostname')),
                    'port' => (int) $GLOBALS['dbi']->escapeString($request->getParsedBodyParam('text_port')),
                ]
            );
        }

        $errorMessages = $this->replicationGui->getHtmlForErrorMessage();

        if ($primaryInfo['status']) {
            /** @var string|null $primaryAddUser */
            $primaryAddUser = $request->getParsedBodyParam('primary_add_user');
            /** @var string $username */
            $username = $request->getParsedBodyParam('username');
            /** @var string $hostname */
            $hostname = $request->getParsedBodyParam('hostname');

            $primaryReplicationHtml = $this->replicationGui->getHtmlForPrimaryReplication(
                $primaryConnection,
                $replClearScr,
                $primaryAddUser,
                $username,
                $hostname
            );
        }

        if ($primaryConfigure !== null) {
            $primaryConfigurationHtml = $this->replicationGui->getHtmlForPrimaryConfiguration();
        } else {
            if ($replClearScr === null) {
                $replicaConfigurationHtml = $this->replicationGui->getHtmlForReplicaConfiguration(
                    $primaryConnection,
                    $replicaInfo['status'],
                    $replicationInfo->getReplicaStatus(),
                    $replicaConfigure !== null
                );
            }

            if ($replicaConfigure !== null) {
                $changePrimaryHtml = $this->replicationGui->getHtmlForReplicationChangePrimary('replica_changeprimary');
            }
        }

        $this->render('server/replication/index', [
            'url_params' => $GLOBALS['urlParams'],
            'is_super_user' => $this->dbi->isSuperUser(),
            'error_messages' => $errorMessages,
            'is_primary' => $primaryInfo['status'],
            'primary_configure' => $primaryConfigure,
            'replica_configure' => $replicaConfigure,
            'clear_screen' => $replClearScr,
            'primary_replication_html' => $primaryReplicationHtml ?? '',
            'primary_configuration_html' => $primaryConfigurationHtml ?? '',
            'replica_configuration_html' => $replicaConfigurationHtml ?? '',
            'change_primary_html' => $changePrimaryHtml ?? '',
        ]);
    }
}
