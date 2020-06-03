<?php

namespace Xigen\Faker\Helper;

use Faker\Factory as Faker;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterfaceFactory;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Psr\Log\LoggerInterface;

/**
 * Category helper.
 */
class Category extends AbstractHelper
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
     * @var \Magento\Catalog\Api\Data\CategoryInterfaceFactory
     */
    protected $categoryInterfaceFactory;

    /**
     * @var \Magento\Catalog\Api\CategoryRepositoryInterface
     */
    protected $categoryRepositoryInterface;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * @var \Magento\Catalog\Model\CategoryFactory
     */
    protected $categoryFactory;

    /**
     * Category constructor.
     * @param Context $context
     * @param LoggerInterface $logger
     * @param CategoryInterfaceFactory $categoryInterfaceFactory
     * @param CategoryRepositoryInterface $categoryRepositoryInterface
     * @param CollectionFactory $categoryCollectionFactory
     * @param CategoryFactory $categoryFactory
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        CategoryInterfaceFactory $categoryInterfaceFactory,
        CategoryRepositoryInterface $categoryRepositoryInterface,
        CollectionFactory $categoryCollectionFactory,
        CategoryFactory $categoryFactory
    ) {
        $this->faker = Faker::create(Data::LOCALE_CODE);
        $this->logger = $logger;
        $this->categoryInterfaceFactory = $categoryInterfaceFactory;
        $this->categoryRepositoryInterface = $categoryRepositoryInterface;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->categoryFactory = $categoryFactory;
        parent::__construct($context);
    }

    /**
     * Create random category
     * @param int $storeId
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createCategory($storeId = 0)
    {
        $parent = $this->getRandomCategory(1);
        if ($parent && $parent->getSize() > 0) {
            $parent = $parent->getFirstItem();

            $category = $this->categoryFactory
                ->create()
                ->setName(ucwords($this->faker->words(rand(1, 5), true)))
                ->setDecription($this->faker->paragraphs(rand(1, 4), true))
                ->setIsActive(rand(1, 2))
                ->setIncludeInMenu(rand(1, 2))
                ->setParentId($parent->getId())
                ->setPath($parent->getPath())
                ->setStoreId($storeId);

            try {
                $category->save();

                return $category;
            } catch (\Exception $e) {
                $this->logger->critical($e);
            }
        }
    }

    /**
     * Return array of random IDs.
     * @param int $limit
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getRandomCategoryId($limit = 1)
    {
        $categories = $this->getRandomCategory($limit);
        $ids = [];
        foreach ($categories as $category) {
            $ids[$category->getId()] = $category->getId();
        }

        return $ids;
    }

    /**
     * Return collection of random categories.
     * @param int $limit
     * @return \Magento\Catalog\Model\ResourceModel\Category\Collection
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getRandomCategory($limit = 1)
    {
        $collection = $this->categoryCollectionFactory
            ->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('entity_id', ['gt' => 1])
            ->setPageSize($limit);

        $collection->getSelect()->order('RAND()');

        return $collection;
    }
}
