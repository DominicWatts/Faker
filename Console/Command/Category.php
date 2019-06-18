<?php

namespace Xigen\Faker\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Category console
 */
class Category extends Command
{
    const GENERATE_ARGUMENT = 'generate';
    const STORE_OPTION = 'store';
    const LIMIT_OPTION = 'limit';

    private $logger;
    private $state;
    private $dateTime;
    private $customerHelper;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\State $state,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        \Xigen\Faker\Helper\Category $categoryHelper
    ) {
        $this->logger = $logger;
        $this->state = $state;
        $this->dateTime = $dateTime;
        $this->categoryHelper = $categoryHelper;
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
        $storeId = $this->input->getOption(self::STORE_OPTION) ?: 0;
        $limit = $this->input->getOption(self::LIMIT_OPTION) ?: 5;

        if ($generate) {
            $this->output->writeln('['.$this->dateTime->gmtDate().'] Start');

            $progress = new ProgressBar($this->output, $limit);
            $progress->start();

            for ($generate = 1; $generate <= $limit; $generate++) {
                $cateory = $this->categoryHelper->createCategory($storeId);
                $progress->advance();
            }

            $progress->finish();
            $this->output->writeln('');
            $this->output->writeln('['.$this->dateTime->gmtDate().'] Finish');
        }
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