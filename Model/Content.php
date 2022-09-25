<?php

declare(strict_types = 1);

namespace TemplateProvider\CmsImportExport\Model;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterface as CmsBlockInterface;
use Magento\Cms\Api\Data\PageInterface as CmsPageInterface;
use Magento\Cms\Model\BlockFactory as CmsBlockFactory;
use Magento\Cms\Model\PageFactory as CmsPageFactory;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as CmsBlockCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as CmsPageCollectionFactory;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Json\DecoderInterface;
use Magento\Framework\Json\EncoderInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\View\Asset\NotationResolver\Variable;
use Magento\Store\Api\StoreRepositoryInterface;
use TemplateProvider\CmsImportExport\Api\ContentInterface;

class Content implements ContentInterface
{
    const JSON_FILENAME = 'cms.json';

    const MEDIA_ARCHIVE_PATH = 'media';

    protected StoreRepositoryInterface $storeRepositoryInterface;

    protected EncoderInterface $encoderInterface;

    protected DecoderInterface $decoderInterface;

    protected CmsPageFactory $pageFactory;

    protected CmsPageCollectionFactory $pageCollectionFactory;

    protected CmsBlockFactory $blockFactory;

    protected CmsBlockCollectionFactory $blockCollectionFactory;

    protected BlockRepositoryInterface $blockRepositoryInterface;

    protected Filesystem $filesystem;

    protected File $file;

    protected DateTime $dateTime;

    protected string $cmsMode;

    protected string $mediaMode;

    /** @var string[] */
    protected array $storesMap;

    public function __construct(
        StoreRepositoryInterface $storeRepositoryInterface,
        EncoderInterface $encoderInterface,
        DecoderInterface $decoderInterface,
        CmsPageFactory $pageFactory,
        CmsPageCollectionFactory $pageCollectionFactory,
        CmsBlockFactory $blockFactory,
        CmsBlockCollectionFactory $blockCollectionFactory,
        BlockRepositoryInterface $blockRepositoryInterface,
        Filesystem $filesystem,
        File $file,
        DateTime $dateTime
    ) {
        $this->storeRepositoryInterface = $storeRepositoryInterface;
        $this->encoderInterface = $encoderInterface;
        $this->decoderInterface = $decoderInterface;
        $this->pageCollectionFactory = $pageCollectionFactory;
        $this->pageFactory = $pageFactory;
        $this->blockCollectionFactory = $blockCollectionFactory;
        $this->blockFactory = $blockFactory;
        $this->blockRepositoryInterface = $blockRepositoryInterface;
        $this->filesystem = $filesystem;
        $this->file = $file;
        $this->dateTime = $dateTime;

        $this->cmsMode = ContentInterface::CMS_MODE_UPDATE;
        $this->mediaMode = ContentInterface::MEDIA_MODE_UPDATE;

        $this->storesMap = [];
        $stores = $this->storeRepositoryInterface->getList();

        foreach ($stores as $store) {
            $this->storesMap[$store->getCode()] = $store->getCode();
        }
    }

    /**
     * @param \Magento\Cms\Api\Data\PageInterface[]  $pageInterfaces
     * @param \Magento\Cms\Api\Data\BlockInterface[] $blockInterfaces
     */
    public function asZipFile(array $pageInterfaces, array $blockInterfaces): string
    {
        $pagesArray = $this->pagesToArray($pageInterfaces);
        $blocksArray = $this->blocksToArray($blockInterfaces);

        $contentArray = array_merge_recursive($pagesArray, $blocksArray);

        $jsonPayload = $this->encoderInterface->encode($contentArray);

        $exportPath = $this->filesystem->getExportPath();

        $zipFile = $exportPath . '/' . sprintf('cms_%s.zip', $this->dateTime->date('Ymd_His'));
        $relativeZipFile = Filesystem::EXPORT_PATH . '/' . sprintf('cms_%s.zip', $this->dateTime->date('Ymd_His'));

        $zipArchive = new \ZipArchive();
        $zipArchive->open($zipFile, \ZipArchive::CREATE);

        $zipArchive->addFromString(self::JSON_FILENAME, $jsonPayload);

        foreach ($contentArray['media'] as $mediaFile) {
            $absMediaPath = $this->filesystem->getMediaPath($mediaFile);

            if ($this->file->fileExists($absMediaPath, true)) {
                $zipArchive->addFile($absMediaPath, self::MEDIA_ARCHIVE_PATH . '/' . $mediaFile);
            }
        }

        $zipArchive->close();

        return $relativeZipFile;
    }

