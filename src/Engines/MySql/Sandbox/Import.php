<?php
/**
 * SwiftOtter_Base is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * SwiftOtter_Base is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with SwiftOtter_Base. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Joseph Maxwell
 * @copyright SwiftOtter Studios, 12/3/16
 * @package default
 **/

namespace Driver\Engines\MySql\Sandbox;

use Driver\Commands\CommandInterface;
use Driver\Engines\RemoteConnectionInterface;
use Driver\Pipeline\Environment\EnvironmentInterface;
use Driver\Pipeline\Transport\Status;
use Driver\Pipeline\Transport\TransportInterface;
use Driver\System\LocalConnectionLoader;
use Driver\System\Logs\LoggerInterface;
use Driver\System\DebugMode;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;

class Import extends Command implements CommandInterface
{
    private LocalConnectionLoader $localConnection;
    private RemoteConnectionInterface $remoteConnection;
    private Ssl $ssl;
    private array $properties = [];
    private LoggerInterface $logger;
    private ConsoleOutput $output;
    private DebugMode $debugMode;

    public function __construct(
        LocalConnectionLoader $localConnection,
        Ssl $ssl,
        RemoteConnectionInterface $connection,
        LoggerInterface $logger,
        ConsoleOutput $output,
        DebugMode $debugMode,
        array $properties = []
    ) {
        $this->localConnection = $localConnection;
        $this->remoteConnection = $connection;
        $this->ssl = $ssl;
        $this->properties = $properties;
        $this->logger = $logger;
        $this->output = $output;
        return parent::__construct('mysql-sandbox-import');
    }

    public function go(TransportInterface $transport, EnvironmentInterface $environment)
    {
        $this->remoteConnection->test(function(RemoteConnectionInterface $connection) {
            $connection->authorizeIp();
        });

        $this->output->writeln("<comment>Importing database into RDS. Please wait... It will take some time.</comment>");
        $this->logger->notice("Importing database into RDS");
        $results = system($this->assembleCommand($transport->getData('dump-file')));

        if ($results) {
            throw new \Exception('Import to RDS instance failed: ' . $results);
            $this->output->writeln('<error>Import to RDS instance failed: ' . $results . '</error>');
        } else {
            $this->logger->notice("Import to RDS completed.");
            $this->output->writeln('<info>Import to RDS completed.</info>');
            return $transport->withStatus(new Status('sandbox_init', 'success'));
        }
    }

    public function getProperties()
    {
        return $this->properties;
    }

    public function assembleCommand($path)
    {
        $command = implode(' ', [
            "mysql --user={$this->remoteConnection->getUser()}",
                "--password={$this->remoteConnection->getPassword()}",
                "--host={$this->remoteConnection->getHost()}",
                "--port={$this->remoteConnection->getPort()}",
                $this->remoteConnection->useSsl() ? "--ssl-ca={$this->ssl->getPath()}" : "",
                "{$this->remoteConnection->getDatabase()}",
            "<",
            $path
        ]);

        if (stripos($this->localConnection->getConnection()->getAttribute(\PDO::ATTR_SERVER_VERSION), 'maria') !== false) {
            $command = str_replace('--ssl-mode=VERIFY_CA', '--ssl', $command);
        }

        return $command;
    }
}
