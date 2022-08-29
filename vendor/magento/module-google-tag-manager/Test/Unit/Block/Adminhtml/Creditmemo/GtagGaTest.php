<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GoogleTagManager\Test\Unit\Block\Adminhtml\Creditmemo;

use Magento\Backend\Model\Session;
use Magento\Cookie\Helper\Cookie;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Escaper;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use Magento\Framework\View\Element\Template\Context;
use Magento\GoogleTagManager\Block\Adminhtml\Creditmemo\GtagGa;
use Magento\GoogleTagManager\Model\Config\TagManagerConfig;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GtagGaTest extends TestCase
{
    /** @var GtagGa */
    protected $ga;

    /** @var ObjectManagerHelper */
    protected $objectManagerHelper;

    /** @var Session|MockObject */
    protected $session;

    /** @var StoreManagerInterface|MockObject */
    protected $storeManagerMock;

    /**
     * @var TagManagerConfig|MockObject
     */
    private $tagManagerConfig;

    protected function setUp(): void
    {
        $objectManager = new ObjectManager($this);

        $contextMock = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();

        $contextMock->expects($this->once())
            ->method('getEscaper')
            ->willReturn($objectManager->getObject(Escaper::class));

        $this->storeManagerMock = $this->getMockBuilder(StoreManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $serializerMock = $this->getMockBuilder(Json::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $contextMock->expects($this->once())->method('getStoreManager')->willReturn($this->storeManagerMock);

        $orderRepository = $this->getMockBuilder(OrderRepositoryInterface::class)
            ->onlyMethods(['getList'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $searchCriteriaBuilder = $this->getMockBuilder(SearchCriteriaBuilder::class)
            ->onlyMethods(['addFilter', 'create'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->tagManagerConfig = $this->getMockBuilder(TagManagerConfig::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cookieHelperMock = $this->getMockBuilder(Cookie::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->session = $this->createMock(Session::class);

        $this->ga = $objectManager->getObject(
            GtagGa::class,
            [
                'context' => $contextMock,
                'googleGtagConfig' => $this->tagManagerConfig,
                'cookieHelper' => $cookieHelperMock,
                'serializer' => $serializerMock,
                'searchCriteriaBuilder' => $searchCriteriaBuilder,
                'orderRepository' => $orderRepository,
                'backendSession' => $this->session
            ]
        );
    }

    /**
     * @param int|null $orderId
     * @param int|string $expected
     *
     * @dataProvider getOrderIdDataProvider
     */
    public function testGetOrderId($orderId, $expected)
    {
        $this->tagManagerConfig->expects($this->any())->method('isGoogleAnalyticsAvailable')
            ->willReturn(true);
        $this->session->expects($this->any())
            ->method('getData')
            ->with('googleanalytics_creditmemo_order', true)
            ->willReturn($orderId);
        $this->assertEquals($expected, $this->ga->getOrderId());
    }

    public function getOrderIdDataProvider()
    {
        return [
            ['10', '10'],
            [null, '']
        ];
    }

    /**
     * @param int|null $revenue
     * @param int|string $expected
     *
     * @dataProvider getRevenueDataProvider
     */
    public function testGetRevenue($revenue, $expected)
    {
        $this->session->expects($this->any())->method('getData')->with('googleanalytics_creditmemo_revenue', true)
            ->willReturn($revenue);
        $this->assertEquals($expected, $this->ga->getRevenue());
    }

    public function getRevenueDataProvider()
    {
        return [
            ['101', '101'],
            [null, '']
        ];
    }

    /**
     * @param int|null $products
     * @param int|string $expected
     *
     * @dataProvider getProductsDataProvider
     */
    public function testGetProducts($products, $expected)
    {
        $this->session->expects($this->any())->method('getData')->with('googleanalytics_creditmemo_products', true)
            ->willReturn($products);
        $this->assertEquals($expected, $this->ga->getProducts());
    }

    public function getProductsDataProvider()
    {
        return [
            [[1,2,3], [1,2,3]],
            [null, []]
        ];
    }

    public function testGetRefundJson()
    {
        $this->tagManagerConfig->expects($this->atLeastOnce())->method('isGoogleAnalyticsAvailable')
            ->willReturn(true);
        $this->session->expects($this->any())->method('getData')->willReturnMap([
            ['googleanalytics_creditmemo_order', true, '11'],
            ['googleanalytics_creditmemo_revenue', true, '22'],
            ['googleanalytics_creditmemo_products', true, [31, 32]],
        ]);
        $this->assertEquals(
            '{"event":"refund","ecommerce":{"refund":{"actionField":{"id":"11","revenue":"22"},"products":[31,32]}}}',
            $this->ga->getRefundJson()
        );
    }
}
