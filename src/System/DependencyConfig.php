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
 * @copyright SwiftOtter Studios, 10/8/16
 * @package default
 **/

namespace Driver\System;

use DI;
use Driver\Engines\LocalConnectionInterface;
use Driver\Engines\MySql\Sandbox\Connection;
use Driver\Engines\MySql\Sandbox\Export;
use Driver\Engines\MySql\Sandbox\Import;
use Driver\Engines\RemoteConnectionInterface;
use Driver\Pipeline\Environment;
use Driver\Pipeline\Stage;
use Driver\Pipeline\Span;
use Driver\Pipeline\Transport\Factory as TransportFactory;
use Driver\Pipeline\Transport\Primary as TransportPrimary;
use Driver\System\Logs\LoggerInterface;
use Driver\System\Logs\Primary;
use Driver\System\DebugMode;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Tester\ApplicationTester as ConsoleApplicationTester;

class DependencyConfig
{
    private bool $isDebug = false;

    public function __construct(bool $isDebug)
    {
        $this->isDebug = $isDebug;
    }

    public function get()
    {
        $output = [
            LoggerInterface::class => DI\Factory(function() {
                return new Primary();
            }),
            Environment\EnvironmentInterface::class => DI\factory([Environment\Primary::class, 'create']),
            Environment\Factory::class => DI\object()->constructorParameter('type', Environment\Primary::class),
            Stage\StageInterface::class => DI\factory([Stage\Primary::class, 'create']),
            Stage\Factory::class => DI\object()->constructorParameter('type', Stage\Primary::class),
            Span\SpanInterface::class => DI\factory([Span\Primary::class, 'create']),
            Span\Factory::class => DI\object()->constructorParameter('type', Span\Primary::class),
            TransportFactory::class => DI\object()->constructorParameter('type', TransportPrimary::class),
            DebugMode::class => DI\object()->constructorParameter('debugMode', $this->isDebug),
            RemoteConnectionInterface::class => DI\object(
                $this->isDebug ? DebugExternalConnection::class : Connection::class
            ),
            LocalConnectionInterface::class => DI\object(
                \Driver\System\LocalConnectionLoader::class
            )
        ];

        return $output;
    }

    public function getForTests()
    {
        return array_merge(
            $this->get(),
            [ ]
        );
    }
}
