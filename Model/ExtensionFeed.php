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

namespace Reva\Core\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\ClientFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Online list of Reva extensions for the Information & Marketplace panel.
 *
 * The list is the public JSON file the RevaMagento website itself uses, so new extensions appear without a module
 * update. Each extension needs "module" (to show "Installed") and "premium": true for paid extensions.
 * Only a plain GET request is made: no store data is sent. The result is cached for a day (failures for an hour),
 * the request gives up after a few seconds, and every value is validated. When the feed is disabled, unreachable
 * or invalid, callers fall back to the list bundled with the module.
 */
class ExtensionFeed
{
    public const WEBSITE_URL = 'https://revacloudflare.harishkhant267.workers.dev/';
    public const FEED_URL = self::WEBSITE_URL . 'reva-extensions.json';
    public const XML_PATH_ENABLED = 'reva_config/reva_menu/remote_feed';
    public const TYPE_PREMIUM = 'premium';
    public const TYPE_FREE = 'free';

    private const CACHE_ID = 'REVA_CORE_EXTENSION_FEED';
    private const CACHE_TAG = 'REVA_CORE_EXTENSION_FEED';
    private const CACHE_LIFETIME = 86400;
    private const FAILURE_LIFETIME = 3600;
    private const FAILURE_MARKER = 'failed';
    private const TIMEOUT = 3;
    private const MAX_BYTES = 102400;
    private const MAX_ITEMS = 30;
    private const MAX_HIGHLIGHTS = 5;
    private const MODULE_PATTERN = '/^[A-Z][A-Za-z0-9]*_[A-Z][A-Za-z0-9]*$/';
    private const ALLOWED_HOSTS = ['revacloudflare.harishkhant267.workers.dev', 'commercemarketplace.adobe.com'];
    private const PATH_PATTERN = '/^[A-Za-z0-9\/._-]*(#[A-Za-z0-9_-]+)?$/';

    /**
     * @param ClientFactory $httpClientFactory
     * @param CacheInterface $cache
     * @param Json $json
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ClientFactory $httpClientFactory,
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Validated extensions of a type from the online list, or null to use the bundled list.
     *
     * Null is also returned when the online list has no extension of that type.
     *
     * @param string $type One of the TYPE_* constants
     * @return array<int, array{module: string, name: string, description: string, highlights: string[],
     *     path: string}>|null
     */
    public function getExtensions(string $type): ?array
    {
        $feed = $this->getFeed();
        // An empty section also falls back, so the panel never shows an empty card.
        if ($feed === null || empty($feed[$type])) {
            return null;
        }

        return $feed[$type];
    }

