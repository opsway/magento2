<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Customer\Controller\Address;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\RegionInterface;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use Magento\Customer\Model\Address\Mapper;
use Magento\Customer\Model\Metadata\FormFactory;
use Magento\Customer\Model\Session;
use Magento\Directory\Helper\Data as HelperData;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Framework\View\Result\PageFactory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class FormPost extends \Magento\Customer\Controller\Address implements HttpPostActionInterface
{
    /**
     * @var RegionFactory
     */
    protected $regionFactory;

    /**
     * @var HelperData
     */
    protected $helperData;

    /**
     * @var Mapper
     */
    private $customerAddressMapper;

    /**
     * @param Context $context
     * @param Session $customerSession
     * @param FormKeyValidator $formKeyValidator
     * @param FormFactory $formFactory
     * @param AddressRepositoryInterface $addressRepository
     * @param CustomerRepositoryInterface $customerRepository
     * @param AddressInterfaceFactory $addressDataFactory
     * @param RegionInterfaceFactory $regionDataFactory
     * @param DataObjectProcessor $dataProcessor
     * @param DataObjectHelper $dataObjectHelper
     * @param ForwardFactory $resultForwardFactory
     * @param PageFactory $resultPageFactory
     * @param RegionFactory $regionFactory
     * @param HelperData $helperData
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        FormKeyValidator $formKeyValidator,
        FormFactory $formFactory,
        AddressRepositoryInterface $addressRepository,
        AddressInterfaceFactory $addressDataFactory,
        RegionInterfaceFactory $regionDataFactory,
        DataObjectProcessor $dataProcessor,
        DataObjectHelper $dataObjectHelper,
        ForwardFactory $resultForwardFactory,
        PageFactory $resultPageFactory,
        RegionFactory $regionFactory,
        HelperData $helperData,
        ?CustomerRepositoryInterface $customerRepository
    ) {
        $this->regionFactory = $regionFactory;
        $this->helperData = $helperData;
        parent::__construct(
            $context,
            $customerSession,
            $formKeyValidator,
            $formFactory,
            $addressRepository,
            $addressDataFactory,
            $regionDataFactory,
            $dataProcessor,
            $dataObjectHelper,
            $resultForwardFactory,
            $resultPageFactory,
            $customerRepository
        );
    }

    /**
     * Extract address from request
     *
     * @param null $customer
     * @return \Magento\Customer\Api\Data\AddressInterface
     * @throws \Exception
     */
    protected function _extractAddress($customer = null)
    {
        $existingAddressData = $this->getExistingAddressData($customer);

        /** @var \Magento\Customer\Model\Metadata\Form $addressForm */
        $addressForm = $this->_formFactory->create(
            'customer_address',
            'customer_address_edit',
            $existingAddressData
        );
        $addressData = $addressForm->extractData($this->getRequest());
        $attributeValues = $addressForm->compactData($addressData);

        $this->updateRegionData($attributeValues);

        $addressDataObject = $this->addressDataFactory->create();
        $this->dataObjectHelper->populateWithArray(
            $addressDataObject,
            array_merge($existingAddressData, $attributeValues),
            \Magento\Customer\Api\Data\AddressInterface::class
        );
        $addressDataObject->setCustomerId($this->_getSession()->getCustomerId())
            ->setIsDefaultBilling($this->getRequest()->getParam('default_billing', false))
            ->setIsDefaultShipping($this->getRequest()->getParam('default_shipping', false));

        return $addressDataObject;
    }

    /**
     * Retrieve existing address data
     * @param $customer
     *
     * @return array
     * @throws \Exception
     */
    protected function getExistingAddressData($customer = null)
    {
        $existingAddressData = [];
        $addressId = $this->getRequest()->getParam('id');

        if ($customer) {
            foreach ($customer->getAddresses() as $customerAddress) {
                if ($customerAddress->getId() == $addressId) {
                    $existingAddressData = $this->getCustomerAddressMapper()->toFlatArray($customerAddress);
                }
            }

            if (empty($existingAddressData) && $addressId) {
                throw new \Exception();
            }

            return $existingAddressData;
        }

        if ($addressId) {
            $existingAddress = $this->_addressRepository->getById($addressId);
            if ($existingAddress->getCustomerId() !== $this->_getSession()->getCustomerId()) {
                throw new \Exception();
            }

            $existingAddressData = $this->getCustomerAddressMapper()->toFlatArray($existingAddress);
        }
        return $existingAddressData;
    }

    /**
     * Update region data
     *
     * @param array $attributeValues
     * @return void
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function updateRegionData(&$attributeValues)
    {
        if (!empty($attributeValues['region_id'])) {
            $newRegion = $this->regionFactory->create()->load($attributeValues['region_id']);
            $attributeValues['region_code'] = $newRegion->getCode();
            $attributeValues['region'] = $newRegion->getDefaultName();
        }

        $regionData = [
            RegionInterface::REGION_ID => !empty($attributeValues['region_id']) ? $attributeValues['region_id'] : null,
            RegionInterface::REGION => !empty($attributeValues['region']) ? $attributeValues['region'] : null,
            RegionInterface::REGION_CODE => !empty($attributeValues['region_code'])
                ? $attributeValues['region_code']
                : null,
        ];

        $region = $this->regionDataFactory->create();
        $this->dataObjectHelper->populateWithArray(
            $region,
            $regionData,
            \Magento\Customer\Api\Data\RegionInterface::class
        );
        $attributeValues['region'] = $region;
    }

    /**
     * Process address form save
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $redirectUrl = null;
        if (!$this->_formKeyValidator->validate($this->getRequest())) {
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        if (!$this->getRequest()->isPost()) {
            $this->_getSession()->setAddressFormData($this->getRequest()->getPostValue());
            return $this->resultRedirectFactory->create()->setUrl(
                $this->_redirect->error($this->_buildUrl('*/*/edit'))
            );
        }

        try {
            $this->saveAddress();
            $this->messageManager->addSuccessMessage(__('You saved the address.'));
            $url = $this->_buildUrl('*/*/index', ['_secure' => true]);
            return $this->resultRedirectFactory->create()->setUrl($this->_redirect->success($url));
        } catch (InputException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            foreach ($e->getErrors() as $error) {
                $this->messageManager->addErrorMessage($error->getMessage());
            }
        } catch (\Exception $e) {
            $redirectUrl = $this->_buildUrl('*/*/index');
            $this->messageManager->addExceptionMessage($e, __('We can\'t save the address.'));
        }

        $url = $redirectUrl;
        if (!$redirectUrl) {
            $this->_getSession()->setAddressFormData($this->getRequest()->getPostValue());
            $url = $this->_buildUrl('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
        }

        return $this->resultRedirectFactory->create()->setUrl($this->_redirect->error($url));
    }

    /**
     * process address save, based on two repositories
     */
    protected function saveAddress()
    {
        $addressId = $this->getRequest()->getParam('id');
        $customerId = $this->_getSession()->getCustomerId();

        if ($this->customerRepository) {
            $customer = $this->customerRepository->getById($customerId);

            $addresses = array_filter($customer->getAddresses(), function ($customerAddress) use ($addressId) {
                return $customerAddress->getId() !== $addressId;
            });

            $addresses[] = $this->_extractAddress($customer);
            $customer->setAddresses($addresses);
            $this->customerRepository->save($customer);

            return;
        }

        $this->_addressRepository->save($this->_extractAddress());
    }

    /**
     * Get Customer Address Mapper instance
     *
     * @return Mapper
     *
     * @deprecated 100.1.3
     */
    private function getCustomerAddressMapper()
    {
        if ($this->customerAddressMapper === null) {
            $this->customerAddressMapper = ObjectManager::getInstance()->get(
                \Magento\Customer\Model\Address\Mapper::class
            );
        }
        return $this->customerAddressMapper;
    }
}
