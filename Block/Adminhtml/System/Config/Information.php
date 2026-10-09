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

namespace Reva\Core\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Magento\Framework\Module\Manager as ModuleManager;

/**
 * Renders the RevaMagento information panel in Stores > Configuration > Reva > Information & Marketplace
 */
class Information extends Template implements RendererInterface
{
    /**
     * RevaMagento website
     */
    public const WEBSITE_URL = 'https://revacloudflare.harishkhant267.workers.dev/';

    /**
     * Support contact details
     */
    public const SUPPORT_EMAIL = 'harishkhant267@gmail.com';
    public const SUPPORT_PHONE = '+919638128762';
    public const SUPPORT_PHONE_LABEL = '+91 96381 28762';
    public const LOCATION = 'India';

    /**
     * Campaign parameters that tell the website a visit came from this panel
     */
    private const UTM_SOURCE = 'magento-admin';
    private const UTM_MEDIUM = 'extension';
    private const UTM_CAMPAIGN = 'reva-core';

    /**
     * @var string
     */
    protected $_template = 'Reva_Core::system/config/information.phtml';

    /**
     * @param Context $context
     * @param ModuleManager $moduleManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly ModuleManager $moduleManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Render the panel in place of the configuration field row
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        return $this->toHtml();
    }

    /**
     * Get a website link with campaign parameters, keeping any "#section" at the end
     *
     * @param string $path Path on the website, optionally ending with "#section"
     * @param string $content Identifies the link that was clicked
     * @return string
     */
    public function getWebsiteUrl(string $path = '', string $content = 'panel'): string
    {
        $fragment = '';
        $hashPosition = strpos($path, '#');
        if ($hashPosition !== false) {
            $fragment = substr($path, $hashPosition);
            $path = substr($path, 0, $hashPosition);
        }
        $query = http_build_query([
            'utm_source' => self::UTM_SOURCE,
            'utm_medium' => self::UTM_MEDIUM,
            'utm_campaign' => self::UTM_CAMPAIGN,
            'utm_content' => $content,
        ]);

        return self::WEBSITE_URL . ltrim($path, '/') . '?' . $query . $fragment;
    }

    /**
     * Get the Reva extensions with their install status
     *
     * @return array[]
     */
    public function getExtensions(): array
    {
        $extensions = [
            [
                'module' => 'Reva_SlidingCart',
                'name' => __('Sliding Cart'),
                'description' => __(
                    'Slide-in mini cart with an order summary, tax and totals, a shipping notice and quick checkout.'
                ),
            ],
            [
                'module' => 'Reva_PopupDisplay',
                'name' => __('Add To Cart Popup'),
                'description' => __(
                    'Confirms every add to cart with a popup showing the product, chosen options and checkout buttons.'
                ),
            ],
            [
                'module' => 'Reva_AddToMenu',
                'name' => __('Add To Menu'),
                'description' => __(
                    'Adds custom links and a vertical category menu to the store navigation, no theme changes needed.'
                ),
            ],
        ];

        foreach ($extensions as &$extension) {
            $extension['installed'] = $this->moduleManager->isEnabled($extension['module']);
        }

        return $extensions;
    }

    /**
     * Get the services offered by RevaMagento
     *
     * @return \Magento\Framework\Phrase[]
     */
    public function getServices(): array
    {
        return [
            __('Magento 2 development'),
            __('Marketplace extensions'),
            __('Custom themes'),
            __('Hyvä storefronts'),
            __('Magento 1 support'),
            __('SEO optimization'),
            __('Performance tuning'),
            __('Digital marketing'),
        ];
    }

    /**
     * Get the logo URL
     *
     * @return string
     */
    public function getLogoUrl(): string
    {
        return $this->getViewFileUrl('Reva_Core::images/reva.png');
    }
}
