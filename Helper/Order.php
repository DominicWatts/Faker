<?php

namespace Xigen\Faker\Helper;

use Faker\Factory as Faker;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\Customer\Model\AddressFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DB\TransactionFactory;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Convert\Order as ConvertOrder;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Shipping\Model\ShipmentNotifier;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

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
     * @var \Magento\CatalogInventory\Api\StockStateInterface
     */
    protected $stockItem;

    /**
     * @var \Magento\Framework\App\ObjectManager
     */
    protected $_objectManager;

    /**
     * @var Magento\Quote\Api\CartManagementInterface
     */
    protected $cartManagementInterface;

    /**
     * @var Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * Order constructor.
     * @param Context $context
     * @param LoggerInterface $logger
     * @param \Xigen\Faker\Helper\Customer $customerHelper
     * @param \Xigen\Faker\Helper\Product $productHelper
     * @param StoreManagerInterface $storeManagerInterface
     * @param CartRepositoryInterface $cartRepositoryInterface
     * @param AddressFactory $addressFactory
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param TransactionFactory $transactionFactory
     * @param ConvertOrder $convertOrder
     * @param StockStateInterface $stockItem
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        Customer $customerHelper,
        Product $productHelper,
        StoreManagerInterface $storeManagerInterface,
        CartRepositoryInterface $cartRepositoryInterface,
        AddressFactory $addressFactory,
        OrderRepositoryInterface $orderRepositoryInterface,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        ConvertOrder $convertOrder,
        StockStateInterface $stockItem
    ) {
        $this->faker = Faker::create(Data::LOCALE_CODE);
        $this->logger = $logger;
        $this->customerHelper = $customerHelper;
        $this->productHelper = $productHelper;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->addressFactory = $addressFactory;
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        $this->transactionFactory = $transactionFactory;
        $this->convertOrder = $convertOrder;
        $this->stockItem = $stockItem;

        parent::__construct($context);
    }

    /**
     * Create random order
     * @param int $storeId
     * @return \Magento\Catalog\Model\Order\Interceptor
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function createOrder($storeId = 1)
    {

        // bypass Area code not set
        $this->_objectManager = ObjectManager::getInstance();
        $this->cartManagementInterface = $this->_objectManager->create(CartManagementInterface::class);

        try {
            $store = $this->storeManagerInterface->getStore($storeId);
            
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

            $limit = rand(1, 10);
            $productIds = $this->getRandomProductId($limit, true);

            if (empty($productIds)) {
                new \Exception(__('Please add some produts for this store first'));
            }

            $added = 0;
            foreach ($productIds as $productId) {
                
                $product = $this->getProductById($productId);
                if ($product->isSalable()) {
                    $qty = $this->stockItem->getStockQty($product->getId(), $websiteId);
                    if ($qty > 1 && $added < $limit) {
                        $product->setStore($store);
                        $product->setPrice($this->faker->randomFloat(4, 0, 100));
                        $quote->addProduct($product, (int) (rand(1, 2)));
                        $added++;
                    }
                }
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

            $this->cartRepositoryInterface->save($quote);

            $quote->collectTotals();
            $quote = $this->cartRepositoryInterface->get($quote->getId());
      
            $orderId = $this->cartManagementInterface->placeOrder($quote->getId());
            $this->generateInvoice($orderId);
            if ($this->getRandomTrueOrFalse()) {
                $this->generateShipment($orderId, $this->getRandomTrueOrFalse());
            }

            return $orderId;

        } catch (\Exception $e) {
            $this->logger->critical($e);
            return $e->getMessage();
        }
    }

    /**
     * Generate invoice from order Id.
     * @param int $orderId
     * @return void
     */
    public function generateInvoice($orderId)
    {
        $order = $this->getById($orderId);
        if (!$order || !$order->getId() || !$order->canInvoice()) {
            return;
        }

        $this->_objectManager = ObjectManager::getInstance();
        $this->invoiceService = $this->_objectManager->create(InvoiceService::class);

        $invoice = $this->invoiceService->prepareInvoice($order);
        if (!$invoice || !$invoice->getTotalQty()) {
            return;
        }

        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
        $invoice->register();
        $invoice->getOrder()->setCustomerNoteNotify(false);
        $invoice->getOrder()->setIsInProcess(true);
        $order->addStatusHistoryComment('Automatically INVOICED', false);
        $transactionSave = $this->transactionFactory
            ->create()
            ->addObject($invoice)
            ->addObject($invoice->getOrder());

        $transactionSave->save();
    }

    /**
     * Generate shipment from order ID.
     * @param $orderId
     * @param bool $doNotify
     * @return bool|void
     */
    public function generateShipment($orderId, $doNotify = true)
    {
        $order = $this->getById($orderId);

        if (!$order || !$order->canShip()) {
            return;
        }
    
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
            $this->_objectManager = ObjectManager::getInstance();
            $this->_objectManager->create(ShipmentNotifier::class)
                ->notify($orderShipment);
            $orderShipment->save();
        }
        $order->addStatusToHistory($order->getStatus(), 'Order has been marked as complete');
        $order->save();

        return true;
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
     * @throws \Magento\Framework\Exception\LocalizedException
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
     * @param bool $inStockOnly
     * @return array
     */
    public function getRandomProductId($limit = 1, $inStockOnly = false, $simpleOnly = true)
    {
        return $this->productHelper->getRandomIds($limit, $inStockOnly, $simpleOnly);
    }

    /**
     * Get product by Id.
     * @param $productId
     * @param bool $editMode
     * @param null $storeId
     * @param bool $forceReload
     * @return bool|\Magento\Catalog\Model\Data\Product
     */
    public function getProductById($productId, $editMode = false, $storeId = null, $forceReload = false)
    {
        return $this->productHelper->getById($productId, $editMode, $storeId, $forceReload);
    }

    /**
     * Get customer by Id.
     * @param $customerId
     * @return bool|\Magento\Customer\Api\Data\CustomerInterface
     */
    public function getCustomerById($customerId)
    {
        return $this->customerHelper->getById($customerId);
    }

    /**
     * Get order by Id.
     * @param $orderId
     * @return bool|\Magento\Sales\Api\Data\OrderInterface
     */
    public function getById($orderId)
    {
        return $this->orderRepositoryInterface->get($orderId);
    }
}
