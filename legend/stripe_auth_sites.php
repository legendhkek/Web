<?php
/**
 * Stripe Auth Site Manager
 *
 * Handles persistence for Stripe-auth enabled domains and manages rotation
 * so that each site is used for a fixed number of requests before switching
 * to the next one. Data is stored in JSON under data/stripe_auth_sites.json.
 */

class StripeAuthSites
{
    private const DATA_FILE = __DIR__ . '/data/stripe_auth_sites.json';
    private const DEFAULT_PER_SITE_LIMIT = 20;

    /**
     * Get all sites with metadata.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAllSites(): array
    {
        $data = self::loadData();
        return $data['sites'];
    }

    /**
     * Get all active site URLs.
     *
     * @return array<int, string>
     */
    public static function getActiveSites(): array
    {
        $sites = self::getAllSites();
        $active = [];
        foreach ($sites as $site) {
            if (!empty($site['active'])) {
                $active[] = $site['url'];
            }
        }
        return $active;
    }

    /**
     * Get rotation configuration. Ensures defaults are present.
     *
     * @return array<string, int>
     */
    public static function getRotationState(): array
    {
        $data = self::loadData();
        return [
            'current_index' => (int) ($data['rotation']['current_index'] ?? 0),
            'current_usage' => (int) ($data['rotation']['current_usage'] ?? 0),
            'per_site_limit' => (int) ($data['rotation']['per_site_limit'] ?? self::DEFAULT_PER_SITE_LIMIT),
        ];
    }

    /**
     * Update per-site rotation limit.
     */
    public static function setPerSiteLimit(int $limit): void
    {
        $limit = max(1, $limit);
        self::transact(function (&$data) use ($limit) {
            $data['rotation']['per_site_limit'] = $limit;
        });
    }

    /**
     * Reset rotation counter and optionally set starting index.
     */
    public static function resetRotation(int $startIndex = 0): void
    {
        self::transact(function (&$data) use ($startIndex) {
            $total = count($data['sites']);
            if ($total === 0) {
                $data['rotation']['current_index'] = 0;
            } else {
                $data['rotation']['current_index'] = max(0, min($startIndex, $total - 1));
            }
            $data['rotation']['current_usage'] = 0;
        });
    }

    /**
     * Add a new site to the pool.
     *
     * @throws InvalidArgumentException
     */
    public static function addSite(string $url, ?int $addedById = null, ?string $addedByUsername = null): void
    {
        $normalizedUrl = self::normalizeUrl($url);

        self::transact(function (&$data) use ($normalizedUrl, $addedById, $addedByUsername, $url) {
            foreach ($data['sites'] as $site) {
                if (strcasecmp($site['url'], $normalizedUrl) === 0) {
                    throw new InvalidArgumentException('Site already exists in rotation.');
                }
            }

            $entry = [
                'url' => $normalizedUrl,
                'active' => true,
                'added_at' => date('c'),
            ];

            if ($addedById !== null) {
                $entry['added_by'] = $addedById;
            } else {
                $entry['added_by'] = 'system';
            }

            if ($addedByUsername !== null) {
                $entry['added_by_username'] = $addedByUsername;
            }

            $data['sites'][] = $entry;
        });
    }

    /**
     * Remove a site by URL.
     */
    public static function removeSite(string $url): bool
    {
        $normalizedUrl = self::normalizeUrl($url);
        $removed = false;

        self::transact(function (&$data) use ($normalizedUrl, &$removed) {
            $filtered = [];
            foreach ($data['sites'] as $site) {
                if (strcasecmp($site['url'], $normalizedUrl) === 0) {
                    $removed = true;
                    continue;
                }
                $filtered[] = $site;
            }
            if ($removed) {
                $data['sites'] = array_values($filtered);
                // Reset rotation if index now out of range
                $currentIndex = (int) ($data['rotation']['current_index'] ?? 0);
                if ($currentIndex >= count($data['sites'])) {
                    $data['rotation']['current_index'] = 0;
                    $data['rotation']['current_usage'] = 0;
                }
            }
        });

        return $removed;
    }

    /**
     * Toggle a site's active status.
     */
    public static function setSiteActive(string $url, bool $active): bool
    {
        $normalizedUrl = self::normalizeUrl($url);
        $updated = false;

        self::transact(function (&$data) use ($normalizedUrl, $active, &$updated) {
            foreach ($data['sites'] as &$site) {
                if (strcasecmp($site['url'], $normalizedUrl) === 0) {
                    $site['active'] = $active;
                    $updated = true;
                    break;
                }
            }
        });

        return $updated;
    }

