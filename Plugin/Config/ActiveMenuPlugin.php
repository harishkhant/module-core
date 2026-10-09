<?php

declare(strict_types=1);

/**
 * Reva Magento 2
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 *
 * @category   Reva
 * @package    Reva_Core
 * @author     Extension Team
 * @copyright  © 2024 - RevaMagento - All rights reserved
 */

namespace Reva\Core\Plugin\Config;

use Magento\Backend\Block\Menu as MenuBlock;
use Magento\Backend\Model\Menu\Item;
use Magento\Backend\Model\View\Result\Page;
use Magento\Config\Controller\Adminhtml\System\Config\Edit;

/**
 * Highlight the MageReva admin menu when a configuration section linked from it is open
 */
class ActiveMenuPlugin
{
    /**
     * Top level MageReva menu item
     */
    private const REVA_MENU_ID = 'Reva_Core::reva_menu';

    /**
     * Mark the MageReva menu item that links to the open section as active
     *
     * Magento always marks Stores > Configuration as active on configuration pages.
     *
     * @param Edit $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterExecute(Edit $subject, $result)
    {
        $section = (string)$subject->getRequest()->getParam('section');
        if ($section === '' || !$result instanceof Page) {
            return $result;
        }

        $menuBlock = $result->getLayout()->getBlock('menu');
        if (!$menuBlock instanceof MenuBlock) {
            return $result;
        }

        $revaMenu = $menuBlock->getMenuModel()->get(self::REVA_MENU_ID);
        $itemId = $revaMenu ? $this->findSectionItemId($revaMenu, $section) : null;
        if ($itemId !== null) {
            $menuBlock->setActive($itemId);
        }

        return $result;
    }

    /**
     * Find the id of the menu item below $item whose action opens the configuration section
     *
     * @param Item $item
     * @param string $section
     * @return string|null
     */
    private function findSectionItemId(Item $item, string $section): ?string
    {
        $pattern = '#(^|/)system_config/edit/section/' . preg_quote($section, '#') . '(/|$)#';
        if (preg_match($pattern, (string)$item->getAction())) {
            return $item->getId();
        }
        if ($item->hasChildren()) {
            foreach ($item->getChildren() as $child) {
                $itemId = $this->findSectionItemId($child, $section);
                if ($itemId !== null) {
                    return $itemId;
                }
            }
        }

        return null;
    }
}
