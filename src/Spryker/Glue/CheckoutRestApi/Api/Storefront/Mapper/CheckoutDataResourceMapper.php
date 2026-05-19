<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Glue\CheckoutRestApi\Api\Storefront\Mapper;

use Generated\Api\Storefront\CheckoutDataStorefrontResource;
use Generated\Shared\Transfer\AddressTransfer;
use Generated\Shared\Transfer\PaymentMethodsTransfer;
use Generated\Shared\Transfer\RestAddressTransfer;
use Generated\Shared\Transfer\RestCheckoutDataResponseAttributesTransfer;
use Generated\Shared\Transfer\RestCheckoutDataTransfer;
use Generated\Shared\Transfer\RestCheckoutRequestAttributesTransfer;
use Generated\Shared\Transfer\RestPaymentMethodTransfer;
use Generated\Shared\Transfer\RestPaymentProviderTransfer;
use Generated\Shared\Transfer\RestShipmentMethodTransfer;
use Generated\Shared\Transfer\ShipmentMethodTransfer;
use Spryker\Glue\CheckoutRestApi\CheckoutRestApiConfig;
use Spryker\Glue\CheckoutRestApi\Processor\Exception\PaymentMethodNotConfiguredException;
use Spryker\Service\Shipment\ShipmentServiceInterface;

class CheckoutDataResourceMapper
{
    public function __construct(
        protected CheckoutRestApiConfig $checkoutRestApiConfig,
        protected ShipmentServiceInterface $shipmentService,
    ) {
    }

    /**
     * @param array<\Spryker\Glue\CheckoutRestApiExtension\Dependency\Plugin\CheckoutDataResponseMapperPluginInterface> $checkoutDataResponseMapperPlugins
     */
    public function buildResponseAttributes(
        RestCheckoutDataTransfer $restCheckoutDataTransfer,
        RestCheckoutRequestAttributesTransfer $restCheckoutRequestAttributesTransfer,
        array $checkoutDataResponseMapperPlugins,
    ): RestCheckoutDataResponseAttributesTransfer {
        $responseAttributesTransfer = new RestCheckoutDataResponseAttributesTransfer();
        $responseAttributesTransfer = $this->mapAddresses($restCheckoutDataTransfer, $responseAttributesTransfer);
        $responseAttributesTransfer = $this->mapPaymentProviders($restCheckoutDataTransfer, $responseAttributesTransfer);
        $responseAttributesTransfer = $this->mapShipmentMethods($restCheckoutDataTransfer, $responseAttributesTransfer);

        foreach ($checkoutDataResponseMapperPlugins as $plugin) {
            $responseAttributesTransfer = $plugin->mapRestCheckoutDataResponseTransferToRestCheckoutDataResponseAttributesTransfer(
                $restCheckoutDataTransfer,
                $restCheckoutRequestAttributesTransfer,
                $responseAttributesTransfer,
            );
        }

        return $responseAttributesTransfer;
    }

