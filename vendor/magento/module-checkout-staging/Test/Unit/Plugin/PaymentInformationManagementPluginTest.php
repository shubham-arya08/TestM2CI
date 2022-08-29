<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CheckoutStaging\Test\Unit\Plugin;

use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\CheckoutStaging\Plugin\PaymentInformationManagementPlugin;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface as CustomerAddressInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote;
use Magento\Staging\Model\VersionManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PaymentInformationManagementPluginTest extends TestCase
{
    private const CART_ID = 1;

    /**
     * @var VersionManager|MockObject
     */
    private $versionManager;

    /**
     * @var PaymentInformationManagementInterface|MockObject
     */
    private $paymentInformationManagement;

    /**
     * @var PaymentInterface|MockObject
     */
    private $paymentMethod;

    /**
     * @var CartRepositoryInterface|MockObject
     */
    private $quoteRepository;

    /**
     * @var Quote|MockObject
     */
    private $quoteMock;

    /**
     * @var CustomerInterface|MockObject
     */
    private $customerMock;

    /**
     * @var AddressInterface|MockObject
     */
    private $shippingAddress;

    /**
     * @var AddressInterface|MockObject
     */
    private $address;

    /**
     * @var AddressRepositoryInterface|MockObject
     */
    private $addressRepository;

    /**
     * @var PaymentInformationManagementPlugin
     */
    private $plugin;

    /**
     * @var CustomerAddressInterface|mixed|MockObject
     */
    private $customerAddress;

    /**
     * @var AddressInterface|mixed|MockObject
     */
    private $billingAddress;

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $this->versionManager = $this->getMockBuilder(VersionManager::class)
            ->disableOriginalConstructor()
            ->setMethods(['isPreviewVersion'])
            ->getMock();
        $this->quoteRepository = $this->getMockBuilder(CartRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->addressRepository = $this->getMockBuilder(AddressRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->customerAddress = $this->getMockBuilder(CustomerAddressInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['setIsDefaultShipping', 'setIsDefaultBilling', 'setCustomerId', 'getId'])
            ->getMockForAbstractClass();
        $this->quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getShippingAddress',
                    'getBillingAddress',
                    'getCustomer',
                    'getCustomerId',
                    'getCustomerAddressId',
                    'addCustomerAddress',
                    'isVirtual'
                ]
            )
            ->getMock();
        $this->shippingAddress = $this->getMockBuilder(AddressInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getSameAsBilling',
                    'setSameAsBilling',
                    'getCustomerAddressId',
                    'getQuoteId',
                    'exportCustomerAddress',
                    'setCustomerAddressData',
                    'setCustomerAddressId',
                    'getData'
                ]
            )
            ->getMockForAbstractClass();
        $this->billingAddress = $this->getMockBuilder(AddressInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getSaveInAddressBook'])
            ->getMockForAbstractClass();
        $this->customerMock = $this->getMockForAbstractClass(CustomerInterface::class);
        $this->paymentInformationManagement =
            $this->getMockForAbstractClass(PaymentInformationManagementInterface::class);
        $this->paymentMethod = $this->getMockForAbstractClass(PaymentInterface::class);
        $this->address = $this->getMockForAbstractClass(AddressInterface::class);

        $this->plugin = new PaymentInformationManagementPlugin(
            $this->versionManager,
            $this->quoteRepository,
            $this->addressRepository
        );
    }

    /**
     * Test Disable order submitting for preview for registered user throws exception
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
            $this->paymentMethod,
            $this->address
        );
    }

    /**
     * Test same_as_billing flag for registered user
     *
     * @param string $quoteSameAsBilling
     * @param string|null $customerId
     * @param string|null $shippingCustomerAddressId
     * @param string|null $billingCustomerAddressId
     * @param bool $hasDefaultBilling
     * @param bool $hasDefaultShipping
     * @throws LocalizedException
     * @dataProvider dataProviderForSavePaymentInformationAndPlaceOrder
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testSameAsBillingFlagBeforeSavePaymentInformationAndPlaceOrder(
        string $quoteSameAsBilling,
        string $customerId = null,
        string $shippingCustomerAddressId = null,
        string $billingCustomerAddressId = null,
        bool $hasDefaultBilling = true,
        bool $hasDefaultShipping = true,
        bool $isVirtual = false
    ): void {
        $this->versionManager->expects(static::once())
            ->method('isPreviewVersion')
            ->willReturn(false);
        $this->quoteRepository
            ->expects($this->any())
            ->method('getActive')
            ->with(self::CART_ID)
            ->willReturn($this->quoteMock);
        $this->quoteMock
            ->expects($this->any())
            ->method('getCustomer')
            ->willReturn($this->customerMock);
        $this->quoteMock
            ->expects($this->any())
            ->method('getShippingAddress')
            ->willReturn($this->shippingAddress);
        $this->quoteMock
            ->expects($this->any())
            ->method('getBillingAddress')
            ->willReturn($this->billingAddress);
        $this->quoteMock
            ->expects($this->any())
            ->method('getCustomerId')
            ->willReturn($customerId);
        $this->quoteMock
            ->expects($this->any())
            ->method('isVirtual')
            ->willReturn($isVirtual);
        $this->customerMock
            ->expects($this->any())
            ->method('getId')
            ->willReturn($customerId);
        $this->customerMock
            ->expects($this->any())
            ->method('getDefaultBilling')
            ->willReturn($hasDefaultBilling);
        $this->customerMock
            ->expects($this->any())
            ->method('getDefaultShipping')
            ->willReturn($hasDefaultShipping);
        $this->customerAddress
            ->expects($this->any())
            ->method('setIsDefaultShipping')
            ->with(!$hasDefaultShipping)
            ->willReturnSelf();
        $this->customerAddress
            ->expects($this->any())
            ->method('setIsDefaultBilling')
            ->with($hasDefaultBilling)
            ->willReturnSelf();
        $this->customerAddress
            ->expects($this->any())
            ->method('setCustomerId')
            ->with($customerId)
            ->willReturnSelf();
        $this->customerAddress
            ->expects($this->any())
            ->method('getId')
            ->willReturn($shippingCustomerAddressId);
        $this->quoteMock
            ->expects($this->any())
            ->method('addCustomerAddress')
            ->with($this->customerAddress)
            ->willReturnSelf();
        $this->shippingAddress
            ->expects($this->any())
            ->method('getSameAsBilling')
            ->willReturn($quoteSameAsBilling);
        $this->shippingAddress
            ->expects($this->any())
            ->method('getCustomerAddressId')
            ->willReturn($shippingCustomerAddressId);
        $this->shippingAddress
            ->expects($this->any())
            ->method('getQuoteId')
            ->willReturn(self::CART_ID);
        $this->shippingAddress
            ->expects($this->any())
            ->method('exportCustomerAddress')
            ->willReturn($this->customerAddress);
        $this->shippingAddress
            ->expects($this->any())
            ->method('setCustomerAddressData')
            ->with($this->customerAddress)
            ->willReturnSelf();
        $this->shippingAddress
            ->expects($this->any())
            ->method('setCustomerAddressId')
            ->with($shippingCustomerAddressId)
            ->willReturnSelf();
        $this->shippingAddress
            ->expects($this->any())
            ->method('getData')
            ->willReturnSelf([]);
        $this->billingAddress
            ->expects($this->any())
            ->method('getSaveInAddressBook')
            ->willReturn(0);
        $this->address
            ->expects($this->any())
            ->method('getCustomerAddressId')
            ->willReturn($billingCustomerAddressId);
        $this->plugin->beforeSavePaymentInformationAndPlaceOrder(
            $this->paymentInformationManagement,
            self::CART_ID,
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
            'update same_as_billing flag if customer id is null'
            => ['0', null, null, null],
            'update same_as_billing flag if getSameAsBilling is 1'
            => ['1', '1', '2', '3'],
            'update same_as_billing flag with different customer address id for shipping and billing'
            => ['0', '2', '1', '2'],
            'update same_as_billing flag with same customer address id for shipping and billing '
            => ['0', '2', '2', '2'],
            'update same_as_billing flag with same customer address id for shipping and billing and no default billing'
            => ['0', '2', '2', '2', false],
            'update same_as_billing flag with same customer address id for shipping and billing and no default shipping'
            => ['0', '2', '2', '2', true, false],
            'update same_as_billing flag with same customer address id for deafult shipping and billing'
            => ['0', '2', '2', '2', true, true],
            'update same_as_billing flag with same customer address id for deafult billing and virtual quote'
            => ['0', '2', '2', '2', true, true, true]
        ];
    }
}