    /**
     * Whether the online list may be used.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED);
    }

    /**
     * Validated feed from the cache or the website.
     *
     * @return array<string, array<int, array<string, mixed>>>|null
     */
    private function getFeed(): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }
        $cached = $this->cache->load(self::CACHE_ID);
        if ($cached === self::FAILURE_MARKER) {
            return null;
        }
        if (is_string($cached) && $cached !== '') {
            $feed = $this->parse($cached);
            if ($feed !== null) {
                return $feed;
            }
        }

        $body = $this->download();
        $feed = $body === null ? null : $this->parse($body);
        if ($feed === null) {
            $this->cache->save(self::FAILURE_MARKER, self::CACHE_ID, [self::CACHE_TAG], self::FAILURE_LIFETIME);

            return null;
        }
        $this->cache->save((string)$body, self::CACHE_ID, [self::CACHE_TAG], self::CACHE_LIFETIME);

        return $feed;
    }

    /**
     * Download the feed, or null on any problem.
     *
     * @return string|null
     */
    private function download(): ?string
    {
        try {
            $client = $this->httpClientFactory->create();
            $client->setTimeout(self::TIMEOUT);
            $client->setOptions([CURLOPT_CONNECTTIMEOUT => self::TIMEOUT, CURLOPT_FOLLOWLOCATION => false]);
            $client->addHeader('Accept', 'application/json');
            $client->get(self::FEED_URL);
            $body = $client->getBody();
            if ($client->getStatus() !== 200 || $body === '' || strlen($body) > self::MAX_BYTES) {
                return null;
            }

            return $body;
        } catch (\Throwable $e) {
            $this->logger->info('Reva Core: the online extension list could not be loaded: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Decode and validate the feed; invalid entries are dropped, an invalid document gives null.
     *
     * The feed is the same file the RevaMagento website uses for its extension list:
     * {"extensions": [{"name", "module", "premium", "desc", "feats", "url", ...}, ...]}. Entries without a valid
     * "module" are skipped; a document without any valid entry is treated as unusable.
     *
     * @param string $body
     * @return array<string, array<int, array<string, mixed>>>|null
     */
    public function parse(string $body): ?array
    {
        try {
            $data = $this->json->unserialize($body);
        } catch (\InvalidArgumentException $e) {
            return null;
        }
        if (!is_array($data) || !isset($data['extensions']) || !is_array($data['extensions'])) {
            return null;
        }

        $feed = [self::TYPE_PREMIUM => [], self::TYPE_FREE => []];
        foreach (array_slice(array_values($data['extensions']), 0, self::MAX_ITEMS) as $item) {
            $extension = is_array($item) ? $this->validateItem($item) : null;
            if ($extension !== null) {
                $feed[($item['premium'] ?? false) === true ? self::TYPE_PREMIUM : self::TYPE_FREE][] = $extension;
            }
        }
        if (!$feed[self::TYPE_PREMIUM] && !$feed[self::TYPE_FREE]) {
            return null;
        }

        return $feed;
    }

    /**
     * One validated extension, or null when a required value is missing or malformed.
     *
     * @param array<mixed> $item
     * @return array{module: string, name: string, description: string, highlights: string[], path: string,
     *     url: string}|null
     */
    private function validateItem(array $item): ?array
    {
        $module = $this->text($item['module'] ?? null, 100);
        $name = $this->text($item['name'] ?? null, 80);
        $description = $this->text($item['desc'] ?? $item['description'] ?? null, 300);
        if ($module === '' || !preg_match(self::MODULE_PATTERN, $module) || $name === '' || $description === '') {
            return null;
        }
        $highlights = [];
        $rawHighlights = $item['feats'] ?? $item['highlights'] ?? [];
        foreach (array_slice(is_array($rawHighlights) ? $rawHighlights : [], 0, self::MAX_HIGHLIGHTS) as $text) {
            $text = $this->text($text, 120);
            if ($text !== '') {
                $highlights[] = $text;
            }
        }

        return [
            'module' => $module,
            'name' => $name,
            'description' => $description,
            'highlights' => $highlights,
            'path' => $this->safePath($item['path'] ?? null),
            'url' => $this->safeUrl($item['url'] ?? null),
        ];
    }

    /**
     * A path on the RevaMagento website, or "#services".
     *
     * @param mixed $value
     * @return string
     */
    private function safePath(mixed $value): string
    {
        $path = $this->text($value, 200);
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '//')
            || !preg_match(self::PATH_PATTERN, $path)
        ) {
            return '#services';
        }

        return $path;
    }

    /**
     * An https URL on the RevaMagento website or the Adobe Commerce Marketplace, or an empty string.
     *
     * Paths such as "/products/x" are resolved against the RevaMagento website.
     *
     * @param mixed $value
     * @return string
     */
    private function safeUrl(mixed $value): string
    {
        $url = $this->text($value, 300);
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $url = rtrim(self::WEBSITE_URL, '/') . $url;
        }
        $hosts = implode('|', array_map(static fn (string $host) => preg_quote($host, '#'), self::ALLOWED_HOSTS));
        $pattern = '#^https://(' . $hosts . ')(/[A-Za-z0-9/._~%+-]*)?(\?[A-Za-z0-9=&._~%+-]*)?(\#[A-Za-z0-9_-]*)?$#i';
        if (str_contains($url, '..') || !preg_match($pattern, $url)) {
            return '';
        }

        return $url;
    }

    /**
     * Single-line plain text limited to a length, or an empty string.
     *
     * @param mixed $value
     * @param int $maxLength
     * @return string
     */
    private function text(mixed $value, int $maxLength): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = trim((string)preg_replace('/\s+/u', ' ', strip_tags($value)));

        return mb_substr($value, 0, $maxLength);
    }
}
