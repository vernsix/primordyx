<?php
/**
 * File: /vendor/vernsix/primordyx/src/BotDetector.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/BotDetector.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Security;

use Exception;
use InvalidArgumentException;
use Primordyx\Events\EventManager;
use RuntimeException;
use Throwable;

/**
 * Comprehensive Bot Detection System
 *
 * Provides multi-layered bot detection through user agent analysis, request patterns,
 * header validation, behavioral indicators, and fingerprinting. Designed to protect
 * applications from automated traffic, scrapers, and malicious bots while allowing
 * legitimate crawlers and users.
 *
 * ## Key Detection Mechanisms
 * - **User Agent Analysis**: Detects known bot patterns and suspicious agents
 * - **Header Validation**: Identifies missing or suspicious HTTP headers
 * - **Request Fingerprinting**: Creates and validates signed fingerprints for tracking
 * - **Behavioral Analysis**: Detects honeypot triggers and suspicious patterns
 * - **Path Protection**: Monitors access to sensitive endpoints
 * - **Rate Limiting**: Pluggable rate limiting system integration
 * - **Scoring System**: Provides weighted probability scores (0.0-1.0)
 *
 * ## Architecture Overview
 * The detector uses a scoring system that combines multiple detection signals:
 * - 0.0 = Definitely human
 * - 0.3-0.4 = Suspicious, consider additional verification
 * - 0.7+ = Likely bot
 * - 1.0 = Definitely bot
 *
 * ## Security Features
 * - **HMAC Fingerprint Signing**: Prevents fingerprint tampering
 * - **Event Logging**: Comprehensive EventManager integration for monitoring
 * - **Defense in Depth**: Multiple detection layers for robust protection
 * - **Graceful Degradation**: Continues functioning even if some checks fail
 *
 * ## Usage Example
 * ```php
 * // Initial setup (once per application)
 * BotDetector::useSecretKey('your-32-character-minimum-secret-key');
 * BotDetector::registerSensitivePaths(['/admin', '/api/private']);
 *
 * // Per-request detection
 * if (BotDetector::isLikelyBot()) {
 *     http_response_code(429);
 *     exit('Bot traffic detected');
 * }
 *
 * // Detailed scoring
 * $score = BotDetector::score();
 * if ($score >= 0.4) {
 *     requireCaptcha();
 * }
 * ```
 *
 * @see EventManager For event handling and monitoring
 * @package Primordyx
 * @since 1.0.0
 */
class BotDetector
{
    protected static array $sensitivePaths = [];
    protected static ?string $secretKey = null;

    /** @var callable|null */
    protected static $rateLimitCallback = null;

