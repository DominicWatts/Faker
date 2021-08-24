<?php

namespace Xigen\Faker\Helper;

use Faker\Factory as Faker;
use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\Data\ProductLinkInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Gallery\Processor;
use Magento\Catalog\Model\Product\Interceptor;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Helper\Stock;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Downloadable\Model\Product\Type as Downloadable;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Filesystem\Io\File;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Psr\Log\LoggerInterface;

/**
 * Product helper
 */
class Product extends AbstractHelper
{
    const ATTRIBUTE_SET_ID = 4;
    const PLACEHOLDER_SOURCE = 'https://raw.githubusercontent.com/DominicWatts/ImageFetch/master/1000/';

    /**
     * @var \Faker\Generator
     */
    protected $faker;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Catalog\Api\Data\ProductInterfaceFactory
     */
    protected $productInterfaceFactory;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepositoryInterface;

    /**
     * @var \Magento\CatalogInventory\Api\StockRegistryInterface
     */
    protected $stockRegistryInterface;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var \Magento\Catalog\Api\Data\ProductLinkInterfaceFactory
     */
    protected $productLinkInterfaceFactory;

    /**
     * @var \Magento\Catalog\Model\Product\Gallery\Processor
     */
    protected $galleryProcessor;

    /**
     * @var \Magento\Framework\Filesystem\Io\File
     */
    protected $file;

    /**
     * @var \Magento\Framework\App\Filesystem\DirectoryList
     */
    protected $directoryList;

    /**
     * @var \Magento\Catalog\Api\CategoryLinkManagementInterface
     */
    protected $categoryLinkManagementInterface;

    /**
     * @var \Xigen\Faker\Helper\Category
     */
    protected $categoryHelper;

    /**
     * @var \Magento\CatalogInventory\Helper\Stock
     */
    protected $stockFilter;

    /**
     * Product constructor.
     * @param Context $context
     * @param LoggerInterface $logger
     * @param ProductInterfaceFactory $productInterfaceFactory
     * @param ProductRepositoryInterface $productRepositoryInterface
     * @param StockRegistryInterface $stockRegistryInterface
     * @param CollectionFactory $productCollectionFactory
     * @param ProductLinkInterfaceFactory $productLinkInterfaceFactory
     * @param Processor $galleryProcessor
     * @param File $file
     * @param DirectoryList $directoryList
     * @param CategoryLinkManagementInterface $categoryLinkManagementInterface
     * @param \Xigen\Faker\Helper\Category $categoryHelper
     * @param Stock $stockFilter
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        ProductInterfaceFactory $productInterfaceFactory,
        ProductRepositoryInterface $productRepositoryInterface,
        StockRegistryInterface $stockRegistryInterface,
        CollectionFactory $productCollectionFactory,
        ProductLinkInterfaceFactory $productLinkInterfaceFactory,
        Processor $galleryProcessor,
        File $file,
        DirectoryList $directoryList,
        CategoryLinkManagementInterface $categoryLinkManagementInterface,
        Category $categoryHelper,
        Stock $stockFilter
    ) {
        $this->faker = Faker::create(Data::LOCALE_CODE);
        $this->logger = $logger;
        $this->productInterfaceFactory = $productInterfaceFactory;
        $this->productRepositoryInterface = $productRepositoryInterface;
        $this->stockRegistryInterface = $stockRegistryInterface;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productLinkInterfaceFactory = $productLinkInterfaceFactory;
        $this->galleryProcessor = $galleryProcessor;
        $this->file = $file;
        $this->directoryList = $directoryList;
        $this->categoryLinkManagementInterface = $categoryLinkManagementInterface;
        $this->categoryHelper = $categoryHelper;
        $this->stockFilter = $stockFilter;
        parent::__construct($context);
    }

    /**
     * Create random product.
     * @param int $websiteId
     * @param string $typeId
     * @param bool $applyImage
     * @return \Magento\Catalog\Model\Product\Interceptor|false
     */
    public function createProduct(
        $websiteId = 1,
        $typeId = ProductType::TYPE_SIMPLE,
        $applyImage = true
    ) {
        if (!in_array($typeId, $this->getTypeArray())) {
            return;
        }

        $quantity = rand(1, 20);
        $product = $this->productInterfaceFactory
            ->create()
            ->setSku('FAKER-' . strtoupper($this->faker->word) . '-' . rand(1000, 9999))
            ->setName(ucwords($this->faker->words(rand(1, 5), true)))
            ->setDecription($this->faker->paragraphs(rand(1, 4), true))
            ->setShortDescription($this->faker->paragraph)
            ->setMetaTitle(ucwords($this->faker->words(rand(1, 4), true)))
            ->setMetaKeyword(implode(', ', $this->faker->words(rand(10, 20))))
            ->setMetaDescription($this->faker->text(200))
            ->setWeight($this->faker->randomFloat(4, 0, 10))
            ->setStatus(rand(1, 2))
            ->setTaxClassId(rand(1, 4))
            ->setManufacturer($this->faker->word)
            ->setColor($this->faker->word)
            ->setTypeId($typeId)
            ->setVisibility(rand(1, 4))
            ->setPrice($this->faker->randomFloat(4, 0, 100))
            ->setCost($this->faker->randomFloat(4, 0, 100))
            ->setAttributeSetId(self::ATTRIBUTE_SET_ID)
            ->setWebsiteIds([$websiteId])
            ->setStockData([
                'is_in_stock' => $quantity > 0 ? 1 : 0,
                'qty' => $quantity,
            ]);

        if ($this->getRandomTrueOrFalse()) {
            $linkData = $this->createProductLinkArray($product, rand(1, 10));
            $product->setProductLinks($linkData);
        }

        // images causes entire process to hang
        if ($applyImage) {
            $this->addImages($product, rand(1, 4));
        }

        try {
            $product = $this->productRepositoryInterface->save($product);

            if ($this->getRandomTrueOrFalse()) {
                $categoryIds = $this->categoryHelper->getRandomCategoryId(rand(1, 3));
                $this->assignProductToCategory($product, $categoryIds);
            }

            return $product;
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
        }
    }

