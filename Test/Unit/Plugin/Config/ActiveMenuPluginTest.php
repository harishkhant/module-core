<?php

declare(strict_types=1);

/**
 * Reva Magento 2
 *
 * @category   Reva
 * @package    Reva_Core
 * @copyright  © 2024 - RevaMagento - All rights reserved
 */

namespace Reva\Core\Test\Unit\Plugin\Config;

use Magento\Backend\Block\Menu as MenuBlock;
use Magento\Backend\Model\Menu;
use Magento\Backend\Model\Menu\Item;
use Magento\Backend\Model\View\Result\Page;
use Magento\Config\Controller\Adminhtml\System\Config\Edit;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\TestCase;
use Reva\Core\Plugin\Config\ActiveMenuPlugin;

class ActiveMenuPluginTest extends TestCase
{
    /**
     * @var string|null
     */
    private ?string $activeItem = null;

    /**
     * @param string $id
     * @param string $action
     * @param Item[] $children
     * @return Item
     */
    private function createItem(string $id, string $action = '', array $children = []): Item
    {
        $item = $this->createStub(Item::class);
        $item->method('getId')->willReturn($id);
        $item->method('getAction')->willReturn($action);
        $item->method('hasChildren')->willReturn((bool)$children);
        $item->method('getChildren')->willReturn(new \ArrayIterator($children));

        return $item;
    }

    /**
     * @param string $section
     * @param Item|null $revaMenu
     * @return array [Edit, Page]
     */
    private function createContext(string $section, ?Item $revaMenu): array
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn($section);
        $subject = $this->createStub(Edit::class);
        $subject->method('getRequest')->willReturn($request);

        $menuModel = $this->createStub(Menu::class);
        $menuModel->method('get')->willReturn($revaMenu);
        $menuBlock = $this->createStub(MenuBlock::class);
        $menuBlock->method('getMenuModel')->willReturn($menuModel);
        $menuBlock->method('__call')->willReturnCallback(function ($method, $args) use ($menuBlock) {
            if ($method === 'setActive') {
                $this->activeItem = $args[0];
            }
            return $menuBlock;
        });
        $layout = $this->createStub(LayoutInterface::class);
        $layout->method('getBlock')->willReturn($menuBlock);
        $page = $this->createStub(Page::class);
        $page->method('getLayout')->willReturn($layout);

        return [$subject, $page];
    }

    /**
     * @return Item
     */
    private function createRevaMenu(): Item
    {
        return $this->createItem('Reva_Core::reva_menu', '', [
            $this->createItem('Reva_Core::reva_configuration', '', [
                $this->createItem('Reva_Core::reva_config', 'adminhtml/system_config/edit/section/reva_config/'),
                $this->createItem('Reva_SlidingCart::topmenu', 'adminhtml/system_config/edit/section/slidingcart/'),
            ]),
        ]);
    }

    public function testRevaSectionActivatesItsMenuItem(): void
    {
        [$subject, $page] = $this->createContext('slidingcart', $this->createRevaMenu());

        $this->assertSame($page, (new ActiveMenuPlugin())->afterExecute($subject, $page));
        $this->assertSame('Reva_SlidingCart::topmenu', $this->activeItem);
    }

    public function testSectionNamePrefixDoesNotMatch(): void
    {
        [$subject, $page] = $this->createContext('slidingcart_extra', $this->createRevaMenu());

        (new ActiveMenuPlugin())->afterExecute($subject, $page);

        $this->assertNull($this->activeItem);
    }

    public function testOtherSectionKeepsStoresActive(): void
    {
        [$subject, $page] = $this->createContext('catalog', $this->createRevaMenu());

        (new ActiveMenuPlugin())->afterExecute($subject, $page);

        $this->assertNull($this->activeItem);
    }

    public function testHiddenRevaMenuIsIgnored(): void
    {
        [$subject, $page] = $this->createContext('slidingcart', null);

        (new ActiveMenuPlugin())->afterExecute($subject, $page);

        $this->assertNull($this->activeItem);
    }

    public function testRedirectResultIsReturnedUnchanged(): void
    {
        [$subject] = $this->createContext('slidingcart', $this->createRevaMenu());
        $redirect = new \stdClass();

        $this->assertSame($redirect, (new ActiveMenuPlugin())->afterExecute($subject, $redirect));
        $this->assertNull($this->activeItem);
    }
}
