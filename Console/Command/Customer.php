<?php

namespace Xigen\Faker\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\ProgressBarFactory;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Xigen\Faker\Helper\Customer as CustomerHelper;

/**
 * Customer Console
 */
class Customer extends Command
{
    const GENERATE_ARGUMENT = 'generate';
    const WEBSITE_OPTION = 'website';
    const LIMIT_OPTION = 'limit';

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $state;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $dateTime;

    /**
     * @var \Xigen\Faker\Helper\Customer
     */
    protected $customerHelper;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var ProgressBarFactory
     */
    protected $progressBarFactory;

    /**
     * Customer constructor.
     * @param LoggerInterface $logger
     * @param State $state
     * @param DateTime $dateTime
     * @param CustomerHelper $customerHelper
     */
    public function __construct(
        LoggerInterface $logger,
        State $state,
        DateTime $dateTime,
        CustomerHelper $customerHelper,
        ProgressBarFactory $progressBarFactory
    ) {
        $this->logger = $logger;
        $this->state = $state;
        $this->dateTime = $dateTime;
        $this->customerHelper = $customerHelper;
        $this->progressBarFactory = $progressBarFactory;
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
        $this->state->setAreaCode(Area::AREA_GLOBAL);

        $generate = $input->getArgument(self::GENERATE_ARGUMENT) ?: false;
        $websiteId = $this->input->getOption(self::WEBSITE_OPTION) ?: 1;
        $limit = $this->input->getOption(self::LIMIT_OPTION) ?: 5;

        if ($generate) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                (string) __('You are about to generate fake customer data. Are you sure? [y/N]'),
                false
            );

            if (!$helper->ask($this->input, $this->output, $question) && $this->input->isInteractive()) {
                return Cli::RETURN_FAILURE;
            }
            $this->output->writeln('[' . $this->dateTime->gmtDate() . '] Start');

            /** @var ProgressBar $progress */
            $progress = $this->progressBarFactory->create(
                [
                    'output' => $this->output,
                    'max' => $limit
                ]
            );

            $progress->setFormat(
                "%current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s% \t| <info>%message%</info>"
            );

            if ($output->getVerbosity() !== OutputInterface::VERBOSITY_NORMAL) {
                $progress->setOverwrite(false);
            }

            for ($generate = 1; $generate <= $limit; $generate++) {
                if ($customer = $this->customerHelper->createCustomer($websiteId)) {
                    $address = $this->customerHelper->createCustomerAddress($customer);
                    $progress->setMessage((string) __(
                        'Customer: %1 %2',
                        $customer->getFirstname(),
                        $customer->getLastname()
                    ));
                    $progress->advance();
                }
            }

            $progress->finish();
            $this->output->writeln('');
            $this->output->writeln('[' . $this->dateTime->gmtDate() . '] Finish');
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