    /**
     * Reload product [might not actually need this].
     * @param $product
     * @return \Magento\Product\Model\Data\Product|false
     */
    public function reloadProduct(Interceptor $product)
    {
        if ($product && $product->getId()) {
            try {
                $product = $this->productRepositoryInterface->getById($product->getId());

                return $product;
            } catch (\Exception $e) {
                $this->logger->critical($e);
                return false;
            }
        }
    }

    /**
     * Array of valid product types.
     * @return array
     */
    public function getTypeArray()
    {
        return [
            ProductType::TYPE_SIMPLE,
            ProductType::TYPE_BUNDLE,
            ProductType::TYPE_VIRTUAL,
            Downloadable::TYPE_DOWNLOADABLE,
            Configurable::TYPE_CODE,
            Grouped::TYPE_CODE,
        ];
    }

    /**
     * Get random link string.
     * @return string
     */
    public function getRandomLink()
    {
        $array = [
            'related',
            'crosssell',
            'upsell',
        ];
        $randomIndex = array_rand($array);

        return $array[$randomIndex];
    }

    /**
     * Create array of random product links.
     * @param \Magento\Product\Model\Data\Product $product
     * @param int $limit
     * @return array
     */
    public function createProductLinkArray(Interceptor $product, $limit = 1)
    {
        $linkData = [];
        $linkSkus = $this->getRandomSku($limit);
        foreach ($linkSkus as $productId => $sku) {
            $productLink = $this->productLinkInterfaceFactory
                ->create()
                ->setSku($product->getSku())
                ->setLinkedProductSku($sku)
                ->setPosition($productId)
                ->setLinkType($this->getRandomLink());
            $linkData[] = $productLink;
        }

        return $linkData;
    }

    /**
     * Randomise true or false.
     * @return bool
     */
    public function getRandomTrueOrFalse()
    {
        return (bool) rand(0, 1);
    }

