<?php
declare(strict_types=1);

namespace Niziou\FreeGift\Test\Unit\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Niziou\FreeGift\Model\Config\GiftConfig;
use PHPUnit\Framework\TestCase;

class GiftConfigTest extends TestCase
{
    public function testIsEnabledReadsWebsiteScope(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with('freegift/general/enabled', ScopeInterface::SCOPE_WEBSITE, 2)
            ->willReturn(true);

        $config = new GiftConfig($scopeConfig);

        $this->assertTrue($config->isEnabled(2));
    }

    public function testGetConfiguredSkuTrimsWhitespace(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('getValue')
            ->with('freegift/general/product_sku', ScopeInterface::SCOPE_WEBSITE, null)
            ->willReturn('  GIFT-SKU  ');

        $config = new GiftConfig($scopeConfig);

        $this->assertSame('GIFT-SKU', $config->getConfiguredSku());
    }

    public function testGetConfiguredSkuReturnsNullWhenEmpty(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('getValue')
            ->willReturn('   ');

        $config = new GiftConfig($scopeConfig);

        $this->assertNull($config->getConfiguredSku());
    }
}
