<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Glue\CheckoutRestApi\Processor\Quote;

use Generated\Shared\Transfer\CustomerTransfer;
use Generated\Shared\Transfer\QuoteCriteriaFilterTransfer;
use Generated\Shared\Transfer\QuoteResponseTransfer;
use Generated\Shared\Transfer\QuoteTransfer;
use Generated\Shared\Transfer\RestCheckoutRequestAttributesTransfer;
use Spryker\Glue\CheckoutRestApi\Dependency\Client\CheckoutRestApiToCartClientInterface;
use Spryker\Glue\CheckoutRestApi\Dependency\Client\CheckoutRestApiToCustomerClientInterface;
use Spryker\Glue\CheckoutRestApi\Processor\CheckoutData\CheckoutDataMapperInterface;
use Spryker\Glue\CheckoutRestApiExtension\Dependency\Plugin\QuoteCollectionReaderPluginInterface;
use Spryker\Glue\GlueApplication\Rest\Request\Data\RestRequestInterface;

class QuoteProcessor implements QuoteProcessorInterface
{
    /**
     * @var \Spryker\Glue\CheckoutRestApi\Dependency\Client\CheckoutRestApiToCartClientInterface
     */
    protected $cartClient;

    /**
     * @var \Spryker\Glue\CheckoutRestApiExtension\Dependency\Plugin\QuoteCollectionReaderPluginInterface
     */
    protected $quoteCollectionReaderPlugin;

    /**
     * @var \Spryker\Glue\CheckoutRestApi\Processor\CheckoutData\CheckoutDataMapperInterface
     */
    protected $checkoutDataMapper;

    /**
     * @var \Spryker\Glue\CheckoutRestApi\Dependency\Client\CheckoutRestApiToCustomerClientInterface
     */
    protected $customerClient;

    /**
     * @param \Spryker\Glue\CheckoutRestApi\Dependency\Client\CheckoutRestApiToCartClientInterface $cartClient
     * @param \Spryker\Glue\CheckoutRestApiExtension\Dependency\Plugin\QuoteCollectionReaderPluginInterface $quoteCollectionReaderPlugin
     * @param \Spryker\Glue\CheckoutRestApi\Processor\CheckoutData\CheckoutDataMapperInterface $checkoutDataMapper
     * @param \Spryker\Glue\CheckoutRestApi\Dependency\Client\CheckoutRestApiToCustomerClientInterface $customerClient
     */
    public function __construct(
        CheckoutRestApiToCartClientInterface $cartClient,
        QuoteCollectionReaderPluginInterface $quoteCollectionReaderPlugin,
        CheckoutDataMapperInterface $checkoutDataMapper,
        CheckoutRestApiToCustomerClientInterface $customerClient
    ) {
        $this->cartClient = $cartClient;
        $this->quoteCollectionReaderPlugin = $quoteCollectionReaderPlugin;
        $this->checkoutDataMapper = $checkoutDataMapper;
        $this->customerClient = $customerClient;
    }

    /**
     * @param \Generated\Shared\Transfer\RestCheckoutRequestAttributesTransfer $restCheckoutRequestAttributesTransfer
     *
     * @return \Generated\Shared\Transfer\QuoteTransfer|null
     */
    public function getCustomerQuote(RestCheckoutRequestAttributesTransfer $restCheckoutRequestAttributesTransfer): ?QuoteTransfer
    {
        $quoteIdentifier = $restCheckoutRequestAttributesTransfer->getQuote()->getQuoteIdentifier();
        $quoteCollectionTransfer = $this->quoteCollectionReaderPlugin->getQuoteCollectionByCriteria(new QuoteCriteriaFilterTransfer());
        foreach ($quoteCollectionTransfer->getQuotes() as $customerQuote) {
            if ($customerQuote->getUuid() === $quoteIdentifier) {
                return $customerQuote;
            }
        }

        return null;
    }

    /**
     * @return \Generated\Shared\Transfer\QuoteResponseTransfer
     */
    public function validateQuote(): QuoteResponseTransfer
    {
        return $this->cartClient->validateQuote();
    }

    /**
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     * @param \Generated\Shared\Transfer\RestCheckoutRequestAttributesTransfer $restCheckoutRequestAttributesTransfer
     * @param \Spryker\Glue\GlueApplication\Rest\Request\Data\RestRequestInterface $restRequest
     *
     * @return \Generated\Shared\Transfer\QuoteTransfer
     */
    public function updateQuoteWithDataFromRequest(
        QuoteTransfer $quoteTransfer,
        RestCheckoutRequestAttributesTransfer $restCheckoutRequestAttributesTransfer,
        RestRequestInterface $restRequest
    ): QuoteTransfer {
        $quoteTransfer = $this->checkoutDataMapper->mapRestCheckoutRequestAttributesTransferToQuoteTransfer(
            $quoteTransfer,
            $restCheckoutRequestAttributesTransfer
        );
        $this->updateQuoteWithCustomerFromRequest($quoteTransfer, $restCheckoutRequestAttributesTransfer, $restRequest);

        return $quoteTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     * @param \Generated\Shared\Transfer\RestCheckoutRequestAttributesTransfer $restCheckoutRequestAttributesTransfer
     * @param \Spryker\Glue\GlueApplication\Rest\Request\Data\RestRequestInterface $restRequest
     *
     * @return void
     */
    protected function updateQuoteWithCustomerFromRequest(
        QuoteTransfer $quoteTransfer,
        RestCheckoutRequestAttributesTransfer $restCheckoutRequestAttributesTransfer,
        RestRequestInterface $restRequest
    ): void {
        if (!$restRequest->getUser()) {
            $quoteTransfer->setCustomer((new CustomerTransfer())->fromArray($restCheckoutRequestAttributesTransfer->getQuote()->getCustomer()->toArray(), true));
        } else {
            $customerResponseTransfer = $this->customerClient->findCustomerByReference((new CustomerTransfer())->setCustomerReference($restRequest->getUser()->getNaturalIdentifier()));
            $quoteTransfer->setCustomer($customerResponseTransfer->getCustomerTransfer());
        }
    }

    /**
     * @return void
     */
    public function clearQuote(): void
    {
        $this->cartClient->clearQuote();
    }
}
