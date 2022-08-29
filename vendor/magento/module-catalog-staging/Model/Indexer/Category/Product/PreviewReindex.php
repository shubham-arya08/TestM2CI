<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CatalogStaging\Model\Indexer\Category\Product;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Indexer\Category\Product\AbstractAction;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Search\Request\Dimension;
use Magento\Framework\Search\Request\IndexScopeResolverInterface as TableResolver;
use Magento\Store\Model\Store;
use Magento\Framework\Exception\NoSuchEntityException;

class PreviewReindex
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var Preview
     */
    private $preview;

    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;
    /**
     * @var TableResolver
     */
    private $tableResolver;

    /**
     * @param ResourceConnection $resourceConnection
     * @param Preview $preview
     * @param CategoryRepositoryInterface $categoryRepository
     * @param TableResolver|null $tableResolver
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        Preview $preview,
        CategoryRepositoryInterface $categoryRepository,
        TableResolver $tableResolver
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->preview = $preview;
        $this->categoryRepository = $categoryRepository;
        $this->tableResolver = $tableResolver;
    }

    /**
     * @param int $rootCategoryId
     * @param int $storeId
     * @throws NoSuchEntityException
     */
    public function reindex(
        int $rootCategoryId,
        int $storeId
    ): void {
        /** @var Category $category */
        $category = $this->categoryRepository->get($rootCategoryId);
        if (!$category) {
            return;
        }

        $productIds = $this->loadProductIds((int)$rootCategoryId);
        $this->preview->execute($rootCategoryId, $productIds);
        $catalogCategoryProductDimension = new Dimension(Store::ENTITY, $storeId);

        $indexTable = $this->tableResolver->resolve(
            AbstractAction::MAIN_INDEX_TABLE,
            [
                $catalogCategoryProductDimension
            ]
        );

        $indexTableTmp = $this->resourceConnection->getTableName($this->preview->getTemporaryTable($storeId));

        $mappedTable = $this->resourceConnection->getMappedTableName($indexTable);
        if ($mappedTable) {
            throw new \LogicException('Table ' . $indexTable . ' already mapped');
        }
        $this->resourceConnection->setMappedTableName($indexTable, $indexTableTmp);
    }

    /**
     * Load category product ids
     *
     * @param int $rootCategoryId
     * @return array
     */
    private function loadProductIds(int $rootCategoryId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()->from(
            $this->resourceConnection->getTableName('catalog_category_product'),
            ['product_id']
        )->where(
            'category_id = ?',
            $rootCategoryId
        );

        return $connection->fetchCol($select);
    }
}
