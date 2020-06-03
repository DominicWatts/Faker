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
use Xigen\Faker\Helper\Review as ReviewHelper;

/**
 * Review console
 */
class Review extends Command
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
     * @var \Xigen\Faker\Helper\Review
     */
    protected $reviewHelper;

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
     * Review constructor.
     * @param LoggerInterface $logger
     * @param State $state
     * @param DateTime $dateTime
     * @param ReviewHelper $reviewHelper
     * @param ProgressBarFactory $progressBarFactory
     */
    public function __construct(
        LoggerInterface $logger,
        State $state,
        DateTime $dateTime,
        ReviewHelper $reviewHelper,
        ProgressBarFactory $progressBarFactory
    ) {
        $this->logger = $logger;
        $this->state = $state;
        $this->dateTime = $dateTime;
        $this->reviewHelper = $reviewHelper;
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
        $storeId = $this->input->getOption(self::STORE_OPTION) ?: 1;
        $limit = $this->input->getOption(self::LIMIT_OPTION) ?: 5;

        if ($generate) {
            $helper = $this->getHelper('question');

            $question = new ConfirmationQuestion(
                (string) __('You are about to generate fake review data. Are you sure? [y/N]'),
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
                if ($review = $this->reviewHelper->createReview($storeId)) {
                    $progress->setMessage((string) __('Review: %1', $review->getTitle()));
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
     * xigen:faker:review [-s|--store STORE] [-l|--limit [LIMIT]] [--] <generate>.
     */
    protected function configure()
    {
        $this->setName('xigen:faker:review');
        $this->setDescription('Generate fake review');
        $this->setDefinition([
            new InputArgument(self::GENERATE_ARGUMENT, InputArgument::REQUIRED, 'Generate'),
            new InputOption(self::STORE_OPTION, '-s', InputOption::VALUE_REQUIRED, 'Store Id'),
            new InputOption(self::LIMIT_OPTION, '-l', InputOption::VALUE_OPTIONAL, 'Limit'),

        ]);
        parent::configure();
    }
}
