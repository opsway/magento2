<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Customer\Test\Unit\Controller\Address;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Controller\Address\Delete;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DeleteTest extends \PHPUnit\Framework\TestCase
{
    /** @var Delete */
    protected $model;

    /** @var \Magento\Framework\App\Action\Context */
    protected $context;

    /** @var \Magento\Customer\Model\Session|\PHPUnit_Framework_MockObject_MockObject */
    protected $sessionMock;

    /** @var \Magento\Framework\Data\Form\FormKey\Validator|\PHPUnit_Framework_MockObject_MockObject */
    protected $validatorMock;

    /**
     * @var \Magento\Customer\Api\AddressRepositoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $addressRepository;

    /** @var \Magento\Customer\Api\CustomerRepositoryInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $customerRepository;

    /** @var \Magento\Framework\App\RequestInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $request;

    /** @var \Magento\Customer\Api\Data\AddressInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $address;

    /** @var \Magento\Framework\Message\ManagerInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $messageManager;

    /** @var \Magento\Framework\Controller\Result\RedirectFactory|\PHPUnit_Framework_MockObject_MockObject */
    protected $resultRedirectFactory;

    /** @var \Magento\Framework\Controller\Result\Redirect|\PHPUnit_Framework_MockObject_MockObject */
    protected $resultRedirect;

    /** @var  \Magento\Customer\Model\Customer */
    protected $customer;

    protected function setUp()
    {
        $this->sessionMock = $this->getMockBuilder(\Magento\Customer\Model\Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->validatorMock = $this->getMockBuilder(\Magento\Framework\Data\Form\FormKey\Validator::class)
            ->disableOriginalConstructor()
            ->getMock();
        $formFactoryMock = $this->getMockBuilder(\Magento\Customer\Model\Metadata\FormFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->addressRepository = $this->getMockBuilder(\Magento\Customer\Api\AddressRepositoryInterface::class)
            ->getMockForAbstractClass();
        $this->customerRepository = $this->getMockBuilder(\Magento\Customer\Api\CustomerRepositoryInterface::class)
            ->getMockForAbstractClass();
        $addressInterfaceFactoryMock = $this->getMockBuilder(\Magento\Customer\Api\Data\AddressInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $regionInterfaceFactoryMock = $this->getMockBuilder(\Magento\Customer\Api\Data\RegionInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $dataObjectProcessorMock = $this->getMockBuilder(\Magento\Framework\Reflection\DataObjectProcessor::class)
            ->disableOriginalConstructor()
            ->getMock();
        $dataObjectHelperMock = $this->getMockBuilder(\Magento\Framework\Api\DataObjectHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $forwardFactoryMock = $this->getMockBuilder(\Magento\Framework\Controller\Result\ForwardFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $pageFactoryMock = $this->getMockBuilder(\Magento\Framework\View\Result\PageFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->request = $this->getMockBuilder(\Magento\Framework\App\RequestInterface::class)
            ->getMockForAbstractClass();
        $this->address = $this->getMockBuilder(\Magento\Customer\Api\Data\AddressInterface::class)
            ->getMockForAbstractClass();
        $this->messageManager = $this->getMockBuilder(\Magento\Framework\Message\ManagerInterface::class)
            ->getMockForAbstractClass();
        $this->resultRedirectFactory =
            $this->getMockBuilder(\Magento\Framework\Controller\Result\RedirectFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->resultRedirect = $this->getMockBuilder(\Magento\Framework\Controller\Result\Redirect::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->customer = $this->prophesize(CustomerInterface::class);

        $objectManager = new ObjectManagerHelper($this);
        $this->context = $objectManager->getObject(
            \Magento\Framework\App\Action\Context::class,
            [
                'request' => $this->request,
                'messageManager' => $this->messageManager,
                'resultRedirectFactory' => $this->resultRedirectFactory,
            ]
        );

        $this->model = new Delete(
            $this->context,
            $this->sessionMock,
            $this->validatorMock,
            $formFactoryMock,
            $this->addressRepository,
            $addressInterfaceFactoryMock,
            $regionInterfaceFactoryMock,
            $dataObjectProcessorMock,
            $dataObjectHelperMock,
            $forwardFactoryMock,
            $pageFactoryMock,
            $this->customerRepository
        );
    }

    public function testExecute()
    {
        $addressId = 1;
        $customerId = 2;

        $this->resultRedirectFactory->expects($this->once())
            ->method('create')
            ->willReturn($this->resultRedirect);
        $this->request->expects($this->once())
            ->method('getParam')
            ->with('id', false)
            ->willReturn($addressId);
        $this->validatorMock->expects($this->once())
            ->method('validate')
            ->with($this->request)
            ->willReturn(true);
        $this->sessionMock->expects($this->once())
            ->method('getCustomerId')
            ->willReturn($customerId);
        $this->customerRepository->expects($this->once())
            ->method('getById')
            ->with($customerId)
            ->willReturn($this->customer->reveal());
        $this->customer->getAddresses()
            ->shouldBeCalled()
            ->willReturn([$this->address]);
        $this->address->expects($this->once())
            ->method('getId')
            ->willReturn($addressId);
        $this->customer->setAddresses()
            ->withArguments([[]])
            ->shouldBeCalled()
            ->willReturn($this->customer->reveal());
        $this->customerRepository->expects($this->once())
            ->method('save')
            ->with($this->customer->reveal())
            ->willReturn($this->customer->reveal());
        $this->messageManager->expects($this->once())
            ->method('addSuccess')
            ->with(__('You deleted the address.'));
        $this->resultRedirect->expects($this->once())
            ->method('setPath')
            ->with('*/*/index')
            ->willReturnSelf();
        $this->assertSame($this->resultRedirect, $this->model->execute());
    }

    public function testExecuteWithException()
    {
        $addressId = 1;
        $customerId = 2;

        $this->resultRedirectFactory->expects($this->once())
            ->method('create')
            ->willReturn($this->resultRedirect);
        $this->request->expects($this->once())
            ->method('getParam')
            ->with('id', false)
            ->willReturn($addressId);
        $this->validatorMock->expects($this->once())
            ->method('validate')
            ->with($this->request)
            ->willReturn(true);
        $this->sessionMock->expects($this->once())
            ->method('getCustomerId')
            ->willReturn($customerId);
        $this->customerRepository->expects($this->once())
            ->method('getById')
            ->with($customerId)
            ->willReturn($this->customer->reveal());
        $this->customer->getAddresses()
            ->shouldBeCalled()
            ->willReturn([$this->address]);
        $this->address->expects($this->once())
            ->method('getId')
            ->willReturn(3);
        $this->messageManager->expects($this->once())
            ->method('addError')
            ->with(__('We can\'t delete the address right now.'));
        $this->resultRedirect->expects($this->once())
            ->method('setPath')
            ->with('*/*/index')
            ->willReturnSelf();
        $this->assertSame($this->resultRedirect, $this->model->execute());
    }
}
