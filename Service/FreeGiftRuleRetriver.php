<?php
declare(strict_types=1);

namespace Niziou\FreeGift\Service;

use Niziou\FreeGift\Helper\Config\Config as FreeGiftConfig;

final class FreeGiftRuleRetriver
{
    /**
     * @param FreeGiftConfig $config
     */
    public function __construct(
        protected FreeGiftConfig $config
    ){
    }

    /**
     * @param $storeId
     * @return array
     */
    public function getFreeGiftRules($storeId = null): array
    {
        $rulesConfig = $this->config->getRulesConfig($storeId);
        $rules = [];

        if ($rulesConfig) {
            $decoded = json_decode($rulesConfig, true);
            if (is_array($decoded)) {
                $rules = $decoded;
            }
        }

        return $rules;
    }
}
