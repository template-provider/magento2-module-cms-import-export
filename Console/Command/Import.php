<?php

declare(strict_types = 1);

namespace TemplateProvider\CmsImportExport\Console\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Module\Dir;
use Magento\Store\Api\StoreRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TemplateProvider\CmsImportExport\Api\ContentInterface;
use TemplateProvider\CmsImportExport\Model\Filesystem;

class Import extends Command
{
    protected DirectoryList $directoryList;

    protected Dir $moduleDir;

    protected ContentInterface $contentInterface;

    protected Filesystem $filesystem;

    protected StoreRepositoryInterface $storeRepositoryInterface;

    public function __construct(
        DirectoryList $directoryList,
        Dir $moduleDir,
        ContentInterface $contentInterface,
        Filesystem $filesystem,
        StoreRepositoryInterface $storeRepositoryInterface
    ) {
        parent::__construct();
        $this->directoryList = $directoryList;
        $this->moduleDir = $moduleDir;
        $this->contentInterface = $contentInterface;
        $this->filesystem = $filesystem;
        $this->storeRepositoryInterface = $storeRepositoryInterface;
    }

    protected function configure()
    {
        $this->setName('template-provider:import:cms-data');
        $this->setDescription('Import CMS Pages & Blocks');
        $this->addOption('file-name', null, InputOption::VALUE_REQUIRED, 'File Name');
        $this->addOption('cms-mode', null, InputOption::VALUE_REQUIRED, 'CMS import mode');
        $this->addOption('media-mode', null, InputOption::VALUE_REQUIRED, 'Media import mode');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getOption('file-name')) {
            $output->writeln('Please enter file name which you want to import.');
        } else {
            $fileName = $this->filesystem->getExportPath() . '/' . $input->getOption('file-name');

            if (file_exists($fileName)) {
                $output->writeln('Import Process started...!');
                $stores = $this->storeRepositoryInterface->getList();
                $cmsMode = $input->getOption('cms-mode');
                $mediaMode = $input->getOption('media-mode');
                $storesMap = [];

                foreach ($stores as $storeInterface) {
                    $storesMap[$storeInterface->getCode()] = $storeInterface->getCode();
                }
                $this->contentInterface->setCmsMode($cmsMode)->setMediaMode($mediaMode)->setStoresMap($storesMap);
                $count = $this->contentInterface->importFromZipFile($fileName);
                $output->writeln(__('A total of %1 item(s) have been imported/updated.', $count));
            } else {
                $output->writeln("{$fileName} file does not exist");
            }
        }
    }
}