    /** @var array|string[] */
    public static array $botMap = [
        'adsbot' => 'Google AdsBot',
        'ahrefsbot' => 'Ahrefs',
        'amazonbot' => 'AmazonBot',
        'applebot' => 'AppleBot',
        'archive.org_bot' => 'Internet Archive',
        'baiduspider' => 'Baidu',
        'bingbot' => 'BingBot',
        'bitlybot' => 'Bitly',
        'bot' => 'Generic Bot',
        'bytespider' => 'ByteDance Spider',
        'censysinspect' => 'Censys',
        'checkmarknetwork' => 'Checkmark Network',
        'chrome-lighthouse' => 'Google Lighthouse',
        'cloudflare' => 'Cloudflare',
        'curl' => 'cURL',
        'datadog' => 'Datadog Agent',
        'discordbot' => 'Discord Bot',
        'dotbot' => 'DotBot',
        'duckduckbot' => 'DuckDuckGo',
        'facebookexternalhit' => 'Facebook Crawler',
        'facebot' => 'Facebook Bot',
        'fetch' => 'Generic Fetch Client',
        'gigabot' => 'Gigablast Bot',
        'googlebot' => 'Googlebot',
        'google' => 'Google (General)',
        'gptbot' => 'OpenAI GPTBot',
        'headless' => 'Headless Browser',
        'httpclient' => 'HTTP Client',
        'http_request2' => 'PEAR HTTP_Request2',
        'ia_archiver' => 'Alexa (Amazon)',
        'insights' => 'Microsoft Insights',
        'ioncrawl' => 'IonCrawl',
        'java/' => 'Java Client',
        'libwww-perl' => 'libwww-perl',
        'linkedinbot' => 'LinkedInBot',
        'ltx71' => 'LTX71',
        'mediapartners-google' => 'Google AdSense',
        'mj12bot' => 'Majestic-12',
        'monitoring' => 'Monitoring Agent',
        'msnbot' => 'MSN Bot',
        'nagios' => 'Nagios Checker',
        'naverbot' => 'Naver Bot',
        'netcraft' => 'Netcraft',
        'newrelicpinger' => 'NewRelic',
        'nutch' => 'Apache Nutch',
        'openuabot' => 'OpenUA Bot',
        'outbrain' => 'Outbrain Bot',
        'panscient' => 'Panscient Bot',
        'petalbot' => 'Huawei PetalBot',
        'phantomjs' => 'PhantomJS',
        'pingdom' => 'Pingdom',
        'postman' => 'Postman Runtime',
        'python' => 'Python Script',
        'qwantbot' => 'Qwant Bot',
        'rogerbot' => 'Rogerbot (Moz)',
        'scrapy' => 'Scrapy Framework',
        'searchmetricsbot' => 'SearchMetrics',
        'semrush' => 'SEMRush',
        'seokicks-robot' => 'SEO Kicks',
        'serpstatbot' => 'Serpstat Bot',
        'serpworx' => 'SERPWorx',
        'shodan' => 'Shodan Bot',
        'siteauditbot' => 'SiteAuditBot',
        'sitecheckerbot' => 'SiteCheckerBot',
        'slackbot' => 'Slack Bot',
        'smtbot' => 'SMTBot',
        'sogou' => 'Sogou Spider',
        'spbot' => 'SEO Profiler Bot',
        'spider' => 'Generic Spider',
        'surveybot' => 'SurveyBot',
        'telegrambot' => 'Telegram Bot',
        'testcertificatechain' => 'Cert Validation Bot',
        'trustpilot' => 'TrustPilot Bot',
        'twitterbot' => 'Twitter Bot',
        'uptimerobot' => 'UptimeRobot',
        'vagabondbot' => 'Vagabond Bot',
        'vkshare' => 'VKontakte Bot',
        'wget' => 'Wget',
        'whatsapp' => 'WhatsApp Bot',
        'yacybot' => 'YaCy Bot',
        'yahoo! slurp' => 'Yahoo Slurp',
        'yahooseeker' => 'Yahoo Seeker',
        'yandex' => 'Yandex Bot',
        'zoominfo' => 'ZoomInfo Bot'
    ];

    /**
     * Set the secret key for fingerprint signing
     *
     * @since 1.0.0
     * @param string $secretKey A strong secret key (minimum 32 characters)
     * @return void
     * @throws InvalidArgumentException If secret key is empty or too short
     * @fires BotDetector.secretKeySet When secret key is successfully configured
     */
    public static function useSecretKey(string $secretKey): void
    {
        if (empty($secretKey)) {
            throw new InvalidArgumentException('Secret key cannot be empty');
        }

        if (strlen($secretKey) < 32) {
            throw new InvalidArgumentException('Secret key must be at least 32 characters long for HMAC-SHA256 security');
        }

        self::$secretKey = $secretKey;

        EventManager::fire('BotDetector.secretKeySet', [
            'key_length' => strlen($secretKey),
            'timestamp' => time()
        ]);
    }

    /**
     * Check if BotDetector has been configured with a secret key
     *
     * @since 1.0.0
     * @return bool True if configured with secret key, false otherwise
     */
    public static function isConfigured(): bool
    {
        return self::$secretKey !== null;
    }