    /**
     * Return array of random SKUs.
     * @param int $limit
     * @return array
     */
    public function getRandomSku($limit = 1)
    {
        $products = $this->getRandomProduct($limit, false, true, ['sku']);
        $skus = [];
        foreach ($products as $product) {
            $skus[] = $product->getSku();
        }

        return $skus;
    }

    /**
     * Return array of random IDs.
     * @param int $limit
     * @param bool $inStockOnly
     * @return array
     */
    public function getRandomIds($limit = 1, $inStockOnly = false, $simpleOnly = true)
    {
        $products = $this->getRandomProduct($limit, $inStockOnly, $simpleOnly, ['entity_id']);
        $ids = [];
        foreach ($products as $product) {
            $ids[] = $product->getId();
        }

        return $ids;
    }

    /**
     * Return collection of random products.
     * @param int $limit
     * @param bool $inStockOnly
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function getRandomProduct($limit = 1, $inStockOnly = false, $simpleOnly = true, $attributes = [])
    {
        $collection = $this->productCollectionFactory
            ->create()
            ->addAttributeToSelect(empty($attributes) ? '*' : $attributes)
            ->setPageSize($limit)
            ->setCurPage(1);

        if ($simpleOnly) {
            $collection->addAttributeToFilter('type_id', ['eq' => ProductType::TYPE_SIMPLE]);
        }

        if ($inStockOnly) {
            $this->stockFilter->addInStockFilterToCollection($collection);
        }

        $collection->getSelect()->order('RAND()');

        return $collection;
    }

    /**
     * Add dummy images.
     * @param \Magento\Catalog\Model\Product\Interceptor $product
     * @param int $limit
     * @return void
     */
    public function addImages(Interceptor $product, $limit = 1)
    {
        for ($generate = 1; $generate <= $limit; $generate++) {
            $imageUrl = self::PLACEHOLDER_SOURCE . 'image-' . str_pad(rand(1, 20), 3, 0, STR_PAD_LEFT) . '.jpg';
            $this->addImageFromUrl($product, $imageUrl, true, $generate == 1
                ? ['image', 'small_image', 'thumbnail'] : []);
        }
    }

    /**
     * Add image from Url.
     * @param Product $product
     * @param string $imageUrl
     * @param array $imageType
     * @param bool $visible
     * @return bool
     */
    public function addImageFromUrl($product, $imageUrl, $visible = false, $imageType = [])
    {
        $tmpDir = $this->getMediaDirTmpDir();
        $this->file->checkAndCreateFolder($tmpDir);
        $pathInfo = $this->file->getPathInfo($imageUrl);
        $newFileName = $tmpDir . $pathInfo["basename"];
        /** read file from URL and copy it to the new destination */
        $result = $this->file->read($imageUrl, $newFileName);
        $this->file->cp($newFileName, $newFileName . '.jpg');
        if ($result) {
            try {
                $this->galleryProcessor->addImage($product, $newFileName . '.jpg', $imageType, false, $visible);
            } catch (\Exception $e) {
                $this->logger->critical($e);
            }
        }

        return $result;
    }

    /**
     * Media directory name for the temporary file storage pub/media/tmp.
     * @return string
     */
    protected function getMediaDirTmpDir()
    {
        return $this->directoryList->getPath(DirectoryList::MEDIA)
            . DIRECTORY_SEPARATOR . 'xigen' . DIRECTORY_SEPARATOR;
    }

    /**
     * Assign product to category.
     * @param \Magento\Catalog\Model\Product $product
     * @param array $categoryIds
     * @return void
     */
    public function assignProductToCategory($product, $categoryIds = [])
    {
        if (!empty($categoryIds)) {
            $this->categoryLinkManagementInterface->assignProductToCategories(
                $product->getSku(),
                $categoryIds
            );
        }
    }

    /**
     * Get product by Id.
     * @param int $productId
     * @param bool $editMode
     * @param int $storeId
     * @param bool $forceReload
     * @return \Magento\Catalog\Model\Data\Product
     */
    public function getById($productId, $editMode = false, $storeId = null, $forceReload = false)
    {
        try {
            return $this->productRepositoryInterface->getById($productId, $editMode, $storeId, $forceReload);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
        }
    }
}
