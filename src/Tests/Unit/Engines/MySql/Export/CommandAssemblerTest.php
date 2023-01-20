<?php

declare(strict_types=1);

namespace Driver\Tests\Unit\Engines\MySql\Export;

use Driver\Engines\ConnectionInterface;
use Driver\Engines\MySql\Export\CommandAssembler;
use Driver\Engines\MySql\Export\TablesProvider;
use Driver\Pipeline\Environment\EnvironmentInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CommandAssemblerTest extends TestCase
{
    private CommandAssembler $commandAssembler;

    /** @var TablesProvider&MockObject */
    private MockObject $tablesProviderMock;

    /** @var ConnectionInterface&MockObject */
    private MockObject $connectionMock;

    /** @var EnvironmentInterface&MockObject */
    private MockObject $environmentMock;

    public function setUp(): void
    {
        $this->tablesProviderMock = $this->getMockBuilder(TablesProvider::class)
            ->disableOriginalConstructor()->getMock();
        $this->connectionMock = $this->getMockBuilder(ConnectionInterface::class)->getMockForAbstractClass();
        $this->connectionMock->expects($this->any())->method('getUser')->willReturn('user');
        $this->connectionMock->expects($this->any())->method('getPassword')->willReturn('password');
        $this->connectionMock->expects($this->any())->method('getHost')->willReturn('host');
        $this->connectionMock->expects($this->any())->method('getDatabase')->willReturn('db');
        $this->environmentMock = $this->getMockBuilder(EnvironmentInterface::class)->getMockForAbstractClass();
        $this->commandAssembler = new CommandAssembler($this->tablesProviderMock);
    }

    public function testReturnsEmptyArrayIfNoTables(): void
    {
        $this->tablesProviderMock->expects($this->any())->method('getAllTables')->willReturn([]);
        $this->assertSame(
            [],
            $this->commandAssembler->execute($this->connectionMock, $this->environmentMock, 'dump.sql')
        );
    }

    public function testReturnsEmptyArrayIfAllTablesAreIgnored(): void
    {
        $this->tablesProviderMock->expects($this->any())->method('getAllTables')->willReturn(['a', 'b']);
        $this->tablesProviderMock->expects($this->any())->method('getIgnoredTables')->willReturn(['a', 'b']);
        $this->assertSame(
            [],
            $this->commandAssembler->execute($this->connectionMock, $this->environmentMock, 'dump.sql')
        );
    }

    public function testReturnsCommandsForNormalTables(): void
    {
        $this->tablesProviderMock->expects($this->any())->method('getAllTables')->willReturn(['a', 'b']);
        $this->tablesProviderMock->expects($this->any())->method('getIgnoredTables')->willReturn([]);
        $this->assertSame(
            [
                "echo '/*!40014 SET @ORG_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;'>> dump.sql",
                'mysqldump --user="user" --password="password" --single-transaction --no-tablespaces --host=host '
                    . 'db a >> dump.sql',
                'mysqldump --user="user" --password="password" --single-transaction --no-tablespaces --host=host '
                    . 'db b >> dump.sql',
                "echo '/*!40014 SET FOREIGN_KEY_CHECKS=@ORG_FOREIGN_KEY_CHECKS */;' >> dump.sql",
                "cat dump.sql | sed -E 's/DEFINER[ ]*=[ ]*`[^`]+`@`[^`]+`/DEFINER=CURRENT_USER/g' | gzip > dump.sql.gz"
            ],
            $this->commandAssembler->execute($this->connectionMock, $this->environmentMock, 'dump.sql')
        );
    }

    public function testReturnsCommandsForEmptyTables(): void
    {
        $this->tablesProviderMock->expects($this->any())->method('getAllTables')->willReturn(['a', 'b']);
        $this->tablesProviderMock->expects($this->any())->method('getIgnoredTables')->willReturn([]);
        $this->tablesProviderMock->expects($this->any())->method('getEmptyTables')->willReturn(['a', 'b']);
        $this->assertSame(
            [
                "echo '/*!40014 SET @ORG_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;'>> dump.sql",
                'mysqldump --user="user" --password="password" --single-transaction --no-tablespaces --host=host '
                    . 'db a b --no-data >> dump.sql',
                "echo '/*!40014 SET FOREIGN_KEY_CHECKS=@ORG_FOREIGN_KEY_CHECKS */;' >> dump.sql",
                "cat dump.sql | sed -E 's/DEFINER[ ]*=[ ]*`[^`]+`@`[^`]+`/DEFINER=CURRENT_USER/g' | gzip > dump.sql.gz"
            ],
            $this->commandAssembler->execute($this->connectionMock, $this->environmentMock, 'dump.sql')
        );
    }

    public function testReturnsCommandsForMixedTables(): void
    {
        $this->tablesProviderMock->expects($this->any())->method('getAllTables')
            ->willReturn(['a', 'b', 'c', 'd', 'e', 'f']);
        $this->tablesProviderMock->expects($this->any())->method('getIgnoredTables')->willReturn(['c', 'f']);
        $this->tablesProviderMock->expects($this->any())->method('getEmptyTables')->willReturn(['b', 'e']);
        $this->assertSame(
            [
                "echo '/*!40014 SET @ORG_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;'>> dump.sql",
                'mysqldump --user="user" --password="password" --single-transaction --no-tablespaces --host=host '
                    . 'db a >> dump.sql',
                'mysqldump --user="user" --password="password" --single-transaction --no-tablespaces --host=host '
                    . 'db d >> dump.sql',
                'mysqldump --user="user" --password="password" --single-transaction --no-tablespaces --host=host '
                    . 'db b e --no-data >> dump.sql',
                "echo '/*!40014 SET FOREIGN_KEY_CHECKS=@ORG_FOREIGN_KEY_CHECKS */;' >> dump.sql",
                "cat dump.sql | sed -E 's/DEFINER[ ]*=[ ]*`[^`]+`@`[^`]+`/DEFINER=CURRENT_USER/g' | gzip > dump.sql.gz"
            ],
            $this->commandAssembler->execute($this->connectionMock, $this->environmentMock, 'dump.sql')
        );
    }
}