    public function mapResponseAttributesToResource(
        RestCheckoutDataResponseAttributesTransfer $responseAttributesTransfer,
        RestCheckoutDataTransfer $restCheckoutDataTransfer,
        CheckoutDataStorefrontResource $resource,
    ): CheckoutDataStorefrontResource {
        $arrayData = $responseAttributesTransfer->toArray(true, true);

        foreach ($arrayData as $key => $value) {
            if (property_exists($resource, $key)) {
                $resource->{$key} = $value;
            }
        }

        // Singleton-resource pattern: identifier value equals the resource shortName so
        // the JSON:API IRI converter can produce a synthetic IRI. `IdNormalizer` strips
        // this synthetic id from the response, leaving `data.id = null` for BC.
        $resource->checkoutDataId = CheckoutRestApiConfig::RESOURCE_CHECKOUT_DATA;

        $resource->shipmentsRelationshipData = $this->mapShipmentsRelationshipData($restCheckoutDataTransfer);
        $resource->shipmentMethodsRelationshipData = $this->mapAvailableShipmentMethodsRelationshipData($restCheckoutDataTransfer);
        $resource->shipmentTypesRelationshipData = $this->mapShipmentTypesRelationshipData($restCheckoutDataTransfer);
        $resource->companyBusinessUnitAddressUuids = $this->mapCompanyBusinessUnitAddressUuids($restCheckoutDataTransfer);
        $resource->servicePointsRelationshipData = $this->mapServicePointsRelationshipData($restCheckoutDataTransfer);

        return $resource;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function mapShipmentsRelationshipData(RestCheckoutDataTransfer $restCheckoutDataTransfer): array
    {
        $quoteTransfer = $restCheckoutDataTransfer->getQuote();

        if ($quoteTransfer === null) {
            return [];
        }

        $shipmentGroupTransfers = $this->shipmentService->groupItemsByShipment($quoteTransfer->getItems());

        if ($shipmentGroupTransfers->count() === 0) {
            return [];
        }

        $rows = [];

        foreach ($shipmentGroupTransfers as $shipmentGroupTransfer) {
            $shipmentTransfer = $shipmentGroupTransfer->getShipment();
            $methodTransfer = $shipmentTransfer?->getMethod();

            $selectedShipmentMethod = [];

            if ($methodTransfer !== null && $methodTransfer->getIdShipmentMethod() !== null) {
                $selectedShipmentMethod = [
                    'id' => $methodTransfer->getIdShipmentMethod(),
                    'name' => $methodTransfer->getName(),
                    'carrierName' => $methodTransfer->getCarrierName(),
                    'price' => $methodTransfer->getStoreCurrencyPrice(),
                    'taxRate' => $methodTransfer->getTaxRate(),
                    'deliveryTime' => $methodTransfer->getDeliveryTime(),
                    'currencyIsoCode' => $methodTransfer->getCurrencyIsoCode(),
                ];
            }

            $shippingAddressTransfer = $shipmentTransfer?->getShippingAddress();
            $items = [];

            foreach ($shipmentGroupTransfer->getItems() as $itemTransfer) {
                $items[] = $itemTransfer->getGroupKey();
            }

            $rows[] = [
                'shipmentsId' => $shipmentGroupTransfer->getHash() ?? (string)count($rows),
                'items' => $items,
                'selectedShipmentMethod' => $selectedShipmentMethod,
                'shippingAddress' => $shippingAddressTransfer !== null
                    ? $this->mapShippingAddressToArray($shippingAddressTransfer)
                    : null,
                'requestedDeliveryDate' => $shipmentTransfer?->getRequestedDeliveryDate(),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function mapAvailableShipmentMethodsRelationshipData(RestCheckoutDataTransfer $restCheckoutDataTransfer): array
    {
        $rows = [];
        $seenIds = [];

        foreach ($this->collectAvailableShipmentMethodTransfers($restCheckoutDataTransfer) as $shipmentMethodTransfer) {
            $idShipmentMethod = $shipmentMethodTransfer->getIdShipmentMethod();

            if ($idShipmentMethod === null || isset($seenIds[$idShipmentMethod])) {
                continue;
            }

            $seenIds[$idShipmentMethod] = true;
            $rows[] = [
                'idShipmentMethod' => (string)$idShipmentMethod,
                'name' => $shipmentMethodTransfer->getName(),
                'carrierName' => $shipmentMethodTransfer->getCarrierName(),
                'price' => $shipmentMethodTransfer->getStoreCurrencyPrice(),
                'taxRate' => $shipmentMethodTransfer->getTaxRate(),
                'deliveryTime' => $shipmentMethodTransfer->getDeliveryTime(),
                'currencyIsoCode' => $shipmentMethodTransfer->getCurrencyIsoCode(),
            ];
        }

        return $rows;
    }

    /**
     * @return iterable<\Generated\Shared\Transfer\ShipmentMethodTransfer>
     */
    protected function collectAvailableShipmentMethodTransfers(RestCheckoutDataTransfer $restCheckoutDataTransfer): iterable
    {
        $availableShipmentMethodsCollectionTransfer = $restCheckoutDataTransfer->getAvailableShipmentMethods();

        if ($availableShipmentMethodsCollectionTransfer !== null) {
            foreach ($availableShipmentMethodsCollectionTransfer->getShipmentMethods() as $shipmentMethodsTransfer) {
                yield from $shipmentMethodsTransfer->getMethods();
            }
        }

        $shipmentMethodsTransfer = $restCheckoutDataTransfer->getShipmentMethods();

        if ($shipmentMethodsTransfer !== null) {
            yield from $shipmentMethodsTransfer->getMethods();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function mapShipmentTypesRelationshipData(RestCheckoutDataTransfer $restCheckoutDataTransfer): array
    {
        $shipmentTypesByUuid = [];

        foreach ($this->collectAvailableShipmentMethodTransfers($restCheckoutDataTransfer) as $shipmentMethodTransfer) {
            $shipmentTypeTransfer = $shipmentMethodTransfer->getShipmentType();

            if ($shipmentTypeTransfer === null || $shipmentTypeTransfer->getUuid() === null) {
                continue;
            }

            $uuid = $shipmentTypeTransfer->getUuid();
            $shipmentTypesByUuid[$uuid] = [
                'uuid' => $uuid,
                'name' => $shipmentTypeTransfer->getName(),
                'key' => $shipmentTypeTransfer->getKey(),
            ];
        }

        return array_values($shipmentTypesByUuid);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function mapServicePointsRelationshipData(RestCheckoutDataTransfer $restCheckoutDataTransfer): array
    {
        $rows = [];

        foreach ($restCheckoutDataTransfer->getServicePoints() as $servicePointTransfer) {
            $uuid = $servicePointTransfer->getUuid();

            if ($uuid === null) {
                continue;
            }

            $rows[] = [
                'uuid' => $uuid,
                'name' => $servicePointTransfer->getName(),
                'key' => $servicePointTransfer->getKey(),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    protected function mapCompanyBusinessUnitAddressUuids(RestCheckoutDataTransfer $restCheckoutDataTransfer): array
    {
        $companyBusinessUnitAddressesCollectionTransfer = $restCheckoutDataTransfer->getCompanyBusinessUnitAddresses();

        if ($companyBusinessUnitAddressesCollectionTransfer === null) {
            return [];
        }

        $uuids = [];

        foreach ($companyBusinessUnitAddressesCollectionTransfer->getCompanyUnitAddresses() as $companyUnitAddressTransfer) {
            $uuid = $companyUnitAddressTransfer->getUuid();

            if ($uuid !== null) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapShippingAddressToArray(AddressTransfer $addressTransfer): array
    {
        $addressData = $addressTransfer->toArray(false, true);

        // Legacy REST API exposed the company-business-unit-address UUID under the key
        // `idCompanyBusinessUnitAddress`. Preserve that contract.
        if (!empty($addressData['companyBusinessUnitAddressUuid'])) {
            $addressData['idCompanyBusinessUnitAddress'] = $addressData['companyBusinessUnitAddressUuid'];
        }

        return $addressData;
    }

    protected function mapAddresses(
        RestCheckoutDataTransfer $restCheckoutDataTransfer,
        RestCheckoutDataResponseAttributesTransfer $responseAttributesTransfer,
    ): RestCheckoutDataResponseAttributesTransfer {
        if (!$this->checkoutRestApiConfig->isAddressesMappedToAttributes()) {
            return $responseAttributesTransfer;
        }

        foreach ($restCheckoutDataTransfer->getAddresses()->getAddresses() as $addressTransfer) {
            $responseAttributesTransfer->addAddress(
                (new RestAddressTransfer())
                    ->fromArray($addressTransfer->toArray(), true)
                    ->setId($addressTransfer->getUuid()),
            );
        }

        return $responseAttributesTransfer;
    }

    protected function mapPaymentProviders(
        RestCheckoutDataTransfer $restCheckoutDataTransfer,
        RestCheckoutDataResponseAttributesTransfer $responseAttributesTransfer,
    ): RestCheckoutDataResponseAttributesTransfer {
        if (!$this->checkoutRestApiConfig->isPaymentProvidersMappedToAttributes()) {
            return $responseAttributesTransfer;
        }

        $availablePaymentMethodsList = $this->getAvailablePaymentMethodsList(
            $restCheckoutDataTransfer->getAvailablePaymentMethods(),
        );

        foreach ($restCheckoutDataTransfer->getPaymentProviders()->getPaymentProviders() as $paymentProviderTransfer) {
            $restPaymentProviderTransfer = (new RestPaymentProviderTransfer())
                ->setPaymentProviderName($paymentProviderTransfer->getPaymentProviderKey());

            foreach ($paymentProviderTransfer->getPaymentMethods() as $paymentMethodTransfer) {
                $paymentSelection = $this->findPaymentSelectionByPaymentProviderAndMethodNames(
                    $paymentProviderTransfer->getPaymentProviderKey(),
                    $paymentMethodTransfer->getName(),
                );

                if (!$paymentSelection || !in_array($paymentMethodTransfer->getMethodName(), $availablePaymentMethodsList)) {
                    continue;
                }

                $restPaymentProviderTransfer->addPaymentMethod(
                    (new RestPaymentMethodTransfer())
                        ->setPaymentMethodName($paymentMethodTransfer->getName())
                        ->setRequiredRequestData(
                            $this->checkoutRestApiConfig->getRequiredRequestDataForPaymentMethod(
                                $paymentMethodTransfer->getMethodName(),
                            ),
                        ),
                );
            }

            $responseAttributesTransfer->addPaymentProvider($restPaymentProviderTransfer);
        }

        return $responseAttributesTransfer;
    }

    protected function mapShipmentMethods(
        RestCheckoutDataTransfer $restCheckoutDataTransfer,
        RestCheckoutDataResponseAttributesTransfer $responseAttributesTransfer,
    ): RestCheckoutDataResponseAttributesTransfer {
        if (!$this->checkoutRestApiConfig->isShipmentMethodsMappedToAttributes()) {
            return $responseAttributesTransfer;
        }

        foreach ($restCheckoutDataTransfer->getShipmentMethods()->getMethods() as $shipmentMethodTransfer) {
            $responseAttributesTransfer->addShipmentMethod(
                $this->mapShipmentMethodTransferToRestShipmentMethodTransfer(
                    $shipmentMethodTransfer,
                    new RestShipmentMethodTransfer(),
                ),
            );
        }

        return $responseAttributesTransfer;
    }

    protected function mapShipmentMethodTransferToRestShipmentMethodTransfer(
        ShipmentMethodTransfer $shipmentMethodTransfer,
        RestShipmentMethodTransfer $restShipmentMethodTransfer,
    ): RestShipmentMethodTransfer {
        return $restShipmentMethodTransfer
            ->fromArray($shipmentMethodTransfer->toArray(), true)
            ->setPrice($shipmentMethodTransfer->getStoreCurrencyPrice())
            ->setId($shipmentMethodTransfer->getIdShipmentMethod());
    }

    /**
     * @return array<int, string|null>
     */
    protected function getAvailablePaymentMethodsList(PaymentMethodsTransfer $availablePaymentMethodsTransfer): array
    {
        $methodNames = [];

        foreach ($availablePaymentMethodsTransfer->getMethods() as $paymentMethodTransfer) {
            $methodNames[] = $paymentMethodTransfer->getMethodName();
        }

        return $methodNames;
    }

    /**
     * @throws \Spryker\Glue\CheckoutRestApi\Processor\Exception\PaymentMethodNotConfiguredException
     */
    protected function findPaymentSelectionByPaymentProviderAndMethodNames(
        string $paymentProviderName,
        string $paymentMethodName,
    ): ?string {
        if (!$this->checkoutRestApiConfig->isPaymentProviderMethodToStateMachineMappingEnabled()) {
            return $paymentMethodName;
        }

        $mapping = $this->checkoutRestApiConfig->getPaymentProviderMethodToStateMachineMapping();

        if (!isset($mapping[$paymentProviderName][$paymentMethodName])) {
            throw new PaymentMethodNotConfiguredException(sprintf(
                'Payment method "%s" for payment provider "%s" is not configured in CheckoutRestApiConfig::getPaymentProviderMethodToStateMachineMapping()',
                $paymentMethodName,
                $paymentProviderName,
            ));
        }

        return $mapping[$paymentProviderName][$paymentMethodName];
    }
}
