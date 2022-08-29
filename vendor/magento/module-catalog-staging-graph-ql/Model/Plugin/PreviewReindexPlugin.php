<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CatalogStagingGraphQl\Model\Plugin;

use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\CategoryTree;
use Magento\Staging\Model\VersionManager;
use Magento\CatalogStaging\Model\Indexer\Category\Product\PreviewReindex;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;
use \Exception;

class PreviewReindexPlugin
{
    /**
     * @var VersionManager
     */
    private $versionManager;

    /**
     * @var PreviewReindex
     */
    private $previewReindex;

    /**
     * @param VersionManager $versionManager
     * @param PreviewReindex $previewReindex
     */
    public function __construct(
        VersionManager $versionManager,
        PreviewReindex $previewReindex
    ) {
        $this->versionManager = $versionManager;
        $this->previewReindex = $previewReindex;
    }

    /**
     * @param CategoryTree $subject
     * @param ResolveInfo $resolveInfo
     * @param int $rootCategoryId
     * @param int $storeId
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeGetTree(
        CategoryTree $subject,
        ResolveInfo $resolveInfo,
        int $rootCategoryId,
        int $storeId
    ): void {
        if ($this->versionManager->isPreviewVersion()) {
            $this->previewReindex->reindex($rootCategoryId, $storeId);
        }
    }

    /**
     * @param CategoryTree $subject
     * @param ResolveInfo $resolveInfo
     * @param int $rootCategoryId
     * @param array $criteria
     * @param StoreInterface $store
     * @param array $attributeNames
     * @param ContextInterface $context
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @throws Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeGetFilteredTree(
        CategoryTree $subject,
        ResolveInfo $resolveInfo,
        int $rootCategoryId,
        array $criteria,
        StoreInterface $store,
        array $attributeNames,
        ContextInterface $context
    ): void {
        if ($this->versionManager->isPreviewVersion()) {
            $this->previewReindex->reindex($rootCategoryId, (int)$store->getId());
        }
    }
}
