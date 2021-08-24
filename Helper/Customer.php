<?php

namespace Xigen\Faker\Helper;

use Faker\Factory as Faker;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\Data\RegionInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Psr\Log\LoggerInterface;

/**
 * Customer helper
 */
class Customer extends AbstractHelper
{
    /**
     * @var \Magento\Customer\Api\Data\CustomerInterfaceFactory
     */
    protected $customerInterfaceFactory;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepositoryInterface;

    /**
     * @var \Magento\Customer\Api\Data\AddressInterfaceFactory
     */
    protected $addressInterfaceFactory;

    /**
     * @var \Magento\Customer\Api\AddressRepositoryInterface
     */
    protected $addressRepositoryInterface;

    /**
     * @var \Magento\Customer\Api\Data\RegionInterface
     */
    protected $regionInterface;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $encryptorInterface;

    /**
     * @var \Faker\Generator
     */
    protected $faker;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Customer\Model\ResourceModel\Customer\CollectionFactory
     */
    protected $customerCollectionFactory;

    /**
     * Customer constructor.
     * @param Context $context
     * @param CustomerInterfaceFactory $customerInterfaceFactory
     * @param CustomerRepositoryInterface $customerRepositoryInterface
     * @param AddressInterfaceFactory $addressInterfaceFactory
     * @param AddressRepositoryInterface $addressRepositoryInterface
     * @param RegionInterface $regionInterface
     * @param EncryptorInterface $encryptorInterface
     * @param LoggerInterface $logger
     * @param CollectionFactory $customerCollectionFactory
     */
    public function __construct(
        Context $context,
        CustomerInterfaceFactory $customerInterfaceFactory,
        CustomerRepositoryInterface $customerRepositoryInterface,
        AddressInterfaceFactory $addressInterfaceFactory,
        AddressRepositoryInterface $addressRepositoryInterface,
        RegionInterface $regionInterface,
        EncryptorInterface $encryptorInterface,
        LoggerInterface $logger,
        CollectionFactory $customerCollectionFactory
    ) {
        $this->customerInterfaceFactory = $customerInterfaceFactory;
        $this->customerRepositoryInterface = $customerRepositoryInterface;
        $this->addressInterfaceFactory = $addressInterfaceFactory;
        $this->addressRepositoryInterface = $addressRepositoryInterface;
        $this->regionInterface = $regionInterface;
        $this->encryptorInterface = $encryptorInterface;
        $this->faker = Faker::create(Data::LOCALE_CODE);
        $this->logger = $logger;
        $this->customerCollectionFactory = $customerCollectionFactory;
        parent::__construct($context);
    }

    /**
     * Create random customer
     * @param int $websiteId
     * @return \Magento\Customer\Api\Data\CustomerInterface|false
     */
    public function createCustomer($websiteId = 1)
    {
        $customer = $this->customerInterfaceFactory
            ->create()
            ->setWebsiteId($websiteId)
            ->setEmail($this->faker->safeEmail)
            ->setFirstname($this->faker->firstName)
            ->setLastname($this->faker->lastName);

        try {
            $hashedPassword = $this->encryptorInterface->getHash($this->faker->word, true);
            $customer = $this->customerRepositoryInterface->save($customer, $hashedPassword);

            return $customer;
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
        }
    }

    /**
     * Create address for supplied customerId.
     * @param \Magento\Customer\Model\Data\Customer $customer
     * @return \Magento\Customer\Api\Data\AddressInterface|false
     */
    public function createCustomerAddress(\Magento\Customer\Model\Data\Customer $customer)
    {
        if ($customer && $customer->getId()) {
            $address = $this->addressInterfaceFactory
                ->create()
                ->setCompany($this->faker->company)
                ->setPrefix($this->faker->title)
                ->setFirstname($customer->getFirstname())
                ->setLastname($customer->getLastname())
                ->setStreet([$this->faker->streetAddress])
                ->setCity($this->faker->city)
                ->setRegion($this->regionInterface->setRegion($this->faker->county))
                ->setPostcode($this->faker->postcode)
                ->setCountryId(\Xigen\Faker\Helper\Data::COUNTRY_CODE)
                ->setCustomerId($customer->getId())
                ->setTelephone($this->faker->phoneNumber)
                ->setIsDefaultBilling(true)
                ->setIsDefaultShipping(true);

            try {
                $address = $this->addressRepositoryInterface->save($address);
                return $address;
            } catch (\Exception $e) {
                $this->logger->critical($e);
                return false;
            }
        }
    }

    /**
     * Return array of random Customer IDs.
     * @param int $limit
     * @param int $websiteId
     * @return array
     */
    public function getRandomCustomerId($limit = 1, $websiteId = 1)
    {
        $customers = $this->getRandomCustomer($limit, $websiteId);
        $ids = [];
        foreach ($customers as $customer) {
            $ids[] = $customer->getId();
        }

        return $ids;
    }

    /**
     * Return collection of random products.
     * @param int $limit
     * @param int $websiteId
     * @return \Magento\Catalog\Model\ResourceModel\Customer\Collection
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getRandomCustomer($limit = 1, $websiteId = 1)
    {
        $collection = $this->customerCollectionFactory
            ->create()
            ->addAttributeToSelect('*')
            ->setPageSize($limit);

        if ($websiteId) {
            $collection->addAttributeToFilter('website_id', ['eq' => $websiteId]);
        }

        $collection->getSelect()->order('RAND()');

        return $collection;
    }

    /**
     * Get customer by Id.
     * @param int $customerId
     * @return bool|\Magento\Customer\Api\Data\CustomerInterface
     */
    public function getById($customerId)
    {
        try {
            return $this->customerRepositoryInterface->getById($customerId);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
        }
    }
}
