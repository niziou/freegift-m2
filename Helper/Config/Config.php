<?php
declare(strict_types=1);

namespace Niziou\FreeGift\Helper\Config;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

final class Config extends AbstractHelper
{
    const string XML_PATH_FREEGIFT_ENABLED = 'freegift/general/enabled';
    const string XML_PATH_FREEGIFT_RULES   = 'freegift/general/rules';

    /**
     * Check if the free gift module is enabled.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(int | null $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_FREEGIFT_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getRulesConfig($storeId = null): string | null
    {
        return $this->scopeConfig->getValue(self::XML_PATH_FREEGIFT_RULES, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
