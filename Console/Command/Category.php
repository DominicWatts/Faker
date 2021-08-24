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
use Xigen\Faker\Helper\Category as CategoryHelper;

/**
 * Category console
 */
class Category extends Command
{
    const GENERATE_ARGUMENT = 'generate';
    const STORE_OPTION = 'store';
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
     * @var \Xigen\Faker\Helper\Category
     */
    protected $categoryHelper;

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
     * Category constructor.
     * @param LoggerInterface $logger
     * @param State $state
     * @param DateTime $dateTime
     * @param CategoryHelper $categoryHelper
     * @param ProgressBarFactory $progressBarFactory
     */
    public function __construct(
        LoggerInterface $logger,
        State $state,
        DateTime $dateTime,
        CategoryHelper $categoryHelper,
        ProgressBarFactory $progressBarFactory
    ) {
        $this->logger = $logger;
        $this->state = $state;
        $this->dateTime = $dateTime;
        $this->categoryHelper = $categoryHelper;
        $this->progressBarFactory = $progressBarFactory;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     * @return int
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $this->input = $input;
        $this->output = $output;
        $this->state->setAreaCode(Area::AREA_GLOBAL);

        $generate = $input->getArgument(self::GENERATE_ARGUMENT) ?: false;
        $storeId = $this->input->getOption(self::STORE_OPTION) ?: 0;
        $limit = $this->input->getOption(self::LIMIT_OPTION) ?: 5;

        if ($generate) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                (string) __('You are about to generate fake category data. Are you sure? [y/N]'),
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
                if ($cateory = $this->categoryHelper->createCategory($storeId)) {
                    $progress->setMessage((string) __('Category: %1', $cateory->getName()));
                }
                $progress->advance();
            }

            $progress->finish();
            $this->output->writeln('');
            $this->output->writeln('[' . $this->dateTime->gmtDate() . '] Finish');
            return Cli::RETURN_SUCCESS;
        }
        return Cli::RETURN_FAILURE;
    }

    /**
     * {@inheritdoc}
     * xigen:faker:category [-s|--store STORE] [-l|--limit [LIMIT]] [--] <generate>.
     */
    protected function configure()
    {
        $this->setName('xigen:faker:category');
        $this->setDescription('Generate fake category');
        $this->setDefinition([
            new InputArgument(self::GENERATE_ARGUMENT, InputArgument::REQUIRED, 'Generate'),
            new InputOption(self::STORE_OPTION, '-s', InputOption::VALUE_REQUIRED, 'Store Id'),
            new InputOption(self::LIMIT_OPTION, '-l', InputOption::VALUE_OPTIONAL, 'Limit'),

        ]);
        parent::configure();
    }
}