    /**
     * @param \Magento\Cms\Api\Data\PageInterface[] $pageInterfaces
     */
    public function pagesToArray(array $pageInterfaces): array
    {
        $pages = [];
        $media = [];

        foreach ($pageInterfaces as $pageInterface) {
            $pageInfo = $this->pageToArray($pageInterface);
            $pages[$this->_getPageKey($pageInterface)] = $pageInfo;
            $media = array_merge($media, $pageInfo['media']);
        }

        return [
            'pages' => $pages,
            'media' => $media,
        ];
    }

    public function pageToArray(CmsPageInterface $pageInterface): array
    {
        $media = $this->getMediaAttachments($pageInterface->getContent());

        /** @var string[] $storeIds */
        $storeIds = $pageInterface->getStoreId();
        return [
            'cms' => [
                CmsPageInterface::IDENTIFIER => $pageInterface->getIdentifier(),
                CmsPageInterface::TITLE => $pageInterface->getTitle(),
                CmsPageInterface::PAGE_LAYOUT => $pageInterface->getPageLayout(),
                CmsPageInterface::META_KEYWORDS => $pageInterface->getMetaKeywords(),
                CmsPageInterface::META_DESCRIPTION => $pageInterface->getMetaDescription(),
                CmsPageInterface::CONTENT_HEADING => $pageInterface->getContentHeading(),
                CmsPageInterface::CONTENT => $pageInterface->getContent(),
                CmsPageInterface::SORT_ORDER => $pageInterface->getSortOrder(),
                CmsPageInterface::LAYOUT_UPDATE_XML => $pageInterface->getLayoutUpdateXml(),
                CmsPageInterface::CUSTOM_THEME => $pageInterface->getCustomTheme(),
                CmsPageInterface::CUSTOM_ROOT_TEMPLATE => $pageInterface->getCustomRootTemplate(),
                CmsPageInterface::CUSTOM_LAYOUT_UPDATE_XML => $pageInterface->getCustomLayoutUpdateXml(),
                CmsPageInterface::CUSTOM_THEME_FROM => $pageInterface->getCustomThemeFrom(),
                CmsPageInterface::CUSTOM_THEME_TO => $pageInterface->getCustomThemeTo(),
                CmsPageInterface::IS_ACTIVE => $pageInterface->isActive(),
            ],
            'stores' => $this->getStoreCodes($storeIds),
            'media' => $media,
        ];
    }

    public function getMediaAttachments(string $content): array
    {
        if (preg_match_all('/\{\{media.+?url\s*=\s*("|&quot;)(.+?)("|&quot;).*?\}\}/', $content, $matches)) {
            return $matches[2];
        }

        return [];
    }

    /**
     * @param string[]|int[] $storeIds
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStoreCodes(array $storeIds): array
    {
        $return = [];

        foreach ($storeIds as $storeId) {
            $return[] = $this->storeRepositoryInterface->getById($storeId)->getCode();
        }

        return $return;
    }

    /**
     * @param \Magento\Cms\Api\Data\BlockInterface[] $blockInterfaces
     */
    public function blocksToArray(array $blockInterfaces): array
    {
        $blocks = [];
        $media = [];

        foreach ($blockInterfaces as $blockInterface) {
            $blockInfo = $this->blockToArray($blockInterface);
            $blocks[$this->_getBlockKey($blockInterface)] = $blockInfo;
            $media = array_merge($media, $blockInfo['media']);
        }

        return [
            'blocks' => $blocks,
            'media' => $media,
        ];
    }

    public function blockToArray(CmsBlockInterface $blockInterface): array
    {
        /** @var string[] $storeIds */
        $storeIds = $blockInterface->getStoreId();
        $media = $this->getMediaAttachments($blockInterface->getContent());

        $payload = [
            'cms' => [
                CmsBlockInterface::IDENTIFIER => $blockInterface->getIdentifier(),
                CmsBlockInterface::TITLE => $blockInterface->getTitle(),
                CmsBlockInterface::CONTENT => $blockInterface->getContent(),
                CmsBlockInterface::IS_ACTIVE => $blockInterface->isActive(),
            ],
            'stores' => $this->getStoreCodes($storeIds),
            'media' => $media,
        ];

        return $payload;
    }

