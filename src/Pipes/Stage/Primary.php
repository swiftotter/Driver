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
 * @copyright SwiftOtter Studios, 10/29/16
 * @package default
 **/

namespace Driver\Pipes\Stage;

use Driver\Commands\Factory as CommandFactory;
use Driver\Pipes\Transport\Status;
use Driver\System\YamlFormatter;
use React\Promise\Deferred;
use React\Promise\Promise;

class Primary implements StageInterface
{
    const PIPE_SET_NODE = 'parent';

    private $actions;
    private $commandFactory;

    public function __construct(array $actions, CommandFactory $commandFactory, YamlFormatter $yamlFormatter)
    {
        $this->commandFactory = $commandFactory;
        $this->actions = $yamlFormatter->extractToAssociativeArray($actions);
    }

    public function __invoke(\Driver\Pipes\Transport\TransportInterface $transport, $testMode = false)
    {
        if ($testMode) {
            $this->actions = [];
        }

        $promises = array_map(function($name) use ($transport) {
            $resolver = function(callable $resolve, callable $reject) use ($name, $transport) {
                try {
                    $command = $this->commandFactory->create($name);
                    $resolve($this->verifyTransport($command->go($transport), $name));
                } catch (\Exception $ex) {
                    $reject($ex);
                }
            };
            $canceller = function(callable $resolve, callable $reject){};
            return new Promise($resolver, $canceller);
        }, $this->actions);

        $compiled = \React\Promise\all($promises);


        return $transport->withStatus(new Status(self::PIPE_SET_NODE, 'complete'));
    }

    private function verifyTransport(\Driver\Pipes\Transport\TransportInterface $transport, $lastCommand)
    {
        if (!$transport) {
            throw new \Exception('No Transport object was returned from the last command executed: ' . $lastCommand);
        }

        return $transport;
    }

    private function formatList(array $list)
    {
        $output = array_reduce($list, function($commands, array $item) {
            array_walk($item, function($name, $id) use (&$commands) {
                while (isset($commands[$id])) {
                    $id++;
                }
                $commands[$id] = $name;
            });

            return $commands;
        }, []);

        ksort($output);

        return $output;
    }
}