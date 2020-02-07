<?php

namespace Xigen\Faker\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

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
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Catalog\Api\Data\ProductInterfaceFactory $productInterfaceFactory
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepositoryInterface
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistryInterface
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Magento\Catalog\Api\Data\ProductLinkInterfaceFactory $productLinkInterfaceFactory
     * @param \Magento\Catalog\Model\Product\Gallery\Processor $galleryProcessor
     * @param \Magento\Framework\Filesystem\Io\File $file
     * @param \Magento\Framework\App\Filesystem\DirectoryList $directoryList
     * @param \Magento\Catalog\Api\CategoryLinkManagementInterface $categoryLinkManagementInterface
     * @param \Xigen\Faker\Helper\Category $categoryHelper
     * @param \Magento\CatalogInventory\Helper\Stock $stockFilter
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Catalog\Api\Data\ProductInterfaceFactory $productInterfaceFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepositoryInterface,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistryInterface,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Catalog\Api\Data\ProductLinkInterfaceFactory $productLinkInterfaceFactory,
        \Magento\Catalog\Model\Product\Gallery\Processor $galleryProcessor,
        \Magento\Framework\Filesystem\Io\File $file,
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList,
        \Magento\Catalog\Api\CategoryLinkManagementInterface $categoryLinkManagementInterface,
        \Xigen\Faker\Helper\Category $categoryHelper,
        \Magento\CatalogInventory\Helper\Stock $stockFilter
    ) {
        // https://packagist.org/packages/fzaninotto/faker
        $this->faker = \Faker\Factory::create(\Xigen\Faker\Helper\Data::LOCALE_CODE);
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
     * @return \Magento\Catalog\Model\Product\Interceptor
     */
    public function createProduct($websiteId = 1, $typeId = \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE, $applyImage)
    {
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
            ->setMetaKeyword(implode($this->faker->words(rand(10, 20)), ', '))
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
        }
    }

    /**
     * Reload product [might not actually need this].
     * @param $product
     * @return \Magento\Product\Model\Data\Product
     */
    public function reloadProduct(\Magento\Catalog\Model\Product\Interceptor $product)
    {
        if ($product && $product->getId()) {
            try {
                $product = $this->productRepositoryInterface->getById($product->getId());

                return $product;
            } catch (\Exception $e) {
                $this->logger->critical($e);
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
            \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE,
            \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE,
            \Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL,
            \Magento\Downloadable\Model\Product\Type::TYPE_DOWNLOADABLE,
            \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE,
            \Magento\GroupedProduct\Model\Product\Type\Grouped::TYPE_CODE,
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
    public function createProductLinkArray(\Magento\Catalog\Model\Product\Interceptor $product, $limit = 1)
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
        return $this->getRandomProduct($limit, $inStockOnly, $simpleOnly, [])->getAllIds();
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
            ->setPageSize($limit);

        if ($simpleOnly) {
            $collection->addAttributeToFilter('type_id', ['eq' => \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE]);
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
    public function addImages(\Magento\Catalog\Model\Product\Interceptor $product, $limit = 1)
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
                // $product->addImageToMediaGallery($newFileName . '.jpg', $imageType, true, $visible);
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
        return $this->directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA)
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
