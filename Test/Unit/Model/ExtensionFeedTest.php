<?php

declare(strict_types=1);

/**
 * Reva Magento 2
 *
 * @category   Reva
 * @package    Reva_Core
 * @copyright  © 2024 - RevaMagento - All rights reserved
 */

namespace Reva\Core\Test\Unit\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\ClientFactory;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Reva\Core\Model\ExtensionFeed;

class ExtensionFeedTest extends TestCase
{
    private const VALID = '{"extensions":[{"img":"/products/a.png","name":"Stock & Price Alert",'
        . '"module":"Reva_StockPriceAlert","premium":true,"version":"1.0.0","desc":"Alerts.",'
        . '"feats":["One","Two"],"url":"https://commercemarketplace.adobe.com/reva-stockpricealert.html"},'
        . '{"name":"Sliding Cart","module":"Reva_SlidingCart","premium":false,"desc":"Cart.",'
        . '"url":"/products/sliding-cart"},'
        . '{"name":"No module","desc":"Skipped, like entries the website lists before they get a module."}]}';

    /**
     * @var array<string, string>
     */
    private array $cacheData = [];

    /**
     * @var int
     */
    private int $downloads = 0;

    /**
     * @param bool $enabled
     * @param int $status
     * @param string $body
     * @return ExtensionFeed
     */
    private function createFeed(bool $enabled = true, int $status = 200, string $body = self::VALID): ExtensionFeed
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(fn ($id) => $this->cacheData[$id] ?? false);
        $cache->method('save')->willReturnCallback(function ($data, $id) {
            $this->cacheData[$id] = $data;

            return true;
        });
        $client = $this->createStub(ClientInterface::class);
        $client->method('get')->willReturnCallback(function () {
            $this->downloads++;
        });
        $client->method('getStatus')->willReturn($status);
        $client->method('getBody')->willReturn($body);
        $clientFactory = $this->createStub(ClientFactory::class);
        $clientFactory->method('create')->willReturn($client);
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('isSetFlag')->willReturn($enabled);

        return new ExtensionFeed(
            $clientFactory,
            $cache,
            new Json(),
            $config,
            $this->createStub(LoggerInterface::class)
        );
    }

    public function testValidFeedIsDownloadedOnceAndCached(): void
    {
        $feed = $this->createFeed();

        $premium = $feed->getExtensions(ExtensionFeed::TYPE_PREMIUM);
        $free = $feed->getExtensions(ExtensionFeed::TYPE_FREE);

        $this->assertCount(1, $premium);
        $this->assertSame('Reva_StockPriceAlert', $premium[0]['module']);
        $this->assertSame('Alerts.', $premium[0]['description']);
        $this->assertSame(['One', 'Two'], $premium[0]['highlights']);
        $this->assertSame('https://commercemarketplace.adobe.com/reva-stockpricealert.html', $premium[0]['url']);
        $this->assertCount(1, $free);
        $this->assertSame(ExtensionFeed::WEBSITE_URL . 'products/sliding-cart', $free[0]['url']);
        $this->assertSame([], $free[0]['highlights']);
        $this->assertSame(1, $this->downloads);
    }

    public function testDisabledFeedIsNotDownloaded(): void
    {
        $this->assertNull($this->createFeed(false)->getExtensions(ExtensionFeed::TYPE_PREMIUM));
        $this->assertSame(0, $this->downloads);
    }

    public function testFailedDownloadFallsBackAndIsNotRetriedImmediately(): void
    {
        $feed = $this->createFeed(true, 500, '');

        $this->assertNull($feed->getExtensions(ExtensionFeed::TYPE_PREMIUM));
        $this->assertNull($feed->getExtensions(ExtensionFeed::TYPE_FREE));
        $this->assertSame(1, $this->downloads);
    }

    public function testSectionWithoutEntriesFallsBack(): void
    {
        $feed = $this->createFeed(true, 200, '{"extensions":[{"name":"Paid","module":"Reva_Paid","premium":true,'
            . '"desc":"Only a premium entry."}]}');

        $this->assertCount(1, $feed->getExtensions(ExtensionFeed::TYPE_PREMIUM));
        $this->assertNull($feed->getExtensions(ExtensionFeed::TYPE_FREE));
    }

    public function testInvalidDocumentIsRejected(): void
    {
        $feed = $this->createFeed();

        $this->assertNull($feed->parse('not json'));
        $this->assertNull($feed->parse('{"premium":[],"free":[]}'));
        $this->assertNull($feed->parse('{"extensions":"x"}'));
        // The website's file without "module" fields: nothing usable, so the bundled list is shown.
        $this->assertNull($feed->parse('{"extensions":[{"name":"Sliding Cart","desc":"Cart."}]}'));
    }

    public function testUnsafeValuesAreCleanedOrDropped(): void
    {
        $result = $this->createFeed()->parse(json_encode(['extensions' => [
            ['module' => 'bad module', 'name' => 'X', 'desc' => 'Y'],
            ['module' => 'Reva_NoName', 'desc' => 'Y'],
            [
                'module' => 'Reva_Ok',
                'premium' => true,
                'name' => '<script>alert(1)</script>Ok',
                'desc' => "Line\none",
                'feats' => ['A', ['nested'], 5],
                'url' => 'https://evil.example/',
            ],
            ['module' => 'Reva_Http', 'premium' => 'yes', 'name' => 'H', 'desc' => 'H',
                'url' => 'http://commercemarketplace.adobe.com/x'],
            ['module' => 'Reva_Proto', 'name' => 'P', 'desc' => 'P', 'url' => '//evil.example/x'],
            ['module' => 'Reva_Js', 'name' => 'J', 'desc' => 'J', 'url' => 'javascript:alert(1)'],
            ['module' => 'Reva_Dots', 'name' => 'D', 'desc' => 'D', 'path' => '../admin'],
        ]]));

        $this->assertCount(1, $result['premium']);
        $this->assertSame('Reva_Ok', $result['premium'][0]['module']);
        $this->assertSame('alert(1)Ok', $result['premium'][0]['name']);
        $this->assertSame('Line one', $result['premium'][0]['description']);
        $this->assertSame(['A'], $result['premium'][0]['highlights']);
        $this->assertSame('', $result['premium'][0]['url']);
        // "premium" must be exactly true; other values count as free.
        $this->assertSame(['Reva_Http', 'Reva_Proto', 'Reva_Js', 'Reva_Dots'], array_column($result['free'], 'module'));
        $this->assertSame(['', '', '', ''], array_column($result['free'], 'url'));
        $this->assertSame('#services', $result['free'][3]['path']);
    }
}
