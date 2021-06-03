<?php

declare(strict_types = 1);

namespace TemplateProvider\CmsImportExport\Console\Command;

use Magento\Cms\Model\ResourceModel\Block\CollectionFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Module\Dir;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TemplateProvider\CmsImportExport\Api\ContentInterface as ImportExportContentInterface;

class ExportStaticBlock extends Command
{
    protected CollectionFactory $collectionFactory;

    protected Dir $moduleReader;

    protected DateTime $dateTime;

    protected DirectoryList $directoryList;

    private ImportExportContentInterface $importExportContentInterface;

    public function __construct(
        Dir $moduleReader,
        CollectionFactory $collectionFactory,
        DateTime $dateTime,
        ImportExportContentInterface $importExportContentInterface,
        DirectoryList $directoryList
    ) {
        $this->moduleReader = $moduleReader;
        $this->collectionFactory = $collectionFactory;
        $this->dateTime = $dateTime;
        $this->importExportContentInterface = $importExportContentInterface;
        $this->directoryList = $directoryList;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('template-provider:export:cms-blocks');
        $this->setDescription('Export CMS Blocks');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $collection = $this->collectionFactory->create();
        $blocks = [];
        $output->writeln('Static Block Export Process starting....');

        foreach ($collection as $page) {
            $output->write('....');
            $blocks[] = $page;
            $output->write('....');
        }
        $output->writeln('');

        try {
            if (!empty($blocks)) {
                $file_name = $this->importExportContentInterface->asZipFile([], $blocks);
                $show_file_name = $this->directoryList->getPath('var') . '/' . $file_name;

                if (!empty($file_name)) {
                    $output->writeln("Static Block successfully export at {$show_file_name}");
                } else {
                    $output->writeln('Error while export blocks.....');
                }
            } else {
                $output->writeln('Data not found...!');
            }
        } catch (\Exception $e) {
            $output->writeln('Error while export static block.....');
            $output->writeln($e->getMessage());
        }
    }
}
