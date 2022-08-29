<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SalesArchive\Plugin;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\ResourceModel\Provider\Query\IdListBuilder;
use Magento\SalesArchive\Model\ResourceModel\Archive\TableMapper;

/**
 * Plugin is needed as the fix to problem when archived orders
 * are restored in Orders Grid because mechanism that updates
 * orders in the grid does not take into account archived data in the DB
 */
class ArchivedEntitiesProcessorPlugin
{

    /** @var TableMapper */

    private $tableMapper;

    /**
     * @deprecated
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * ArchivedOrdersProcessorPlugin constructor.
     * @param ResourceConnection $resourceConnection
     * @param TableMapper $tableMapper
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        TableMapper $tableMapper
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->tableMapper = $tableMapper;
    }

    /**
     * Gets ids query builder and add archive table to lookup table to exclude orders that is in archive from results.
     *
     * @param IdListBuilder $idListBuilder
     * @param string $mainTableName
     * @param string $gridTableName
     * @return array
     */
    public function beforeBuild(
        IdListBuilder $idListBuilder,
        string $mainTableName,
        string $gridTableName
    ) : array {
        $archiveTable = $this->tableMapper->getArchiveEntityTableBySourceTable($gridTableName);
        if ($archiveTable !== null) {
            $idListBuilder->addAdditionalGridTable($archiveTable);
        }

        return [$mainTableName, $gridTableName];
    }
}
