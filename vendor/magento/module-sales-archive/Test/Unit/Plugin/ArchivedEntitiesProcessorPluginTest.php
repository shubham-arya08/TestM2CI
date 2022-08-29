<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SalesArchive\Test\Unit\Plugin;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Sales\Model\ResourceModel\Provider\Query\IdListBuilder;
use Magento\Sales\Model\ResourceModel\Provider\UpdatedIdListProvider;
use Magento\SalesArchive\Model\ResourceModel\Archive\TableMapper;
use Magento\SalesArchive\Plugin\ArchivedEntitiesProcessorPlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ArchivedEntitiesProcessorPluginTest extends TestCase
{
    /**
     * @var ArchivedEntitiesProcessorPlugin
     */
    private $plugin;

    /**
     * @var MockObject
     */
    private $resourceConnectionMock;
    /**
     * @var MockObject
     */
    private $tableMapperMock;

    protected function setUp(): void
    {
        $this->resourceConnectionMock = $this->getMockBuilder(ResourceConnection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->tableMapperMock = $this->getMockBuilder(TableMapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->plugin = new ArchivedEntitiesProcessorPlugin(
            $this->resourceConnectionMock,
            $this->tableMapperMock
        );
    }

    public function testAfterGetIds()
    {
        $mainTableName = 'sales_order';
        $gridTableName = 'sales_order_grid';
        $archiveGridTableName = 'sales_order_archive_grid';
        $this->tableMapperMock
            ->expects($this::once())
            ->method('getArchiveEntityTableBySourceTable')
            ->willReturn($archiveGridTableName);
        $idListBuilder = $this->getMockBuilder(IdListBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $idListBuilder->expects($this::once())
            ->method('addAdditionalGridTable')
            ->with($archiveGridTableName);

        $this->assertEquals(
            [$mainTableName, $gridTableName],
            $this->plugin->beforeBuild($idListBuilder, $mainTableName, $gridTableName)
        );
    }
}
