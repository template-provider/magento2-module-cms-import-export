<?php

declare(strict_types = 1);

namespace TemplateProvider\CmsImportExport\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Filesystem\Io\File;

class Filesystem
{
    const EXPORT_PATH = 'cms-import-export/export';

    const EXTRACT_PATH = 'cms-import-export/extract';

    const UPLOAD_PATH = 'cms-import-export/extract';

    protected \Magento\Framework\Filesystem $filesystem;

    protected File $file;

    public function __construct(
        \Magento\Framework\Filesystem $filesystem,
        File $file
    ) {
        $this->filesystem = $filesystem;
        $this->file = $file;
    }

    public function getUploadPath(): string
    {
        $varDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $exportPath = $varDir->getAbsolutePath(self::UPLOAD_PATH);

        $this->file->mkdir($exportPath, DriverInterface::WRITEABLE_DIRECTORY_MODE, true);

        return $exportPath;
    }

    public function getExportPath(): string
    {
        $varDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $exportPath = $varDir->getAbsolutePath(self::EXPORT_PATH);

        $this->file->mkdir($exportPath, DriverInterface::WRITEABLE_DIRECTORY_MODE, true);

        return $exportPath;
    }

    public function getExtractPath(string $subPath): string
    {
        $varDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $extractPath = $varDir->getAbsolutePath(self::EXTRACT_PATH . '/' . $subPath);

        $this->file->mkdir($extractPath, DriverInterface::WRITEABLE_DIRECTORY_MODE, true);

        return $extractPath;
    }

    public function getMediaPath(string $mediaFile, bool $write = false): string
    {
        if ($write) {
            $mediaDir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        } else {
            $mediaDir = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        }

        return $mediaDir->getAbsolutePath($mediaFile);
    }
}