    /**
     * @throws \Exception
     */
    public function importFromZipFile(string $fileName): int
    {
        $zipArchive = new \ZipArchive();
        $result = $zipArchive->open($fileName);

        if (true !== $result) {
            throw new \Exception('Cannot open ZIP archive');
        }

        $subPath = md5(date(DATE_RFC2822));
        $extractPath = $this->filesystem->getExtractPath($subPath);

        $zipArchive->extractTo($extractPath);
        $zipArchive->close();

        $pagesFile = $extractPath . '/' . self::JSON_FILENAME;

        if (!$this->file->fileExists($pagesFile, true)) {
            throw new \Exception(self::JSON_FILENAME . ' is missing');
        }

        $jsonString = $this->file->read($pagesFile);
        $cmsData = $this->decoderInterface->decode($jsonString);

        $count = $this->importFromArray($cmsData, $extractPath);

        $this->file->rmdir($extractPath, true);

        return $count;
    }

    /**
     * @throws \Exception
     */
    public function importFromArray(array $payload, string $archivePath = null): int
    {
        if (!isset($payload['pages']) && !isset($payload['blocks'])) {
            throw new \Exception('Invalid json archive');
        }

        $count = 0;

        foreach ($payload['pages'] as $pageData) {
            if ($this->importPageFromArray($pageData)) {
                ++$count;
            }
        }

        foreach ($payload['blocks'] as $blockData) {
            if ($this->importBlockFromArray($blockData)) {
                ++$count;
            }
        }

        if ($archivePath && ($count > 0) && (ContentInterface::MEDIA_MODE_NONE != $this->mediaMode)) {
            foreach ($payload['media'] as $mediaFile) {
                $sourceFile = $archivePath . '/' . self::MEDIA_ARCHIVE_PATH . '/' . $mediaFile;
                $destFile = $this->filesystem->getMediaPath($mediaFile);

                if ($this->file->fileExists($sourceFile, true)) {
                    if ($this->file->fileExists($destFile, true) &&
                        (ContentInterface::MEDIA_MODE_SKIP == $this->mediaMode)
                    ) {
                        continue;
                    }

                    if ($this->file->createDestinationDir(dirname($destFile)) ||
                        !$this->file->cp($sourceFile, $destFile)) {
                        throw new \Exception('Unable to save image: ' . $mediaFile);
                    }
                    ++$count;
                }
            }
        }

        return $count;
    }

    public function importPageFromArray(array $pageData): bool
    {
        $storeIds = $this->getStoreIdsByCodes($this->_mapStores($pageData['stores']));

        $collection = $this->pageCollectionFactory->create();
        $collection
            ->addFieldToFilter(CmsPageInterface::IDENTIFIER, $pageData['cms'][CmsPageInterface::IDENTIFIER]);

        $pageId = 0;

        foreach ($collection as $item) {
            $itemStoreIds = $item->getStoreId();
            if ($itemStoreIds === null) {
                $itemStoreIds = [];
            }
            $storesIntersect = array_intersect($itemStoreIds, $storeIds);

            if (count($storesIntersect)) {
                $pageId = $item->getId();

                break;
            }
        }

        $page = $this->pageFactory->create();

        if ($pageId) {
            $page->load($pageId);

            if (ContentInterface::CMS_MODE_SKIP == $this->cmsMode) {
                return false;
            }
        }

        $cms = $pageData['cms'];

        $page
            ->setIdentifier($cms[CmsPageInterface::IDENTIFIER])
            ->setTitle($cms[CmsPageInterface::TITLE])
            ->setPageLayout($cms[CmsPageInterface::PAGE_LAYOUT])
            ->setMetaKeywords($cms[CmsPageInterface::META_KEYWORDS])
            ->setMetaDescription($cms[CmsPageInterface::META_DESCRIPTION])
            ->setContentHeading($cms[CmsPageInterface::CONTENT_HEADING])
            ->setContent($cms[CmsPageInterface::CONTENT])
            ->setSortOrder($cms[CmsPageInterface::SORT_ORDER])
            ->setLayoutUpdateXml($cms[CmsPageInterface::LAYOUT_UPDATE_XML])
            ->setCustomTheme($cms[CmsPageInterface::CUSTOM_THEME])
            ->setCustomRootTemplate($cms[CmsPageInterface::CUSTOM_ROOT_TEMPLATE])
            ->setCustomLayoutUpdateXml($cms[CmsPageInterface::CUSTOM_LAYOUT_UPDATE_XML])
            ->setCustomThemeFrom($cms[CmsPageInterface::CUSTOM_THEME_FROM])
            ->setCustomThemeTo($cms[CmsPageInterface::CUSTOM_THEME_TO])
            ->setIsActive($cms[CmsPageInterface::IS_ACTIVE]);

        $page->setData('stores', $storeIds);
        $page->save();

        return true;
    }