    /**
     * Generate a cryptographically secure random key for development/testing
     *
     * @param int $length Length of the generated key (default: 64, minimum: 32)
     * @return string A cryptographically secure random hexadecimal string
     * @throws InvalidArgumentException If length is less than 32
     * @throws Exception If cryptographically secure random bytes cannot be generated
     * @since 1.0.0
     */
    public static function generateSecretKey(int $length = 64): string
    {
        if ($length < 32) {
            throw new InvalidArgumentException('Key length must be at least 32 characters');
        }

        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Assert that configuration is complete
     *
     * @since 1.0.0
     * @return void
     * @throws RuntimeException If not configured
     */
    protected static function assertConfigured(): void
    {
        if (!self::isConfigured()) {
            throw new RuntimeException(
                'BotDetector is not configured. Call BotDetector::useSecretKey($secretKey) first. ' .
                'You can generate a key using BotDetector::generateSecretKey()'
            );
        }
    }

    /**
     * Update or add a bot pattern to the detection map
     *
     * @since 1.0.0
     * @param string $key   Bot identifier (lowercase user agent substring)
     * @param string $value Human-readable bot name
     * @return void
     */
    public static function updateBotMap(string $key, string $value): void
    {
        self::$botMap[$key] = $value;
    }

    /**
     * Query the bot map for a specific identifier
     *
     * @since 1.0.0
     * @param string $key The bot identifier to look up
     * @return string The bot name if found, 'Unknown' otherwise
     */
    public static function queryBotMap(string $key): string
    {
        return self::$botMap[$key] ?? 'Unknown';
    }

    /**
     * Determines the bot name based on user agent string by checking against known bot patterns
     *
     * @param string $userAgent Optional user agent string. If empty, uses $_SERVER['HTTP_USER_AGENT']
     * @return string The identified bot name or 'Unknown' if no match found
     */
    public static function getBotName(string $userAgent = ''): string
    {
        if (empty($userAgent)) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }

        $userAgent = strtolower($userAgent);

        foreach (self::$botMap as $key => $name) {
            if (str_contains($userAgent, $key)) {
                return $name;
            }
        }

        return 'Unknown';
    }

