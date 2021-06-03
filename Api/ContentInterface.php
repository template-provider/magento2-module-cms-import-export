<?php

declare(strict_types = 1);

namespace TemplateProvider\CmsImportExport\Api;

/**
 * @package TemplateProvider\CmsImportExport\Api
 *
 * @api
 */
interface ContentInterface
{
    const CMS_MODE_UPDATE = 'update';

    const CMS_MODE_SKIP = 'skip';

    const MEDIA_MODE_NONE = 'none';

    const MEDIA_MODE_UPDATE = 'update';

    const MEDIA_MODE_SKIP = 'skip';

    public function setCmsMode($mode): ContentInterface;

    public function setMediaMode($mode): ContentInterface;

    public function setStoresMap(array $storesMap): ContentInterface;

    public function blockToArray(\Magento\Cms\Api\Data\BlockInterface $blockInterface): array;

    public function pageToArray(\Magento\Cms\Api\Data\PageInterface $pageInterface): array;

    public function blocksToArray(array $blockInterfaces): array;

    public function pagesToArray(array $pageInterfaces): array;

    public function asZipFile(array $pageInterfaces, array $blockInterfaces): string;

    public function importPageFromArray(array $pageData): bool;

    public function importBlockFromArray(array $blockData): bool;

    public function importFromArray(array $payload, string $archivePath = null): int;

    public function importFromZipFile(string $fileName, bool $rm = false): int;
}
