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

namespace Reva\Core\Block\Adminhtml;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Class Color Picker Block
 */
class ColorPicker extends Field
{
    /**
     * Get Element Html.
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $html = $element->getElementHtml();
        $htmlId = $this->_escaper->escapeJs($element->getHtmlId());
        $color = $this->getHexColor($element->getData('value'));

        $html .= '<script>
            require([
                "jquery",
                "jquery/colorpicker/js/colorpicker",
                "domReady!"
                ], function ($) {
                var el = $("#' . $htmlId . '");

                if ("' . $color . '") {
                    el.css("background-color", "#' . $color . '");
                }
                el.ColorPicker({
                    layout: "hex",
                    onChange: function (hsb, hex, rgb) {
                        el.css("background-color", "#" + hex);
                        el.val(hex);
                    }
                }).keyup(function () {
                    var value = el.val();
                    $(this).ColorPickerSetColor(value);
                    el.css("background-color", "#" + value);
                });
            });
            </script>';

        return $html;
    }

    /**
     * Keep only the hexadecimal digits of a stored color, without the leading "#"
     *
     * @param mixed $value
     * @return string
     */
    public function getHexColor($value): string
    {
        return substr((string)preg_replace('/[^0-9a-f]/i', '', (string)$value), 0, 8);
    }
}
