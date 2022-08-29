/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'ko',
    'mage/storage',
    'Magento_Customer/js/model/customer',
    'Magento_GiftCardAccount/js/model/gift-card',
    'Magento_GiftCardAccount/js/model/payment/gift-card-messages',
    'Magento_Checkout/js/model/url-builder',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/error-processor',
    'mage/utils/wrapper',
    'Magento_ReCaptchaWebapiUi/js/webapiReCaptchaRegistry'
], function (
    ko,
    storage,
    customer,
    giftCardAccount,
    messageList,
    urlBuilder,
    quote,
    errorProcessor,
    wrapper,
    recaptchaRegistry
  ) {
    'use strict';

    var extender = {

        /**
         * @param {Function} originFn - Original method.
         * @param {Object} giftCardCode - giftCardCode model.
         */
        check: function (originFn, giftCardCode) {
            var self = this,
                serviceUrl, headers = {};

            this.isLoading(true);

            if (recaptchaRegistry.triggers.hasOwnProperty('recaptcha-checkout-gift-apply')) {
                recaptchaRegistry.addListener('recaptcha-checkout-gift-apply', function (token) {
                    headers  = {
                        'X-ReCaptcha': token
                    };
                });

                if (!customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl('/carts/guest-carts/:cartId/checkGiftCard/:giftCardCode', {
                        cartId: quote.getQuoteId(),
                        giftCardCode: giftCardCode
                    });
                } else {
                    serviceUrl = urlBuilder.createUrl('/carts/mine/checkGiftCard/:giftCardCode', {
                        giftCardCode: giftCardCode
                    });
                }
                messageList.clear();

                return storage.get(
                    serviceUrl,  true, 'application/json', headers
               ).done(function (response) {
                    giftCardAccount.isChecked(true);
                    giftCardAccount.code(giftCardCode);
                    giftCardAccount.amount(response);
                    giftCardAccount.isValid(true);
                }).fail(function (response) {
                    giftCardAccount.isValid(false);
                    errorProcessor.process(response, messageList);
                }).always(function () {
                    self.isLoading(false);
                });
            }

            return originFn(giftCardCode);
        }
    };

    return function (target) {
        return wrapper.extend(target, extender);
    };
});