    /**
     * Fetch the next site for use, rotated according to usage limit.
     *
     * @throws RuntimeException when no active sites are available.
     */
    public static function getNextSite(): string
    {
        return self::transact(function (&$data) {
            $sites = $data['sites'];
            if (empty($sites)) {
                throw new RuntimeException('No sites configured for Stripe auth.');
            }

            $limit = (int) ($data['rotation']['per_site_limit'] ?? self::DEFAULT_PER_SITE_LIMIT);
            if ($limit < 1) {
                $limit = self::DEFAULT_PER_SITE_LIMIT;
            }

            $currentIndex = (int) ($data['rotation']['current_index'] ?? 0);
            $currentUsage = (int) ($data['rotation']['current_usage'] ?? 0);
            $total = count($sites);

            $currentIndex = $currentIndex % max($total, 1);

            $selectedIndex = self::findNextActiveIndex($sites, $currentIndex);
            if ($selectedIndex === null) {
                throw new RuntimeException('No active Stripe auth sites available.');
            }

            if ($selectedIndex !== $currentIndex) {
                $currentUsage = 0;
                $currentIndex = $selectedIndex;
            }

            if ($currentUsage >= $limit) {
                $selectedIndex = self::findNextActiveIndex($sites, $currentIndex + 1);
                if ($selectedIndex === null) {
                    throw new RuntimeException('No active Stripe auth sites available.');
                }
                $currentIndex = $selectedIndex;
                $currentUsage = 0;
            }

            $data['rotation']['current_index'] = $currentIndex;
            $data['rotation']['current_usage'] = $currentUsage + 1;

            return $sites[$currentIndex]['url'];
        });
    }

    /**
     * Run a callback with exclusive lock on the data file.
     *
     * @template T
     * @param callable(array&):T $callback Callback receives data array by reference.
     * @return T
     */
    private static function transact(callable $callback)
    {
        $file = self::DATA_FILE;
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $handle = fopen($file, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open Stripe auth data file.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to acquire lock for Stripe auth data.');
            }

            rewind($handle);
            $contents = stream_get_contents($handle);
            if ($contents === false || trim($contents) === '') {
                $data = self::defaultData();
            } else {
                $decoded = json_decode($contents, true);
                if (!is_array($decoded)) {
                    $data = self::defaultData();
                } else {
                    $data = self::ensureStructure($decoded);
                }
            }

            $result = $callback($data);

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($handle);

            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return $result;
    }

    /**
     * Load data without modifying rotation (read-only).
     */
    private static function loadData(): array
    {
        $file = self::DATA_FILE;
        if (!file_exists($file)) {
            return self::defaultData();
        }

        $contents = file_get_contents($file);
        if ($contents === false || trim($contents) === '') {
            return self::defaultData();
        }

        $data = json_decode($contents, true);
        if (!is_array($data)) {
            return self::defaultData();
        }

        return self::ensureStructure($data);
    }

    /**
     * Ensure required keys exist in data array.
     */
    private static function ensureStructure(array $data): array
    {
        if (!isset($data['sites']) || !is_array($data['sites'])) {
            $data['sites'] = [];
        }

        if (!isset($data['rotation']) || !is_array($data['rotation'])) {
            $data['rotation'] = [];
        }

        $data['rotation']['current_index'] = (int) ($data['rotation']['current_index'] ?? 0);
        $data['rotation']['current_usage'] = (int) ($data['rotation']['current_usage'] ?? 0);
        $data['rotation']['per_site_limit'] = (int) ($data['rotation']['per_site_limit'] ?? self::DEFAULT_PER_SITE_LIMIT);

        return $data;
    }

    /**
     * Default data structure.
     */
    private static function defaultData(): array
    {
        return [
            'rotation' => [
                'current_index' => 0,
                'current_usage' => 0,
                'per_site_limit' => self::DEFAULT_PER_SITE_LIMIT,
            ],
            'sites' => [],
        ];
    }

    /**
     * Find next active index starting from a given position.
     */
    private static function findNextActiveIndex(array $sites, int $startIndex): ?int
    {
        $total = count($sites);
        if ($total === 0) {
            return null;
        }

        for ($offset = 0; $offset < $total; $offset++) {
            $idx = ($startIndex + $offset) % $total;
            if (!empty($sites[$idx]['active'])) {
                return $idx;
            }
        }

        return null;
    }

    /**
     * Normalise URL to https scheme and consistent casing.
     *
     * @throws InvalidArgumentException
     */
    private static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new InvalidArgumentException('Site URL cannot be empty.');
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            throw new InvalidArgumentException('Invalid site URL provided.');
        }

        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        // Normalise path (remove trailing slash unless root)
        if ($path === '') {
            $path = '';
        } else {
            $path = rtrim($path, '/');
            if ($path !== '' && $path[0] !== '/') {
                $path = '/' . $path;
            }
        }

        return 'https://' . $host . $path . $query . $fragment;
    }
}