    /**
     * Get store ids by codes
     */
    public function getStoreIdsByCodes(array $storeCodes): array
    {
        $return = [];

        foreach ($storeCodes as $storeCode) {
            if ('admin' == $storeCode) {
                $return[] = 0;
            } else {
                $store = $this->storeRepositoryInterface->get($storeCode);

                if ($store && $store->getId()) {
                    $return[] = $store->getId();
                }
            }
        }

        return $return;
    }

    public function importBlockFromArray(array $blockData): bool
    {
        $storeIds = $this->getStoreIdsByCodes($this->_mapStores($blockData['stores']));

        $collection = $this->blockCollectionFactory->create();
        $collection
            ->addFieldToFilter(CmsBlockInterface::IDENTIFIER, $blockData['cms'][CmsBlockInterface::IDENTIFIER]);

        $blockId = 0;

        foreach ($collection as $item) {
            $itemStoreIds = $item->getStoreId();
            if ($itemStoreIds === null) {
                $itemStoreIds = [];
            }
            $storesIntersect = array_intersect($itemStoreIds, $storeIds);

            if (count($storesIntersect)) {
                $blockId = $item->getId();

                break;
            }
        }

        $block = $this->blockFactory->create();

        if ($blockId) {
            $block->load($blockId);

            if (ContentInterface::CMS_MODE_SKIP == $this->cmsMode) {
                return false;
            }
        }

        $cms = $blockData['cms'];

        $block
            ->setIdentifier($cms[CmsBlockInterface::IDENTIFIER])
            ->setTitle($cms[CmsBlockInterface::TITLE])
            ->setContent($cms[CmsBlockInterface::CONTENT])
            ->setIsActive($cms[CmsBlockInterface::IS_ACTIVE]);

        $block->setData('stores', $storeIds);
        $block->save();

        return true;
    }

    /**
     * Set CMS mode
     *
     * @param $mode
     */
    public function setCmsMode($mode): ContentInterface
    {
        $this->cmsMode = $mode;

        return $this;
    }

    /**
     * Set media mode
     *
     * @param $mode
     */
    public function setMediaMode($mode): ContentInterface
    {
        $this->mediaMode = $mode;

        return $this;
    }

    /**
     * Set stores mapping
     */
    public function setStoresMap(array $storesMap): ContentInterface
    {
        return $this;
    }

    /**
     * Get page unique key
     */
    protected function _getPageKey(CmsPageInterface $pageInterface): string
    {
        /** @var string[] $storeIds */
        $storeIds = $pageInterface->getStoreId();
        $keys = $this->getStoreCodes($storeIds);
        $keys[] = $pageInterface->getIdentifier();

        return implode(':', $keys);
    }

    protected function _getBlockKey(CmsBlockInterface $blockInterface): string
    {
        /** @var string[] $storeIds */
        $storeIds = $blockInterface->getStoreId();
        $keys = $this->getStoreCodes($storeIds);
        $keys[] = $blockInterface->getIdentifier();

        return implode(':', $keys);
    }

    /**
     * @param string[] $storeCodes
     */
    protected function _mapStores(array $storeCodes): array
    {
        $return = [];

        foreach ($storeCodes as $storeCode) {
            foreach ($this->storesMap as $to => $from) {
                if ($storeCode == $from) {
                    $return[] = $to;
                }
            }
        }

        return $return;
    }
}
