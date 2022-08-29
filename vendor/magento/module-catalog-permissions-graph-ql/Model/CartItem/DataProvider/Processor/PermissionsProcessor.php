<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CatalogPermissionsGraphQl\Model\CartItem\DataProvider\Processor;

use Magento\Catalog\Model\ResourceModel\Product;
use Magento\CatalogPermissions\Helper\Data as CatalogPermissionsData;
use Magento\CatalogPermissions\Model\Permission;
use Magento\CatalogPermissions\Model\ResourceModel\Permission\Index;
use Magento\CatalogPermissionsGraphQl\Model\Customer\GroupProcessor;
use Magento\CatalogPermissionsGraphQl\Model\Store\StoreProcessor;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\QuoteGraphQl\Model\CartItem\DataProvider\Processor\ItemDataProcessorInterface;
use Magento\CatalogPermissions\App\ConfigInterface;

class PermissionsProcessor implements ItemDataProcessorInterface
{
    /**
     * @var Product
     */
    private $product;

    /**
     * @var GroupProcessor
     */
    private $groupProcessor;

    /**
     * @var Index
     */
    private $index;

    /**
     * @var StoreProcessor
     */
    private $storeProcessor;

    /**
     * @var CatalogPermissionsData
     */
    private $catalogPermissionsData;

    /**
     * Catalog permissions config
     *
     * @var ConfigInterface
     */
    private $config;

    /**
     * @param Product $product
     * @param GroupProcessor $groupProcessor
     * @param StoreProcessor $storeProcessor
     * @param Index $index
     * @param CatalogPermissionsData $catalogPermissionsData
     * @param ConfigInterface $config
     */
    public function __construct(
        Product $product,
        GroupProcessor $groupProcessor,
        StoreProcessor $storeProcessor,
        Index $index,
        CatalogPermissionsData $catalogPermissionsData,
        ConfigInterface $config
    ) {
        $this->product = $product;
        $this->groupProcessor = $groupProcessor;
        $this->storeProcessor = $storeProcessor;
        $this->index = $index;
        $this->catalogPermissionsData = $catalogPermissionsData;
        $this->config = $config;
    }

    /**
     * Process cart item data
     *
     * @param array $cartItemData
     * @param ContextInterface $context
     * @return array
     */
    public function process(array $cartItemData, ContextInterface $context): array
    {
        if ($this->config->isEnabled()) {
            $productId = (int)$this->product->getIdBySku($cartItemData['sku']);
            $customerGroupId = $this->groupProcessor->getCustomerGroup($context);
            $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
            $permissionsIndexes = $this->index->getIndexForProduct($productId, $customerGroupId, $storeId);

            if ($permissionsIndexes) {
                if ($permissionsIndexes[$productId]['grant_checkout_items'] == Permission::PERMISSION_DENY
                    || $permissionsIndexes[$productId]['grant_checkout_items'] != Permission::PERMISSION_ALLOW
                    && !$this->catalogPermissionsData->isAllowedCheckoutItems($storeId, $customerGroupId)
                ) {
                    $cartItemData['grant_checkout'] = false;
                }
            } else {
                if (!$this->catalogPermissionsData->isAllowedCheckoutItems($storeId, $customerGroupId)) {
                    $cartItemData['grant_checkout'] = false;
                }
            }
        }

        return $cartItemData;
    }
}
