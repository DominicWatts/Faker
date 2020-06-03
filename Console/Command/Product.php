<?php

namespace Xigen\Faker\Console\Command;

use Magento\Catalog\Model\Product\Type;
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
use Xigen\Faker\Helper\Product as ProductHelper;

/**
 * Product Console
 */
class Product extends Command
{
    const GENERATE_ARGUMENT = 'generate';
    const IMAGE_ARGUMENT = 'applyImage';
    const WEBSITE_OPTION = 'website';
    const TYPE_OPTION = 'type';
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
     * @var \Xigen\Faker\Helper\Product
     */
    protected $productHelper;

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
     * Product constructor.
     * @param LoggerInterface $logger
     * @param State $state
     * @param DateTime $dateTime
     * @param ProductHelper $productHelper
     * @param ProgressBarFactory $progressBarFactory
     */
    public function __construct(
        LoggerInterface $logger,
        State $state,
        DateTime $dateTime,
        ProductHelper $productHelper,
        ProgressBarFactory $progressBarFactory
    ) {
        $this->logger = $logger;
        $this->state = $state;
        $this->dateTime = $dateTime;
        $this->productHelper = $productHelper;
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
        $image = $input->getArgument(self::IMAGE_ARGUMENT) ?: false;
        $websiteId = $this->input->getOption(self::WEBSITE_OPTION) ?: 1;
        $limit = $this->input->getOption(self::LIMIT_OPTION) ?: 5;

        if ($generate) {
            $helper = $this->getHelper('question');

            $question = new ConfirmationQuestion(
                (string) __(
                    'You are about to generate fake product data%1. Are you sure? [y/N]',
                    (($image) ? ' with images' : '')
                ),
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
                if ($product = $this->productHelper->createProduct(
                    $websiteId,
                    Type::TYPE_SIMPLE,
                    $image
                )) {
                    $progress->setMessage((string) __('Product: %1', $product->getSku()));
                }
                $progress->advance();
            }

            $progress->finish();
            $this->output->writeln('');
            $this->output->writeln('[' . $this->dateTime->gmtDate() . '] Finish');
        }
    }

    /**
     * {@inheritdoc}
     * xigen:faker:product [-w|--website WEBSITE] [-l|--limit [LIMIT]] [-t|--type [TYPE]] [--] <generate> <applyImage>.
     */
    protected function configure()
    {
        $this->setName('xigen:faker:product');
        $this->setDescription('Generate fake product');
        $this->setDefinition([
            new InputArgument(self::GENERATE_ARGUMENT, InputArgument::REQUIRED, 'Generate'),
            new InputArgument(self::IMAGE_ARGUMENT, InputArgument::OPTIONAL, 'Apply Image'),
            new InputOption(self::WEBSITE_OPTION, '-w', InputOption::VALUE_REQUIRED, 'Website Id'),
            new InputOption(self::TYPE_OPTION, '-t', InputOption::VALUE_REQUIRED, 'Type Id'),
            new InputOption(self::LIMIT_OPTION, '-l', InputOption::VALUE_OPTIONAL, 'Limit'),

        ]);
        parent::configure();
    }
}
