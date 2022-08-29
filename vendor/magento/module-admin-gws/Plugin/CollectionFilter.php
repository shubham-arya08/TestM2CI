<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\AdminGws\Plugin;

use Magento\AdminGws\Model\Role;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Class for filer collection and leave only allowed for current admin entities.
 */
class CollectionFilter
{
    private const FILTERED_FLAG_NAME = 'admin_gws_filtered';

    /**
     * @var Role
     */
    private $role;

    /**
     * @var array
     */
    private $tableColumns;

    /**
     * @param Role $role
     */
    public function __construct(Role $role)
    {
        $this->role = $role;
        $this->tableColumns = [];
    }

    /**
     * Adds allowed websites or stores to query filter.
     *
     * @param AbstractCollection $collection
     * @param bool $printQuery
     * @param bool $logQuery
     * @return array
     * @throws \Zend_Db_Select_Exception
     */
    public function beforeLoadWithFilter(AbstractCollection $collection, $printQuery = false, $logQuery = false)
    {
        $this->filterCollection($collection);

        return [$printQuery, $logQuery];
    }

    /**
     * Adds allowed websites or stores to query filter.
     *
     * @param AbstractCollection $collection
     * @throws \Zend_Db_Select_Exception
     */
    public function beforeGetSelectCountSql(AbstractCollection $collection)
    {
        $this->filterCollection($collection);
    }

    /**
     * Add filter to collection.
     *
     * @param AbstractCollection $collection
     * @throws \Zend_Db_Select_Exception
     */
    private function filterCollection(AbstractCollection $collection)
    {
        if (!$this->role->getIsAll() && !$collection->getFlag(self::FILTERED_FLAG_NAME)) {
            if (method_exists($collection, 'addStoreFilter')) {
                $collection->addStoreFilter($this->role->getStoreIds());
                $collection->setFlag(self::FILTERED_FLAG_NAME, true);
            } elseif (isset($collection->getSelect()->getPart(Select::FROM)['main_table'])) {
                $this->tableBasedFilter($collection);
                $collection->setFlag(self::FILTERED_FLAG_NAME, true);
            }
        }
    }

    /**
     * Filter the collection by setting relevant website or store Ids by looking up collection's insides.
     *
     * @param AbstractCollection $collection
     * @return void
     */
    private function tableBasedFilter(AbstractCollection $collection)
    {
        $mainTable = $collection->getMainTable();
        if (!isset($this->tableColumns[$mainTable])) {
            $describe = $collection->getConnection()->describeTable($mainTable);
            $this->tableColumns[$mainTable] = array_column($describe, 'COLUMN_NAME');
        }
        if (in_array('website_id', $this->tableColumns[$mainTable], true)) {
            $whereCondition = 'main_table.website_id IN (?) OR main_table.website_id IS NULL';
            $collection->getSelect()->where($whereCondition, $this->role->getRelevantWebsiteIds());
        } elseif (in_array('store_website_id', $this->tableColumns[$mainTable], true)) {
            $whereCondition = 'main_table.store_website_id IN (?) OR main_table.store_website_id IS NULL';
            $collection->getSelect()->where(
                $whereCondition,
                $this->role->getRelevantWebsiteIds()
            );
        } elseif (in_array('store_id', $this->tableColumns[$mainTable], true)) {
            $whereCondition = 'main_table.store_id IN (?) OR main_table.store_id IS NULL';
            $collection->getSelect()->where($whereCondition, $this->role->getStoreIds());
        }
    }
}
