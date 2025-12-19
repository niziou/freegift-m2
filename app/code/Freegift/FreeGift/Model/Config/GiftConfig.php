<?php
namespace Freegift\FreeGift\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class GiftConfig
{
    private const XML_PATH_ENABLED = 'freegift/general/enabled';
    private const XML_PATH_PRODUCT_SKU = 'freegift/general/product_sku';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(?int $websiteId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }

    public function getConfiguredSku(?int $websiteId = null): ?string
    {
        $sku = (string)$this->scopeConfig->getValue(self::XML_PATH_PRODUCT_SKU, ScopeInterface::SCOPE_WEBSITE, $websiteId);
        $sku = trim($sku);

        return $sku !== '' ? $sku : null;
    }
}
