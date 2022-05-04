<?php

namespace Xigen\Faker\Helper;

use Faker\Factory as Faker;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Review helper
 */
class Review extends AbstractHelper
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
     * @var \Magento\Review\Model\ResourceModel\Review\CollectionFactory
     */
    protected $reviewCollectionFactory;

    /**
     * @var \Magento\Review\Model\ReviewFactory
     */
    protected $reviewFactory;

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
     * Review constructor.
     * @param Context $context
     * @param LoggerInterface $logger
     * @param CollectionFactory $reviewCollectionFactory
     * @param ReviewFactory $reviewFactory
     * @param \Xigen\Faker\Helper\Customer $customerHelper
     * @param \Xigen\Faker\Helper\Product $productHelper
     * @param StoreManagerInterface $storeManagerInterface
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        CollectionFactory $reviewCollectionFactory,
        ReviewFactory $reviewFactory,
        Customer $customerHelper,
        Product $productHelper,
        StoreManagerInterface $storeManagerInterface
    ) {
        $this->faker = Faker::create(Data::LOCALE_CODE);
        $this->logger = $logger;
        $this->reviewCollectionFactory = $reviewCollectionFactory;
        $this->reviewFactory = $reviewFactory;
        $this->customerHelper = $customerHelper;
        $this->productHelper = $productHelper;
        $this->storeManagerInterface = $storeManagerInterface;
        parent::__construct($context);
    }

    /**
     * Create random review.
     * @param int $storeId
     * @return mixed
     */
    public function createReview($storeId = 1)
    {
        $product = $this->productHelper
            ->getRandomIds(1);

        $website = null;
        $stores = $this->storeManagerInterface->getStores(true, false);
        foreach ($stores as $store) {
            if ($store->getStoreId() == $storeId) {
                $website = $store->getWebsiteId();
            }
        }

        $customer = $this->customerHelper
            ->getRandomCustomerId(1, $website);

        $review = $this->reviewFactory
            ->create()
            ->setEntityPkValue($product[0])
            ->setStatusId(rand(1, 3))
            ->setTitle(ucwords($this->faker->words(rand(1, 5), true)))
            ->setDetail($this->faker->paragraphs(rand(1, 4), true))
            ->setEntityId(1)
            ->setStoreId($storeId)
            ->setStores(1)
            ->setCustomerId($customer[0])
            ->setNickname($this->faker->firstName)
            ->save();

        try {
            $review->save();
            return $review;
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
        return false;
    }
}
