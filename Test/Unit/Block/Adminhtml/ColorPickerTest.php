<?php

declare(strict_types=1);

/**
 * Reva Magento 2
 *
 * @category   Reva
 * @package    Reva_Core
 * @copyright  © 2024 - RevaMagento - All rights reserved
 */

namespace Reva\Core\Test\Unit\Block\Adminhtml;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Escaper;
use PHPUnit\Framework\TestCase;
use Reva\Core\Block\Adminhtml\ColorPicker;

class ColorPickerTest extends TestCase
{
    /**
     * @var ColorPicker
     */
    private ColorPicker $block;

    protected function setUp(): void
    {
        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturnCallback(fn ($type) => $this->createStub($type));
        ObjectManager::setInstance($objectManager);

        $context = $this->createStub(Context::class);
        $context->method('getEscaper')->willReturn(new Escaper());
        $this->block = new ColorPicker($context);
    }

    public function testGetHexColorStripsEverythingButHexDigits(): void
    {
        $this->assertSame('1e90ff', $this->block->getHexColor('#1e90ff'));
        $this->assertSame('', $this->block->getHexColor(null));
        $this->assertSame('ae1ff', $this->block->getHexColor('");alert(1);//ff'));
    }

    public function testRenderedScriptCannotBeBrokenByStoredValue(): void
    {
        $element = $this->createStub(AbstractElement::class);
        $element->method('getElementHtml')->willReturn('<input id="reva_color"/>');
        $element->method('getHtmlId')->willReturn('reva_color');
        $element->method('getData')->willReturn('"); alert(document.cookie); ("');

        $method = new \ReflectionMethod(ColorPicker::class, '_getElementHtml');
        $html = $method->invoke($this->block, $element);

        $this->assertStringStartsWith('<input id="reva_color"/>', $html);
        $this->assertStringContainsString('$("#reva_color")', $html);
        $this->assertStringNotContainsString('alert', $html);
        $this->assertStringContainsString('el.css("background-color", "#aedcece");', $html);
    }
}
