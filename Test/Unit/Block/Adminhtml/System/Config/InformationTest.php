<?php

declare(strict_types=1);

/**
 * Reva Magento 2
 *
 * @category   Reva
 * @package    Reva_Core
 * @copyright  © 2024 - RevaMagento - All rights reserved
 */

namespace Reva\Core\Test\Unit\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\TestCase;
use Reva\Core\Block\Adminhtml\System\Config\Information;
use Reva\Core\Model\ExtensionFeed;

class InformationTest extends TestCase
{
    /**
     * @param string[] $enabledModules
     * @param array<string, array<int, array<string, mixed>>|null>|null $online Online list per type, null = unavailable
     * @return Information
     */
    private function createBlock(array $enabledModules = [], ?array $online = null): Information
    {
        $feed = $this->createStub(ExtensionFeed::class);
        $feed->method('getExtensions')->willReturnCallback(fn (string $type) => $online[$type] ?? null);

        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturnCallback(fn ($type) => $this->createStub($type));
        ObjectManager::setInstance($objectManager);

        $moduleManager = $this->createStub(ModuleManager::class);
        $moduleManager->method('isEnabled')
            ->willReturnCallback(fn ($module) => in_array($module, $enabledModules, true));

        return new Information($this->createStub(Context::class), $moduleManager, $feed);
    }

    public function testWebsiteUrlAddsCampaignBeforeFragment(): void
    {
        $this->assertSame(
            Information::WEBSITE_URL . '?utm_source=magento-admin&utm_medium=extension&utm_campaign=reva-core'
            . '&utm_content=hire-us#contact',
            $this->createBlock()->getWebsiteUrl('#contact', 'hire-us')
        );
    }

    public function testWebsiteUrlWithPath(): void
    {
        $url = $this->createBlock()->getWebsiteUrl('/privacy-policy', 'privacy');

        $this->assertStringStartsWith(Information::WEBSITE_URL . 'privacy-policy?utm_source=magento-admin', $url);
        $this->assertStringEndsWith('&utm_content=privacy', $url);
    }

    public function testExtensionsShowInstallStatus(): void
    {
        $extensions = $this->createBlock(['Reva_SlidingCart'])->getExtensions();
        $status = array_column($extensions, 'installed', 'module');

        $this->assertSame(
            ['Reva_SlidingCart' => true, 'Reva_PopupDisplay' => false, 'Reva_AddToMenu' => false],
            $status
        );
        foreach ($extensions as $extension) {
            $this->assertNotSame('', (string)$extension['name']);
            $this->assertNotSame('', (string)$extension['description']);
        }
    }

    public function testPremiumExtensionsShowInstallStatusWithoutPrices(): void
    {
        $installed = $this->createBlock(['Reva_StockPriceAlert'])->getPremiumExtensions();
        $notInstalled = $this->createBlock()->getPremiumExtensions();

        $this->assertNotEmpty($installed);
        $this->assertSame(['Reva_StockPriceAlert' => true], array_column($installed, 'installed', 'module'));
        $this->assertSame(['Reva_StockPriceAlert' => false], array_column($notInstalled, 'installed', 'module'));
        foreach ($installed as $extension) {
            $this->assertNotSame('', (string)$extension['name']);
            $this->assertNotSame('', (string)$extension['description']);
            $this->assertNotEmpty($extension['highlights']);
            $this->assertArrayNotHasKey('price', $extension);
        }
    }

    public function testOnlineListReplacesBundledList(): void
    {
        $online = [
            ExtensionFeed::TYPE_PREMIUM => [[
                'module' => 'Reva_NewPaid',
                'name' => 'New Paid',
                'description' => 'Description',
                'highlights' => ['One'],
                'path' => 'new-paid',
            ]],
            ExtensionFeed::TYPE_FREE => null,
        ];
        $block = $this->createBlock(['Reva_NewPaid'], $online);

        $premium = $block->getPremiumExtensions();

        $this->assertSame(['Reva_NewPaid' => true], array_column($premium, 'installed', 'module'));
        $this->assertSame(
            ['Reva_SlidingCart', 'Reva_PopupDisplay', 'Reva_AddToMenu'],
            array_column($block->getExtensions(), 'module')
        );
    }

    public function testLearnMoreUsesExtensionUrlOrWebsitePage(): void
    {
        $block = $this->createBlock();

        $this->assertSame(
            'https://commercemarketplace.adobe.com/reva-slidingcart.html',
            $block->getLearnMoreUrl([
                'module' => 'Reva_SlidingCart',
                'url' => 'https://commercemarketplace.adobe.com/reva-slidingcart.html',
            ])
        );
        $this->assertStringStartsWith(
            Information::WEBSITE_URL . '?utm_source=magento-admin',
            $block->getLearnMoreUrl(['module' => 'Reva_X', 'path' => '#services'])
        );
    }

    public function testServicesAreListed(): void
    {
        $this->assertCount(8, $this->createBlock()->getServices());
    }
}
