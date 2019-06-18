<?php

namespace Xigen\Faker\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Customer Console
 */
class Customer extends Command
{
    const GENERATE_ARGUMENT = 'generate';
    const WEBSITE_OPTION = 'website';
    const LIMIT_OPTION = 'limit';

    private $logger;
    private $state;
    private $dateTime;
    private $customerHelper;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\State $state,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        \Xigen\Faker\Helper\Customer $customerHelper
    ) {
        $this->logger = $logger;
        $this->state = $state;
        $this->dateTime = $dateTime;
        $this->customerHelper = $customerHelper;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $this->input = $input;
        $this->output = $output;
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_GLOBAL);

        $generate = $input->getArgument(self::GENERATE_ARGUMENT) ?: false;
        $websiteId = $this->input->getOption(self::WEBSITE_OPTION) ?: 1;
        $limit = $this->input->getOption(self::LIMIT_OPTION) ?: 5;

        if ($generate) {
            $this->output->writeln('['.$this->dateTime->gmtDate().'] Start');

            $progress = new ProgressBar($this->output, $limit);
            $progress->start();

            for ($generate = 1; $generate <= $limit; $generate++) {
                $customer = $this->customerHelper->createCustomer($websiteId);
                $address = $this->customerHelper->createCustomerAddress($customer);
                $progress->advance();
            }

            $progress->finish();
            $this->output->writeln('');
            $this->output->writeln('['.$this->dateTime->gmtDate().'] Finish');
        }
    }

    /**
     * {@inheritdoc}
     * xigen:faker:customer [-w|--website WEBSITE] [-l|--limit [LIMIT]] [--] <generate>.
     */
    protected function configure()
    {
        $this->setName('xigen:faker:customer');
        $this->setDescription('Generate fake customer');
        $this->setDefinition([
            new InputArgument(self::GENERATE_ARGUMENT, InputArgument::REQUIRED, 'Generate'),
            new InputOption(self::WEBSITE_OPTION, '-w', InputOption::VALUE_REQUIRED, 'Website Id'),
            new InputOption(self::LIMIT_OPTION, '-l', InputOption::VALUE_OPTIONAL, 'Limit'),

        ]);
        parent::configure();
    }
}