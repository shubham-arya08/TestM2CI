<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);


namespace Magento\CheckoutStaging\Test\Unit\Plugin;

use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\CheckoutStaging\Plugin\GuestPaymentInformationManagementPlugin;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Staging\Model\VersionManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test class for GuestPaymentInformationManagement plugin
 */
class GuestPaymentInformationManagementPluginTest extends TestCase
{
    const CART_ID = 1;
    const EMAIL = 'test@test.com';

    /**
     * @var VersionManager|MockObject
     */
    private $versionManager;

    /**
     * @var GuestPaymentInformationManagementInterface|MockObject
     */
    private $paymentInformationManagement;

    /**
     * @var PaymentInterface|MockObject
     */
    private $paymentMethod;

    /**
     * @var AddressInterface|MockObject
     */
    private $address;

    /**
     * @var CartRepositoryInterface|MockObject
     */
    private $cartRepository;

    /**
     * @var QuoteIdMaskFactory|MockObject
     */
    private $quoteIdMaskFactory;
    /**
     * @var Quote|MockObject
     */
    private $quoteMock;

    /**
     * @var AddressInterface|MockObject
     */
    private $shippingAddress;

    /**
     * @var QuoteIdMask|MockObject
     */
    private $quoteIdMaskMock;

    /**
     * @var GuestPaymentInformationManagementPlugin
     */
    private $plugin;

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $this->versionManager = $this->getMockBuilder(VersionManager::class)
            ->disableOriginalConstructor()
            ->setMethods(['isPreviewVersion'])
            ->getMock();

        $this->quoteIdMaskFactory = $this->createPartialMock(
            QuoteIdMaskFactory::class,
            ['create']
        );
        $this->quoteIdMaskMock = $this->getMockBuilder(QuoteIdMask::class)
            ->addMethods(['getQuoteId'])
            ->onlyMethods(['load'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->cartRepository = $this->getMockBuilder(CartRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->setMethods(['getShippingAddress', 'getCustomerId', 'getCustomerAddressId'])
            ->getMock();
        $this->shippingAddress = $this->getMockBuilder(AddressInterface::class)
            ->setMethods(['getData'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->paymentInformationManagement =
            $this->getMockForAbstractClass(GuestPaymentInformationManagementInterface::class);
        $this->paymentMethod = $this->getMockForAbstractClass(PaymentInterface::class);
        $this->address = $this->getMockBuilder(AddressInterface::class)
            ->setMethods(['getData'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->plugin = new GuestPaymentInformationManagementPlugin(
            $this->versionManager,
            $this->quoteIdMaskFactory,
            $this->cartRepository);
    }

    /**
     * Test Disable order submitting for preview for guest user throws exception
     */
    public function testBeforeSavePaymentInformationAndPlaceOrder()
    {
        $this->expectException('Magento\Framework\Exception\LocalizedException');
        $this->expectExceptionMessage('The order can\'t be submitted in preview mode.');
        $this->versionManager->expects(static::once())
            ->method('isPreviewVersion')
            ->willReturn(true);

        $this->plugin->beforeSavePaymentInformationAndPlaceOrder(
            $this->paymentInformationManagement,
            self::CART_ID,
            self::EMAIL,
            $this->paymentMethod,
            $this->address
        );
    }

    /**
     * Test same_as_billing flag for guest user
     *
     * @param string $quoteSameAsBilling
     * @param string|null $customerId
     * @param string|null $shippingCustomerAddressId
     * @param string|null $billingCustomerAddressId
     * @dataProvider dataProviderForSavePaymentInformationAndPlaceOrder
     * @throws LocalizedException
     */
    public function testSameAsBillingFlagBeforeSavePaymentInformationAndPlaceOrder(
        string $quoteSameAsBilling,
        string $customerId = null,
        string $shippingCustomerAddressId = null,
        string $billingCustomerAddressId = null
    ): void {
        $this->versionManager->expects(static::once())
            ->method('isPreviewVersion')
            ->willReturn(false);
        $this->quoteIdMaskFactory
            ->expects($this->any())
            ->method('create')
            ->willReturn($this->quoteIdMaskMock);
        $this->quoteIdMaskMock->expects($this->once())->method('load')->with(self::CART_ID, 'masked_id')->willReturnSelf();
        $this->quoteIdMaskMock->expects($this->once())->method('getQuoteId')->willReturn(self::CART_ID);
        $this->cartRepository
            ->expects($this->any())
            ->method('getActive')
            ->with(self::CART_ID)
            ->willReturn($this->quoteMock);
        $this->quoteMock
            ->expects($this->any())
            ->method('getShippingAddress')
            ->willReturn($this->shippingAddress);
        $this->quoteMock
            ->expects($this->any())
            ->method('getCustomerId')
            ->willReturn($customerId);
        $this->shippingAddress
            ->expects($this->any())
            ->method('getSameAsBilling')
            ->willReturn($quoteSameAsBilling);
        $this->shippingAddress
            ->expects($this->any())
            ->method('getData')
            ->willReturn([]);
        $this->shippingAddress
            ->expects($this->any())
            ->method('getCustomerAddressId')
            ->willReturn($shippingCustomerAddressId);
        $this->address
            ->expects($this->any())
            ->method('getData')
            ->willReturn([]);
        $this->address
            ->expects($this->any())
            ->method('getCustomerAddressId')
            ->willReturn($billingCustomerAddressId);
        $this->plugin->beforeSavePaymentInformationAndPlaceOrder(
            $this->paymentInformationManagement,
            self::CART_ID,
            self::EMAIL,
            $this->paymentMethod,
            $this->address
        );
    }

    /**
     * Data provider for plugin beforeSavePaymentInformationAndPlaceOrder.
     *
     * @return array
     */
    public function dataProviderForSavePaymentInformationAndPlaceOrder(): array
    {
        return [
            'update same_as_billing flag if getSameAsBilling is 1' => ['1', '1', '2', '3'],
            'update same_as_billing flag with different customer address id for shipping and billing ' => ['0', '2', '1', '2'],
            'update same_as_billing flag with same customer address id for shipping and billing ' => ['0', '2', '2', '2'],
        ];
    }
}
