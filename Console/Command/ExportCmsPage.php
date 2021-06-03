<?php

declare(strict_types = 1);

namespace TemplateProvider\CmsImportExport\Console\Command;

use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Module\Dir;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TemplateProvider\CmsImportExport\Api\ContentInterface as ImportExportContentInterface;

class ExportCmsPage extends Command
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
        $this->setName('template-provider:export:cms-pages');
        $this->setDescription('Export CMS Pages');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $collection = $this->collectionFactory->create();
        $pages = [];
        $output->writeln('Export Process starting....');

        foreach ($collection as $page) {
            $output->write('....');
            $pages[] = $page;
            $output->write('....');
        }
        $output->writeln('');

        try {
            if (!empty($pages)) {
                $file = $this->importExportContentInterface->asZipFile($pages, []);
                $showFileName = $this->directoryList->getPath('var') . '/' . $file;

                if (!empty($file)) {
                    $output->writeln("Pages successfully export at {$showFileName}");
                } else {
                    $output->writeln('Error while export pages.....');
                }
            } else {
                $output->writeln('Data not found...!');
            }
        } catch (\Exception $e) {
            $output->writeln('Error while export pages.....');
            $output->writeln($e->getMessage());
        }
    }
}
