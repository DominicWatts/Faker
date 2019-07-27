<?php

namespace Xigen\Faker\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Sales\Model\Service\InvoiceService;

/**
 * Order helper
 */
class Order extends AbstractHelper
{
    /**
     * @var \Faker\Generator
     */
    protected $faker;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Xigen\Faker\Helper\Customer
     */
    protected $customerHelper;

    /**
     * @var \Xigen\Faker\Helper\Product
     */
    protected $productHelper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $cartRepositoryInterface;

    /**
     * @var \Magento\Customer\Model\AddressFactory
     */
    protected $addressFactory;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepositoryInterface;

    /**
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @var \Magento\Sales\Model\Convert\Order
     */
    protected $convertOrder;

    /**
     * Order constructor.
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param Customer $customerHelper
     * @param Product $productHelper
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
     * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepositoryInterface
     * @param \Magento\Customer\Model\AddressFactory $addressFactory
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface
     * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
     * @param \Magento\Sales\Model\Convert\Order $convertOrder
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Xigen\Faker\Helper\Customer $customerHelper,
        \Xigen\Faker\Helper\Product $productHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepositoryInterface,
        \Magento\Customer\Model\AddressFactory $addressFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Model\Convert\Order $convertOrder
    ) {
        // https://packagist.org/packages/fzaninotto/faker
        $this->faker = \Faker\Factory::create(\Xigen\Faker\Helper\Data::LOCALE_CODE);
        $this->logger = $logger;
        $this->customerHelper = $customerHelper;
        $this->productHelper = $productHelper;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->addressFactory = $addressFactory;
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        $this->transactionFactory = $transactionFactory;
        $this->convertOrder = $convertOrder;

        parent::__construct($context);
    }

    /**
     * Create random product.
     * @param int $storeId
     * @return \Magento\Catalog\Model\Order\Interceptor
     */
    public function createOrder($storeId = 1)
    {

        // bypass Area code not set
        $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->cartManagementInterface = $this->_objectManager->create(CartManagementInterface::class);

        try {
            $store = $this->storeManagerInterface->getStore($storeId);
        } catch (\Exception $e) {
            $this->logger->critical($e);

            return;
        }

        $websiteId = $store->getWebsiteId();
        $customerIds = $this->getRandomCustomerId($websiteId);
        if (empty($customerIds)) {
            new \Exception(__('Please add some customers for this store first'));
        }

        $customer = $this->getCustomerById($customerIds[0]);
        if (!$customer) {
            new \Exception(__('Problem loading customer'));
        }

        $cartId = $this->cartManagementInterface->createEmptyCart(); //Create empty cart
        $quote = $this->cartRepositoryInterface->get($cartId); // load empty cart quote
        $quote->setStore($store);
        $quote->setCurrency();
        $quote->assignCustomer($customer);

        $productIds = $this->getRandomProductId(rand(1, 10));

        if (empty($productIds)) {
            new \Exception(__('Please add some produts for this store first'));
        }

        foreach ($productIds as $productId) {
            $product = $this->getProductById($productId);
            $product->setStore($store);
            $product->setPrice($this->faker->randomFloat(4, 0, 100));
            $quote->addProduct($product, (int) (rand(1, 2)));
        }

        $billingAddress = $this->addressFactory->create()->load($customer->getDefaultBilling());
        $shippingAddress = $this->addressFactory->create()->load($customer->getDefaultShipping());

        $quote->getBillingAddress()->addData($billingAddress->getData());
        $quote->getShippingAddress()->addData($shippingAddress->getData());

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod('flatrate_flatrate');

        $quote->setPaymentMethod('checkmo');
        $quote->setInventoryProcessed(false);

        $quote->getPayment()->importData(['method' => 'checkmo']);

        try {
            $this->cartRepositoryInterface->save($quote);
        } catch (\Exception $e) {
            $this->logger->critical($e);

            return;
        }

        $quote->collectTotals();
        $quote = $this->cartRepositoryInterface->get($quote->getId());

        try {
            $orderId = $this->cartManagementInterface->placeOrder($quote->getId());
            $this->generateInvoice($orderId);
            if ($this->getRandomTrueOrFalse()) {
                $this->generateShipment($orderId, $this->getRandomTrueOrFalse());
            }

            return $orderId;
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
    }

    /**
     * Generate invoice from order Id.
     * @param int $orderId
     * @return void
     */
    public function generateInvoice($orderId)
    {
        try {
            $order = $this->getById($orderId);
            if (!$order || !$order->getId() || !$order->canInvoice()) {
                return;
            }

            $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $this->invoiceService = $this->_objectManager->create(InvoiceService::class);

            $invoice = $this->invoiceService->prepareInvoice($order);
            if (!$invoice || !$invoice->getTotalQty()) {
                return;
            }

            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            $invoice->getOrder()->setCustomerNoteNotify(false);
            $invoice->getOrder()->setIsInProcess(true);
            $order->addStatusHistoryComment('Automatically INVOICED', false);
            $transactionSave = $this->transactionFactory
                ->create()
                ->addObject($invoice)
                ->addObject($invoice->getOrder());

            $transactionSave->save();
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
    }

    /**
     * Generate shipment from order ID.
     * @param int $orderId
     * @return void
     */
    public function generateShipment($orderId, $doNotify = true)
    {
        $order = $this->getById($orderId);

        if (!$order || !$order->canShip()) {
            return;
        }

        try {
            $orderShipment = $this->convertOrder->toShipment($order);

            foreach ($order->getAllItems() as $orderItem) {
                if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                    continue;
                }
                $shipmentItem = $this->convertOrder
                    ->itemToShipmentItem($orderItem)
                    ->setQty($orderItem->getQtyToShip());

                $orderShipment->addItem($shipmentItem);
            }

            $orderShipment->register();
            $orderShipment->getOrder()->setIsInProcess(true);

            // Save created Order Shipment
            $orderShipment->save();
            $orderShipment->getOrder()->save();

            if ($doNotify) {
                $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $this->_objectManager->create(\Magento\Shipping\Model\ShipmentNotifier::class)
                    ->notify($orderShipment);
                $orderShipment->save();
            }
            $order->addStatusToHistory($order->getStatus(), 'Order has been marked as complete');
            $order->save();

            return true;

        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
    }

    /**
     * Randomise true or false.
     * @return bool
     */
    public function getRandomTrueOrFalse()
    {
        return $this->productHelper->getRandomTrueOrFalse();
    }

    /**
     * Return array of random Customer IDs.
     * @param int $limit
     * @param int $websiteId
     * @return array
     */
    public function getRandomCustomerId($limit = 1, $websiteId = 1)
    {
        return $this->customerHelper->getRandomCustomerId($limit, $websiteId);
    }

    /**
     * Return collection of random customers.
     * @param int $limit
     * @param int $websiteId
     * @return \Magento\Catalog\Model\ResourceModel\Customer\Collection
     */
    public function getRandomCustomerCollection($limit = 1, $websiteId = 1)
    {
        return $this->customerHelper->getRandomCustomer($limit, $websiteId);
    }

    /**
     * Return single random customer.
     * @param int $websiteId
     * @return \Magento\Customer\Model\Customer\Interceptor
     */
    public function getRandomCustomer($websiteId = 1)
    {
        $collection = $this->getRandomCustomerCollection(1, $websiteId);
        if ($collection && $collection->getSize() > 0) {
            return $collection->getFirstItem();
        }

        return false;
    }

    /**
     * Return array of random product IDs.
     * @param int $limit
     * @return array
     */
    public function getRandomProductId($limit = 1)
    {
        return $this->productHelper->getRandomIds($limit);
    }

    /**
     * Get product by Id.
     * @param int $productId
     * @param bool $editMode
     * @param int $storeId
     * @param bool $forceReload
     * @return \Magento\Catalog\Model\Data\Product
     */
    public function getProductById($productId, $editMode = false, $storeId = null, $forceReload = false)
    {
        return $this->productHelper->getById($productId, $editMode, $storeId, $forceReload);
    }

    /**
     * Get customer by Id.
     * @param int $customerId
     * @return \Magento\Catalog\Model\Data\Product
     */
    public function getCustomerById($customerId)
    {
        return $this->customerHelper->getById($customerId);
    }

    /**
     * Get order by Id.
     * @param int $orderId
     * @return \Magento\Sales\Model\Data\Order
     */
    public function getById($orderId)
    {
        try {
            return $this->orderRepositoryInterface->get($orderId);
        } catch (\Exception $e) {
            $this->logger->critical($e);

            return false;
        }
    }
}