    /**
     * Collects and returns comprehensive user information from server variables
     *
     * @return array{ip: string, fingerprint: string|null, user_agent: string, referer: string} User information array
     */
    public static function userInfo(): array
    {
        return [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'fingerprint' => self::fingerprint(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
        ];
    }

    /**
     * Sets a callback function for rate limiting checks
     *
     * @param callable $callback Function that returns boolean indicating if rate limited
     * @return void
     */
    public static function useRateLimiter(callable $callback): void
    {
        self::$rateLimitCallback = $callback;
    }

    /**
     * Registers an array of sensitive paths that should trigger bot detection
     *
     * @param array $paths Array of path strings to be considered sensitive
     * @return void
     */
    public static function registerSensitivePaths(array $paths): void
    {
        $paths = array_map('strtolower', $paths);
        self::$sensitivePaths = array_unique(array_merge(self::$sensitivePaths, $paths));
    }

    /**
     * Clears all registered sensitive paths
     *
     * @since 1.0.0
     * @return void
     */
    public static function resetSensitivePaths(): void
    {
        self::$sensitivePaths = [];
    }

    /**
     * Asserts that HTTP headers have not been sent yet, throwing exception if they have
     *
     * @since 1.0.0
     * @return void
     * @throws RuntimeException If headers have already been sent
     */
    public static function assertHeadersNotSent(): void
    {
        if (headers_sent($file, $line)) {
            EventManager::fire('BotDetector.headers.alreadySent', [
                'file' => $file,
                'line' => $line,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
            ]);

            throw new RuntimeException("Headers already sent at $file:$line — cannot modify headers, set cookies, etc.");
        }
    }

    // checks...

    /**
     * Checks if the user agent matches any known bot patterns
     *
     * @param string $userAgent Optional user agent string. If empty, uses $_SERVER['HTTP_USER_AGENT']
     * @return bool True if user agent matches a known bot, false otherwise
     */
    public static function matchesKnownBot(string $userAgent = ''): bool
    {
        $name = self::getBotName($userAgent);

        if ($name !== 'Unknown') {
            EventManager::fire('BotDetector.knownBotDetected', [
                'bot_name' => $name,
                'matched_user_agent' => strtolower($userAgent ?: ($_SERVER['HTTP_USER_AGENT'] ?? '')),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
            ]);
            return true;
        }

        return false;
    }

    /**
     * Checks if the current request path matches any registered sensitive paths
     *
     * @param string $path Optional path to check. If empty, uses $_SERVER['REQUEST_URI']
     * @return bool True if path contains sensitive content, false otherwise
     */
    public static function isHittingSensitivePath(string $path = ''): bool
    {
        if (empty($path)) {
            $path = $_SERVER['REQUEST_URI'] ?? '';
        }

        $path = strtolower($path);

        foreach (self::$sensitivePaths as $needle) {
            if (stripos($path, $needle) !== false) {
                EventManager::fire('BotDetector.sensitive.endpoint', [
                    'path' => $path,
                    'match' => $needle
                ]);
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the request is coming from a local IP address
     *
     * @return bool True if request is from localhost, false otherwise
     */
    public static function isLocalIp(): bool
    {
        return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    }

    /**
     * Determines if the request is likely from a bot based on scoring threshold
     *
     * @return bool True if bot score is >= 0.7, false otherwise
     */
    public static function isLikelyBot(): bool
    {
        return self::score() >= 0.7;
    }

    /**
     * Checks if the Origin header doesn't match the expected host
     *
     * @since 1.0.0
     * @return bool True if origin is invalid or suspicious, false otherwise
     * @fires BotDetector.invalidOrigin When origin doesn't match host
     */
    public static function isInvalidOrigin(): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if ($origin && !str_contains($origin, $host)) {
            EventManager::fire('BotDetector.invalidOrigin', [
                'origin' => $origin,
                'expected_host' => $host,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'fpid_state' => self::fpidState(),
            ]);
            return true;
        }

        return false;
    }

    /**
     * Checks if the request uses uncommon HTTP methods that may indicate bot behavior
     *
     * @since 1.0.0
     * @return bool True if using rare method, false otherwise
     * @fires BotDetector.rareMethod.detected When rare method is used
     */
    public static function isUsingRareMethod(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';

        if (in_array($method, ['TRACE', 'TRACK', 'CONNECT', 'OPTIONS'])) {
            EventManager::fire('BotDetector.rareMethod.detected', [
                'method' => $method,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
            ]);
            return true;
        }

        return false;
    }

    /**
     * Checks if the request is rate limited using the configured callback
     *
     * @since 1.0.0
     * @return bool True if rate limited, false otherwise
     * @fires BotDetector.checkRateLimit When checking rate limit status
     */
    public static function isRateLimited(): bool
    {
        if (is_callable(self::$rateLimitCallback)) {
            return (bool) call_user_func(self::$rateLimitCallback);
        }
        EventManager::fire('BotDetector.checkRateLimit',[]);
        return false;
    }

    /**
     * Check if honeypot field has been filled
     *
     * @since 1.0.0
     * @param string $honeyField Name of the honeypot field (default: 'primordyx_start')
     * @return bool True if honeypot triggered, false otherwise
     * @fires BotDetector.honeypot.detected When honeypot is triggered
     */
    public static function isHoneypot(string $honeyField = 'primordyx_start'): bool
    {
        if (!empty($_POST[$honeyField])) {
            EventManager::fire('BotDetector.honeypot.detected', [
                'field' => $honeyField,
                'value' => $_POST[$honeyField],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
            ]);
            return true;
        }
        return false;
    }

    /**
     * Check if request path contains suspicious patterns
     *
     * Detects common attack patterns, vulnerability scans, and malicious probes
     * in the request path. Checks for things like directory traversal, common
     * vulnerable endpoints, and configuration file access attempts.
     *
     * @since 1.0.0
     * @param string $path Optional path to check (defaults to $_SERVER['REQUEST_URI'])
     * @return bool True if suspicious patterns detected, false otherwise
     * @fires BotDetector.suspicious.path When suspicious path pattern is detected
     */
    public static function isSuspiciousPath(string $path = ''): bool
    {
        if (empty($path)) {
            $path = $_SERVER['REQUEST_URI'] ?? '';
        }

        $needles = [
            'login', 'config', 'db', 'sql', 'aws/credentials', 'docker-compose',

            // Common sensitive files
            '.env', '.git', '.svn', '.DS_Store', 'id_rsa', 'id_rsa.pub',
            'composer.json', 'composer.lock', 'package.json', 'yarn.lock',
            'config.php', 'config.json', 'settings.py', 'settings.ini',
            '.htaccess', '.htpasswd', 'web.config', 'httpd.conf',

            // Backup/database files
            'backup', 'db.sql', 'database.sql', 'dump.sql', 'db_backup',
            'sql.gz', 'mysql.gz', 'database.zip', 'db.zip', 'backup.tar',

            // WordPress/Joomla/Drupal/Magento/etc.
            'wp-admin', 'wp-login', 'wp-content', 'wp-config', 'xmlrpc.php',
            'readme.html', 'license.txt', 'install.php', 'upgrade.php',
            'administrator', 'admin/config', 'admin/login', 'index.php?option=com_',
            'sites/default', 'CHANGELOG.txt', 'user/register',

            // Dev/test/debug stuff
            'phpinfo', 'test.php', 'debug.php', 'info.php', 'xdebug',
            'dev.php', 'build.xml', 'gulpfile.js', 'Gruntfile.js',
            'swagger.json', 'openapi.json',

            // Cloud / provider creds
            '.aws', '.azure', '.gcp', '.env.production', '.env.dev',
            'google-services.json', 'firebase-config.json',

            // Misconfigurations
            '.well-known/security.txt', '.well-known/openid-configuration',
            'server-status', 'server-info', 'phpmyadmin', 'pma',

            // Public admin panels
            'admin', 'cpanel', 'panel', 'dashboard', 'manage', 'console',
            'controlpanel', 'secure', 'superadmin',

            // Exploit attempts
            'cgi-bin', 'shell.php', 'cmd.php', 'upload.php', 'exploit',
            'filemanager', 'editor', 'elfinder', 'browse.php',

            // Common API abuse
            '/api/v1/users', '/api/v1/auth', '/api/login', '/graphql',

            // Traversal or probes
            '../', '..\\', '%2e%2e%2f', '%2e%2e\\', '%252e%252e%255c',

            // Weird trailing extensions or probes
            '.bak', '.old', '.swp', '.tmp', '~', '.orig', '.disabled'

        ];

        foreach ($needles as $needle) {
            if (stripos($path, $needle) !== false) {
                EventManager::fire('BotDetector.suspicious.path', ['path' => $path, 'needle' => $needle]);
                return true;
            }
        }

        return false;
    }

    /**
     * Check if request is malicious (path or payload)
     *
     * @since 1.0.0
     * @return bool True if malicious patterns detected, false otherwise
     */
    public static function isMaliciousRequest(): bool
    {
        return self::isSuspiciousPath() || self::isSuspiciousPayload();
    }

    /**
     * Check if user agent string is empty
     *
     * @since 1.0.0
     * @param string|null $userAgent Optional user agent (defaults to $_SERVER['HTTP_USER_AGENT'])
     * @return bool True if empty, false otherwise
     * @fires BotDetector.userAgent.empty When empty user agent is detected
     */
    public static function isEmptyUserAgent(?string $userAgent = null): bool
    {
        $ua = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');

        if (trim($ua) === '') {
            EventManager::fire('BotDetector.userAgent.empty', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
            ]);
            return true;
        }

        return false;
    }

    /**
     * Check if user agent is suspicious
     *
     * @since 1.0.0
     * @param string|null $userAgent Optional user agent (defaults to $_SERVER['HTTP_USER_AGENT'])
     * @return bool True if suspicious, false otherwise
     */
    public static function isSuspiciousUserAgent(?string $userAgent = null): bool
    {
        $ua = strtolower($userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? '');
        return self::isEmptyUserAgent($ua) || self::matchesKnownBot($ua);
    }

    /**
     * Check if Accept headers are missing or suspicious
     *
     * @since 1.0.0
     * @param string|null $accept Optional Accept header
     * @param string|null $acceptLanguage Optional Accept-Language header
     * @return bool True if suspicious, false otherwise
     * @fires BotDetector.suspicious.headers When suspicious headers detected
     */
    public static function isSuspiciousAcceptHeaders( ?string $accept = null, ?string $acceptLanguage = null ): bool {
        $accept ??= $_SERVER['HTTP_ACCEPT'] ?? '';
        $acceptLanguage ??= $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

        $missing = [];
        if (empty($accept)) {
            $missing[] = 'HTTP_ACCEPT';
        }
        if (empty($acceptLanguage)) {
            $missing[] = 'HTTP_ACCEPT_LANGUAGE';
        }

        if (!empty($missing)) {
            EventManager::fire('BotDetector.suspicious.headers', [
                'missing' => $missing,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            return true;
        }

        return false;
    }

    /**
     * Check if POST request is missing Referer header
     *
     * @since 1.0.0
     * @param string|null $method Optional HTTP method
     * @param string|null $referer Optional Referer header
     * @return bool True if POST without referer, false otherwise
     * @fires BotDetector.suspicious.post.noReferer When detected
     */
    public static function isPostWithoutReferer(?string $method = null, ?string $referer = null): bool
    {
        $method ??= $_SERVER['REQUEST_METHOD'] ?? '';
        $referer ??= $_SERVER['HTTP_REFERER'] ?? '';

        if (strtoupper($method) === 'POST' && empty($referer)) {
            EventManager::fire('BotDetector.suspicious.post.noReferer', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            return true;
        }
        return false;
    }

    /**
     * Check if request has no cookies
     *
     * @since 1.0.0
     * @return bool True if no cookies present, false otherwise
     * @fires BotDetector.noCookies When no cookies detected
     */
    public static function hasNoCookies(): bool
    {
        if (empty($_COOKIE)) {
            EventManager::fire('BotDetector.noCookies', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
            ]);
            return true;
        }

        return false;
    }

    /**
     * Check for missing headers typically present in browsers
     *
     * @since 1.0.0
     * @return bool True if suspicious headers detected, false otherwise
     * @fires BotDetector.suspicious.headers When suspicious headers detected
     */
    public static function hasSuspiciousHeaders(): bool
    {
        if (self::isLocalIp()) {
            return false;
        }

        $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        $missing = [];

        if (stripos($ua, 'mozilla') !== false) {
            if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) $missing[] = 'Accept-Language';
            if (empty($_SERVER['HTTP_ACCEPT_ENCODING'])) $missing[] = 'Accept-Encoding';
            if (empty($_SERVER['HTTP_SEC_CH_UA'])) $missing[] = 'Sec-CH-UA';
        }

        if (!empty($missing)) {
            EventManager::fire('BotDetector.suspicious.headers', [
                'missing' => $missing,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            return true;
        }

        return false;
    }

    /**
     * Analyze request payloads for attack patterns
     *
     * @since 1.0.0
     * @return bool True if suspicious payload detected, false otherwise
     * @fires BotDetector.suspicious.payload When suspicious payload detected
     */
    public static function isSuspiciousPayload(): bool
    {
        $suspectPatterns = [
            '/(union\s+select|select\s+\*|insert\s+into|drop\s+table)/i',
            '/(\%27)|(\')|(\-\-)|(\%23)|(#)/i',
            '/(<script|javascript:|onerror=|alert\s*\()/i',
            '/(php:\/\/|data:\/\/|base64_decode|eval\s*\(|assert\s*\(|cmd=)/i',
            '/(\.\.\/|\.\.\\\\)/'
        ];

        $payloads = array_merge(
            $_GET ?? [],
            $_POST ?? [],
            $_COOKIE ?? [],
            $_REQUEST ?? []
        );

        foreach ($payloads as $key => $value) {
            foreach ($suspectPatterns as $pattern) {
                if (is_string($value) && preg_match($pattern, $value)) {
                    EventManager::fire('BotDetector.suspicious.payload', [
                        'key' => $key,
                        'value' => $value,
                        'pattern' => $pattern
                    ]);
                    return true;
                }
            }
        }

        return false;
    }

    // fingerprint...

    /**
     * Sign or validate the fingerprint cookie
     *
     * Processes the fingerprint cookie to ensure it's properly signed. Handles
     * unsigned cookies by signing them, validates already signed cookies, and
     * clears forged or malformed cookies.
     *
     * @since 1.0.0
     * @return void
     * @throws RuntimeException If headers already sent or not configured
     * @fires BotDetector.signFingerprintCookie Various states during processing
     */
    public static function signFingerprintCookie(): void
    {
        self::assertConfigured();

        $cookie = $_COOKIE['fpid'] ?? '';
        EventManager::fire('BotDetector.signFingerprintCookie', [
            'cookie' => $cookie,
            'message' => 'Cookie provided by browser'
        ]);

        if (!$cookie) {
            EventManager::fire('BotDetector.signFingerprintCookie', [
                'cookie' => $cookie,
                'message' => 'Cookie is not set'
            ]);
            return;
        }

        // If already signed format, verify it
        if (substr_count($cookie, '|') === 1 && preg_match('/^[a-f0-9]{64}\|[a-f0-9]{64}$/i', $cookie)) {
            [$raw, $sig] = explode('|', $cookie, 2);

            if (self::verifySignature($raw, $sig)) {
                EventManager::fire('BotDetector.signFingerprintCookie', [
                    'cookie' => $cookie,
                    'message' => 'Already signed and is valid'
                ]);
                return;
            }

            // Invalid signature — treat as tampered and clear it
            EventManager::fire('BotDetector.signFingerprintCookie', [
                'cookie' => $cookie,
                'message' => 'Invalid signature — clearing forged cookie.'
            ]);
            self::assertHeadersNotSent();
            setcookie('fpid', '', [
                'path' => '/',
                'expires' => time() - 3600,
            ]);
            return;
        }

        // Unsigned — must be raw SHA256
        if (!preg_match('/^[a-f0-9]{64}$/i', $cookie)) {
            EventManager::fire('BotDetector.signFingerprintCookie', [
                'cookie' => $cookie,
                'message' => 'Malformed unsigned cookie — clearing.'
            ]);
            self::assertHeadersNotSent();
            setcookie('fpid', '', [
                'path' => '/',
                'expires' => time() - 3600,
            ]);
            return;
        }

        // Valid unsigned fingerprint — sign and set
        try {
            $sig = self::getSignature($cookie);
            EventManager::fire('BotDetector.signFingerprintCookie', [
                'cookie' => $cookie . '|' . $sig,
                'message' => 'Signed valid fingerprint and setting cookie'
            ]);
            self::assertHeadersNotSent();
            setcookie('fpid', $cookie . '|' . $sig, [
                'path' => '/',
                'expires' => time() + 31536000,
                'samesite' => 'Lax',
                'httponly' => false,
            ]);
        } catch (Throwable $e) {
            EventManager::fire('BotDetector.signFingerprintCookie', [
                'cookie' => $cookie,
                'message' => 'Signing failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Determine the state of the fingerprint cookie
     *
     * @since 1.0.0
     * @return string One of: 'missing', 'malformed', 'unsigned', 'valid', 'forged'
     */
    public static function fpidState(): string
    {
        static $cached = null;
        if ($cached !== null) return $cached;

        $cookie = $_COOKIE['fpid'] ?? '';

        if (empty($cookie)) {
            return $cached = 'missing';
        }

        if (!str_contains($cookie, '|')) {
            if (!preg_match('/^[a-f0-9]{64}$/i', $cookie)) {
                EventManager::fire('BotDetector.invalid.fpid', [
                    'reason' => 'Unsigned cookie present but not a valid SHA256 fingerprint',
                    'cookie' => $cookie,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                ]);
                return $cached = 'malformed';
            }

            return $cached = 'unsigned';
        }

        return $cached = self::isValidFingerprint() ? 'valid' : 'forged';
    }

    /**
     * Retrieve the raw fingerprint value
     *
     * @since 1.0.0
     * @param bool $refresh Whether to refresh the cached result
     * @return string|null The raw fingerprint or null if invalid/missing
     */
    public static function fingerprint(bool $refresh = false): ?string
    {
        static $cached = null;

        if ($refresh) {
            $cached = null;
        }

        if ($cached !== null) {
            return $cached;
        }

        if (!self::isValidFingerprint($refresh)) {
            return $cached = null;
        }

        [$raw] = explode('|', $_COOKIE['fpid'], 2);
        return $cached = $raw;
    }

    /**
     * Validate fingerprint cookie signature
     *
     * @since 1.0.0
     * @param bool $refresh Whether to refresh the cached validation result
     * @return bool True if fingerprint is valid, false otherwise
     * @fires BotDetector.invalid.fpid When fingerprint is invalid
     */
    public static function isValidFingerprint(bool $refresh = false): bool
    {
        static $cached = null;

        if (!$refresh && $cached !== null) {
            return $cached;
        }

        if (!self::isConfigured()) {
            return $cached = false;
        }

        $cookie = $_COOKIE['fpid'] ?? '';

        if (substr_count($cookie, '|') !== 1) {
            EventManager::fire('BotDetector.invalid.fpid', [
                'reason' => 'Malformed: wrong delimiter count',
                'cookie' => $cookie,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            return $cached = false;
        }

        [$raw, $sig] = explode('|', $cookie, 2);
        return $cached = self::verifySignature($raw, $sig);
    }

    /**
     * Generate HMAC signature for fingerprint
     *
     * @since 1.0.0
     * @param string $raw Raw fingerprint (must be valid SHA256 hex)
     * @return string HMAC-SHA256 signature
     * @throws InvalidArgumentException If raw fingerprint format is invalid
     * @throws RuntimeException If not configured
     */
    public static function getSignature(string $raw): string
    {
        self::assertConfigured();

        if (!preg_match('/^[a-f0-9]{64}$/i', $raw)) {
            EventManager::fire('BotDetector.invalid.rawFingerprint', [
                'raw' => $raw,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
            ]);

            throw new InvalidArgumentException("Raw fingerprint must be a valid SHA256 hex string.");
        }

        return hash_hmac('sha256', $raw, self::$secretKey);
    }

    /**
     * Verify fingerprint signature
     *
     * @since 1.0.0
     * @param string $raw Raw fingerprint value
     * @param string $sig Signature to verify
     * @return bool True if signature is valid, false otherwise
     * @fires BotDetector.invalid.signatureFormat When format is invalid
     * @fires BotDetector.signature.mismatch When signature doesn't match
     */
    public static function verifySignature(string $raw, string $sig): bool
    {
        if (!self::isConfigured()) {
            return false;
        }

        // Validate format first
        if (
            !preg_match('/^[a-f0-9]{64}$/i', $raw) ||
            !preg_match('/^[a-f0-9]{64}$/i', $sig)
        ) {
            EventManager::fire('BotDetector.invalid.signatureFormat', [
                'raw' => $raw,
                'sig' => $sig,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
            ]);
            return false;
        }

        $expected = self::getSignature($raw);

        if (!hash_equals($expected, $sig)) {
            EventManager::fire('BotDetector.signature.mismatch', [
                'raw' => $raw,
                'sig' => $sig,
                'expected' => $expected,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
            ]);
            return false;
        }

        return true;
    }

    // score them...

    /**
     * Calculate comprehensive bot detection score
     *
     * Analyzes multiple detection signals and returns a weighted probability
     * score indicating likelihood of bot traffic. The scoring system combines
     * user agent analysis, header validation, behavioral patterns, and
     * fingerprint state to produce a score between 0.0 (human) and 1.0 (bot).
     *
     * ## Scoring Components
     * - **User Agent** (0.4): Suspicious or known bot patterns
     * - **Headers** (0.2-0.3): Missing or suspicious headers
     * - **Request Patterns** (0.1-0.3): Path access, methods, rate limiting
     * - **Fingerprint** (0.1-0.5): Missing, unsigned, or forged fingerprints
     * - **Payload** (0.3-0.4): Honeypot triggers, attack patterns
     *
     * ## Score Interpretation
     * - 0.0-0.2: Very likely human
     * - 0.3-0.4: Suspicious, consider additional verification
     * - 0.5-0.6: Probable bot
     * - 0.7-0.9: Very likely bot
     * - 1.0: Definitely bot (capped maximum)
     *
     * @since 1.0.0
     * @static
     *
     * @return float Bot detection score between 0.0 and 1.0
     *
     * @fires BotDetector.score When score is calculated with reasons
     *
     * @example
     * ```php
     * $score = BotDetector::score();
     * if ($score >= 0.7) {
     *     // High probability bot
     *     blockAccess();
     * } elseif ($score >= 0.4) {
     *     // Suspicious
     *     requireCaptcha();
     * }
     * ```
     */
    public static function score(): float
    {
        $score = 0;
        $reasons = [];

        // Declarative scoring rules with category labels
        $checks = [
            'isSuspiciousUserAgent'       => ['useragent', self::isSuspiciousUserAgent(),        0.4],
            'isSuspiciousAcceptHeaders'   => ['headers',   self::isSuspiciousAcceptHeaders(),    0.2],
            'isPostWithoutReferer'        => ['request',   self::isPostWithoutReferer(),         0.1],
            'isHittingSensitivePath'      => ['request',   self::isHittingSensitivePath(),       0.1],
            'isRateLimited'               => ['request',   self::isRateLimited(),                0.2],
            'hasSuspiciousHeaders'        => ['headers',   self::hasSuspiciousHeaders(),         0.2],
            'hasNoCookies'                => ['headers',   self::hasNoCookies(),                 0.3],
            'isUsingRareMethod'           => ['request',   self::isUsingRareMethod(),            0.3],
            'isInvalidOrigin'             => ['headers',   self::isInvalidOrigin(),              0.1],
            'isHoneypot'                  => ['payload',   self::isHoneypot(),                   0.3],
            'isSuspiciousPayload'         => ['payload',   self::isSuspiciousPayload(),          0.4],
        ];

        foreach ($checks as $label => [$category, $condition, $weight]) {
            if ($condition) {
                $score += $weight;
                $reasons[$category][$label] = $weight;
            }
        }

        // Fingerprint scoring
        $fpidWeight = 0;
        $fpidState = self::fpidState();
        switch ($fpidState) {
            case 'missing':
            case 'malformed':
                $fpidWeight = 0.3;
                break;
            case 'unsigned':
                $fpidWeight = 0.1;
                break;
            case 'forged':
                $fpidWeight = 0.5;
                break;
        }

        $score += $fpidWeight;
        if ($fpidWeight > 0) {
            $reasons['fingerprint']["fpidState:$fpidState"] = $fpidWeight;
        }

        $final = min($score, 1.0);

        EventManager::fire('BotDetector.score', [
            'score' => $final,
            'reasons' => $reasons,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);

        return $final;
    }
}