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

class InformationTest extends TestCase
{
    /**
     * @param string[] $enabledModules
     * @return Information
     */
    private function createBlock(array $enabledModules = []): Information
    {
        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturnCallback(fn ($type) => $this->createStub($type));
        ObjectManager::setInstance($objectManager);

        $moduleManager = $this->createStub(ModuleManager::class);
        $moduleManager->method('isEnabled')
            ->willReturnCallback(fn ($module) => in_array($module, $enabledModules, true));

        return new Information($this->createStub(Context::class), $moduleManager);
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

    public function testServicesAreListed(): void
    {
        $this->assertCount(8, $this->createBlock()->getServices());
    }
}
