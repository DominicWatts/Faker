<?php

namespace Xigen\Faker\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

/**
 * Product helper
 */
class Product extends AbstractHelper
{
    const COUNTRY_CODE = 'GB';
    const LOCALE_CODE = 'en_GB';
    const ATTRIBUTE_SET_ID = 4;
    const PLACEHOLDER_SOURCE = 'http://lorempixel.com/1000/1000';

    private $faker;
    private $logger;
    private $productInterfaceFactory;
    private $productRepositoryInterface;
    private $stockRegistryInterface;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
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
        \Xigen\Faker\Helper\Category $categoryHelper
    ) {
        $this->faker = \Faker\Factory::create(self::LOCALE_CODE);
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
        parent::__construct($context);
    }

    /**
     * Create random product.
     *
     * @param int $websiteId
     *
     * @return \Magento\Catalog\Model\Product\Interceptor
     */
    public function createProduct($websiteId = 1, $typeId = \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
    {
        if (!in_array($typeId, $this->getTypeArray())) {
            return;
        }

        $quantity = rand(1, 20);
        $product = $this->productInterfaceFactory
            ->create()
            ->setSku('FAKER-'.strtoupper($this->faker->word).'-'.rand(1000, 9999))
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
                'qty'         => $quantity,
            ]);

        if ($this->getRandomTrueOrFalse()) {
            $linkData = $this->createProductLinkArray($product, rand(1, 10));
            $product->setProductLinks($linkData);
        }

        if ($this->getRandomTrueOrFalse()) {
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
     *
     * @param $product
     *
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
     *
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
     *
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
     *
     * @param \Magento\Product\Model\Data\Product $product
     * @param int                                 $limit
     *
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
     *
     * @return bool
     */
    public function getRandomTrueOrFalse()
    {
        return (bool) rand(0, 1);
    }

    /**
     * Return array of random SKUs.
     *
     * @param int $limit
     *
     * @return array
     */
    public function getRandomSku($limit = 1)
    {
        $products = $this->getRandomProduct($limit);
        $skus = [];
        foreach ($products as $product) {
            $skus[] = $product->getSku();
        }

        return $skus;
    }

    /**
     * Return array of random IDs.
     *
     * @param int $limit
     *
     * @return array
     */
    public function getRandomIds($limit = 1)
    {
        $products = $this->getRandomProduct($limit);
        $ids = [];
        foreach ($products as $product) {
            $ids[] = $product->getId();
        }

        return $ids;
    }

    /**
     * Return collection of random products.
     *
     * @param int $limit
     *
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function getRandomProduct($limit = 1)
    {
        $collection = $this->productCollectionFactory
            ->create()
            ->addAttributeToSelect('*')
            ->setPageSize($limit);

        $collection->getSelect()->order('RAND()');

        return $collection;
    }

    /**
     * Add dummy images.
     *
     * @param \Magento\Catalog\Model\Product\Interceptor $product
     * @param int                                        $limit
     *
     * @return void
     */
    public function addImages(\Magento\Catalog\Model\Product\Interceptor $product, $limit = 1)
    {
        for ($generate = 1; $generate <= $limit; $generate++) {
            $this->addImageFromUrl($product, self::PLACEHOLDER_SOURCE, true, $generate == 1
                ? ['image', 'small_image', 'thumbnail'] : []);
        }
    }

    /**
     * Add image from Url.
     *
     * @param Product $product
     * @param string  $imageUrl
     * @param array   $imageType
     * @param bool    $visible
     *
     * @return bool
     */
    public function addImageFromUrl($product, $imageUrl, $visible = false, $imageType = [])
    {
        $tmpDir = $this->getMediaDirTmpDir();
        $this->file->checkAndCreateFolder($tmpDir);
        $newFileName = $tmpDir.basename($imageUrl);
        /** read file from URL and copy it to the new destination */
        $result = $this->file->read($imageUrl, $newFileName);
        $this->file->cp($newFileName, $newFileName.'.jpg');
        if ($result) {
            try {
                // $product->addImageToMediaGallery($newFileName . '.jpg', $imageType, true, $visible);
                $this->galleryProcessor->addImage($product, $newFileName.'.jpg', $imageType, false, $visible);
            } catch (\Exception $e) {
                $this->logger->critical($e);
            }
        }

        return $result;
    }

    /**
     * Media directory name for the temporary file storage pub/media/tmp.
     *
     * @return string
     */
    protected function getMediaDirTmpDir()
    {
        return $this->directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA)
            .DIRECTORY_SEPARATOR.'xigen'.DIRECTORY_SEPARATOR;
    }

    /**
     * Assign product to category.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param array                          $categoryIds
     *
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
     *
     * @param int $limit
     *
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