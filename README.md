## Installation

```bash
composer require template-provider/magento2-module-cms-import-export
```

### Export CMS Blocks

```bash
php bin/magento template-provider:export:cms-blocks
```
 
### Export CMS Pages

```bash
php bin/magento template-provider:export:cms-pages
```
 
### Import CMS Pages

```bash
php bin/magento template-provider:import:cms-data --file-name=cms_blocks.zip --cms-mode=update --media-mode=update
```


