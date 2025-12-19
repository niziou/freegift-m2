<?php
namespace Freegift\FreeGift\Test\Unit\Config;

use Freegift\FreeGift\Model\Config\GiftConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
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
            ->willReturn('  SKU123 ');

        $config = new GiftConfig($scopeConfig);
        $this->assertSame('SKU123', $config->getConfiguredSku());
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
