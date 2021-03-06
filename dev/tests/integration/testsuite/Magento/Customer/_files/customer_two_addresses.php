<?php
/**
 * Customer address fixture with entity_id = 2, this fixture also creates address with entity_id = 1
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Customer\Model\CustomerRegistry;

require 'customer_address.php';

/** @var \Magento\Customer\Model\Address $customerAddress */
$customerAddress = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
    ->create(\Magento\Customer\Model\Address::class);
/** @var CustomerRegistry $customerRegistry */
$customerRegistry = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
    ->get(CustomerRegistry::class);
$customerAddress->isObjectNew(true);
$customerAddress->setData(
    [
        'entity_id' => 2,
        'attribute_set_id' => 2,
        'telephone' => 3234676,
        'postcode' => 47676,
        'country_id' => 'US',
        'city' => 'CityX',
        'street' => ['Black str, 48'],
        'lastname' => 'Smith',
        'firstname' => 'John',
        'parent_id' => 1,
        'region_id' => 1,
    ]
)->setCustomerId(
    1
);

$customerAddress->save();
/** @var \Magento\Customer\Api\AddressRepositoryInterface $addressRepository */
$addressRepository = $objectManager->get(\Magento\Customer\Api\AddressRepositoryInterface::class);
$customerAddress = $addressRepository->getById(2);
$customerAddress->setCustomerId(1);
$customerAddress = $addressRepository->save($customerAddress);
$customerRegistry->remove($customerAddress->getCustomerId());
/** @var \Magento\Customer\Model\AddressRegistry $addressRegistry */
$addressRegistry = $objectManager->get(\Magento\Customer\Model\AddressRegistry::class);
$addressRegistry->remove($customerAddress->getId());
