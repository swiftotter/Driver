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
 * @copyright SwiftOtter Studios, 12/17/16
 * @package default
 **/

namespace Driver\Engines\MySql;

use Driver\Commands\CommandInterface;
use Driver\Engines\MySql\Sandbox\Connection as SandboxConnection;
use Driver\Engines\MySql\Sandbox\Utilities;
use Driver\Pipeline\Environment\EnvironmentInterface;
use Driver\Pipeline\Transport\Status;
use Driver\Pipeline\Transport\TransportInterface;
use Driver\System\Configuration;
use Driver\System\Logs\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;

class Transformation extends Command implements CommandInterface
{
    /** @var Configuration */
    private $configuration;

    /** @var array */
    private $properties;

    /** @var SandboxConnection */
    private $sandbox;

    /** @var LoggerInterface */
    private $logger;

    /** @var ConsoleOutput */
    private $output;

    public function __construct(
        Configuration $configuration,
        SandboxConnection $sandbox,
        Utilities $utilities,
        LoggerInterface $logger,
        ConsoleOutput $output,
        array $properties = []
    ) {
        $this->configuration = $configuration;
        $this->properties = $properties;
        $this->sandbox = $sandbox;
        $this->logger = $logger;
        $this->output = $output;

        parent::__construct('mysql-transformation');
    }

    public function go(TransportInterface $transport, EnvironmentInterface $environment)
    {
        $this->applyTransformationsTo($this->sandbox->getConnection(), $environment->getTransformations());

        return $transport->withStatus(new Status('mysql-transformation', 'success'));
    }

    public function getProperties()
    {
        return $this->properties;
    }

    private function applyTransformationsTo(\PDO $connection, $transformations)
    {
        array_walk($transformations, function ($query) use ($connection) {
            try {
                $this->logger->info("Attempting: " . $query);
                $this->output->writeln("<comment> Attempting: " . $query . '</comment>');
                $connection->beginTransaction();
                $connection->query($query);
                $connection->commit();

                $this->logger->info("Successfully executed: " . $query);
                $this->output->writeln("<info>Successfully executed: " . $query . '</info>');
            } catch (\Exception $ex) {
                $connection->rollBack();
                $this->logger->error("Query transformation failed: " . $query, [
                    $ex->getMessage(),
                    $ex->getTraceAsString()
                ]);
                $this->output->writeln("<error>Query transformation failed: " . $query, [
                    $ex->getMessage(),
                    $ex->getTraceAsString()
                ] . '</error>');
            }
        });
    }
}
