<?php
/**
 * File: /vendor/vernsix/primordyx/src/HttpStatus.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/HttpStatus.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Http;

/**
 * Class HttpStatus
 *
 * A utility class providing constants for all standard HTTP status codes,
 * along with helper methods for determining the category of a given status code
 * and retrieving the corresponding human-readable message.
 *
 * @since       1.0.0
 *
 */
class HttpStatus
{
    private static ?array $textMapCache = null;

    // 1xx Informational
    public const CONTINUE = 100;
    public const SWITCHING_PROTOCOLS = 101;

// 2xx Success
    public const OK = 200;
    public const CREATED = 201;
    public const ACCEPTED = 202;
    public const NON_AUTHORITATIVE_INFORMATION = 203;
    public const NO_CONTENT = 204;
    public const RESET_CONTENT = 205;
    public const PARTIAL_CONTENT = 206;

// 3xx Redirection
    public const MULTIPLE_CHOICES = 300;
    public const MOVED_PERMANENTLY = 301;
    public const FOUND = 302; // previously MOVED_TEMPORARILY
    public const SEE_OTHER = 303;
    public const NOT_MODIFIED = 304;
    public const USE_PROXY = 305;
    public const TEMPORARY_REDIRECT = 307;
    public const PERMANENT_REDIRECT = 308;

// 4xx Client Error
    public const BAD_REQUEST = 400;
    public const UNAUTHORIZED = 401;
    public const PAYMENT_REQUIRED = 402;
    public const FORBIDDEN = 403;
    public const NOT_FOUND = 404;
    public const METHOD_NOT_ALLOWED = 405;
    public const NOT_ACCEPTABLE = 406;
    public const PROXY_AUTHENTICATION_REQUIRED = 407;
    public const REQUEST_TIMEOUT = 408;
    public const CONFLICT = 409;
    public const GONE = 410;
    public const LENGTH_REQUIRED = 411;
    public const PRECONDITION_FAILED = 412;
    public const PAYLOAD_TOO_LARGE = 413;
    public const URI_TOO_LONG = 414;
    public const UNSUPPORTED_MEDIA_TYPE = 415;
    public const RANGE_NOT_SATISFIABLE = 416;
    public const EXPECTATION_FAILED = 417;
    public const IM_A_TEAPOT = 418; // Yes, really
    public const TOO_MANY_REQUESTS = 429;

// 5xx Server Error
    public const INTERNAL_SERVER_ERROR = 500;
    public const NOT_IMPLEMENTED = 501;
    public const BAD_GATEWAY = 502;
    public const SERVICE_UNAVAILABLE = 503;
    public const GATEWAY_TIMEOUT = 504;
    public const HTTP_VERSION_NOT_SUPPORTED = 505;

    /**
     * Returns the standard reason phrase for a given HTTP status code.
     *
     * @param int $code The HTTP status code (e.g. 200, 404, 500).
     * @return string The corresponding reason phrase or 'Unknown Status' if unrecognized.
     */
    public static function text(int $code): string
    {
        return self::textMap()[$code] ?? 'Unknown Status';
    }

    public static function textMap(): array
    {
        return self::$textMapCache ?? self::$textMapCache = [
            // 1xx Informational
            self::CONTINUE => 'Continue',
            self::SWITCHING_PROTOCOLS => 'Switching Protocols',

            // 2xx Success
            self::OK => 'OK',
            self::CREATED => 'Created',
            self::ACCEPTED => 'Accepted',
            self::NON_AUTHORITATIVE_INFORMATION => 'Non-Authoritative Information',
            self::NO_CONTENT => 'No Content',
            self::RESET_CONTENT => 'Reset Content',
            self::PARTIAL_CONTENT => 'Partial Content',

            // 3xx Redirection
            self::MULTIPLE_CHOICES => 'Multiple Choices',
            self::MOVED_PERMANENTLY => 'Moved Permanently',
            self::FOUND => 'Found',
            self::SEE_OTHER => 'See Other',
            self::NOT_MODIFIED => 'Not Modified',
            self::USE_PROXY => 'Use Proxy',
            self::TEMPORARY_REDIRECT => 'Temporary Redirect',
            self::PERMANENT_REDIRECT => 'Permanent Redirect',

            // 4xx Client Error
            self::BAD_REQUEST => 'Bad Request',
            self::UNAUTHORIZED => 'Unauthorized',
            self::PAYMENT_REQUIRED => 'Payment Required',
            self::FORBIDDEN => 'Forbidden',
            self::NOT_FOUND => 'Not Found',
            self::METHOD_NOT_ALLOWED => 'Method Not Allowed',
            self::NOT_ACCEPTABLE => 'Not Acceptable',
            self::PROXY_AUTHENTICATION_REQUIRED => 'Proxy Authentication Required',
            self::REQUEST_TIMEOUT => 'Request Timeout',
            self::CONFLICT => 'Conflict',
            self::GONE => 'Gone',
            self::LENGTH_REQUIRED => 'Length Required',
            self::PRECONDITION_FAILED => 'Precondition Failed',
            self::PAYLOAD_TOO_LARGE => 'Payload Too Large',
            self::URI_TOO_LONG => 'URI Too Long',
            self::UNSUPPORTED_MEDIA_TYPE => 'Unsupported Media Type',
            self::RANGE_NOT_SATISFIABLE => 'Range Not Satisfiable',
            self::EXPECTATION_FAILED => 'Expectation Failed',
            self::IM_A_TEAPOT => "I'm a teapot",
            self::TOO_MANY_REQUESTS => 'Too Many Requests',

            // 5xx Server Error
            self::INTERNAL_SERVER_ERROR => 'Internal Server Error',
            self::NOT_IMPLEMENTED => 'Not Implemented',
            self::BAD_GATEWAY => 'Bad Gateway',
            self::SERVICE_UNAVAILABLE => 'Service Unavailable',
            self::GATEWAY_TIMEOUT => 'Gateway Timeout',
            self::HTTP_VERSION_NOT_SUPPORTED => 'HTTP Version Not Supported',
        ];

    }

    /**
     * Returns a longer-form description of the HTTP status code.
     *
     * Useful for logging, debugging, API documentation, or user-facing error pages.
     *
     * @param int $code The HTTP status code.
     * @return string A detailed description of what the status code means.
     */
    public static function description(int $code): string {
        return match ($code) {
            self::CONTINUE => 'The server has received the request headers, and the client should proceed to send the request body.',
            self::SWITCHING_PROTOCOLS => 'The requester has asked the server to switch protocols and the server has agreed to do so.',
            self::OK => 'The request has succeeded.',
            self::CREATED => 'The request has been fulfilled and has resulted in one or more new resources being created.',
            self::ACCEPTED => 'The request has been accepted for processing, but the processing has not been completed.',
            self::NON_AUTHORITATIVE_INFORMATION => 'The request was successful but the enclosed payload has been modified from that of the origin server.',
            self::NO_CONTENT => 'The server has successfully fulfilled the request and there is no content to send in the response.',
            self::RESET_CONTENT => 'The server has fulfilled the request and the user agent should reset the document view.',
            self::PARTIAL_CONTENT => 'The server is delivering only part of the resource due to a range header sent by the client.',
            self::MULTIPLE_CHOICES => 'The request has more than one possible response. The user or user agent should choose one of them.',
            self::MOVED_PERMANENTLY => 'The requested resource has been permanently moved to a new URI.',
            self::FOUND => 'The requested resource has been temporarily moved to a different URI.',
            self::SEE_OTHER => 'The response to the request can be found under another URI using the GET method.',
            self::NOT_MODIFIED => 'The resource has not been modified since the version specified by the request headers.',
            self::USE_PROXY => 'The requested resource must be accessed through the proxy given by the Location field.',
            self::TEMPORARY_REDIRECT => 'The request should be repeated with another URI; however, future requests should still use the original URI.',
            self::PERMANENT_REDIRECT => 'The request and all future requests should be repeated using another URI.',
            self::BAD_REQUEST => 'The server could not understand the request due to invalid syntax.',
            self::UNAUTHORIZED => 'The client must authenticate itself to get the requested response.',
            self::PAYMENT_REQUIRED => 'Reserved for future use. Intended for digital payment systems.',
            self::FORBIDDEN => 'The client does not have access rights to the content.',
            self::NOT_FOUND => 'The server can not find the requested resource.',
            self::METHOD_NOT_ALLOWED => 'The request method is known by the server but is not supported by the target resource.',
            self::NOT_ACCEPTABLE => 'The server cannot produce a response matching the list of acceptable values defined in the request\'s headers.',
            self::PROXY_AUTHENTICATION_REQUIRED => 'The client must first authenticate itself with the proxy.',
            self::REQUEST_TIMEOUT => 'The server would like to shut down this unused connection.',
            self::CONFLICT => 'The request conflicts with the current state of the server.',
            self::GONE => 'The requested resource is no longer available and will not be available again.',
            self::LENGTH_REQUIRED => 'The request did not specify the length of its content, which is required by the requested resource.',
            self::PRECONDITION_FAILED => 'The server does not meet one of the preconditions that the requester put on the request.',
            self::PAYLOAD_TOO_LARGE => 'The request is larger than the server is willing or able to process.',
            self::URI_TOO_LONG => 'The URI requested by the client is longer than the server is willing to interpret.',
            self::UNSUPPORTED_MEDIA_TYPE => 'The media format of the requested data is not supported by the server.',
            self::RANGE_NOT_SATISFIABLE => 'The range specified by the Range header field in the request can\'t be fulfilled.',
            self::EXPECTATION_FAILED => 'The server cannot meet the requirements of the Expect request-header field.',
            self::IM_A_TEAPOT => 'This code was defined in 1998 as an April Fools\' joke and is not expected to be implemented.',
            self::TOO_MANY_REQUESTS => 'The user has sent too many requests in a given amount of time.',
            self::INTERNAL_SERVER_ERROR => 'The server has encountered a situation it doesn\'t know how to handle.',
            self::NOT_IMPLEMENTED => 'The request method is not supported by the server and cannot be handled.',
            self::BAD_GATEWAY => 'The server received an invalid response from the upstream server.',
            self::SERVICE_UNAVAILABLE => 'The server is not ready to handle the request. Common causes include server maintenance or overload.',
            self::GATEWAY_TIMEOUT => 'The server is acting as a gateway and cannot get a response in time.',
            self::HTTP_VERSION_NOT_SUPPORTED => 'The HTTP version used in the request is not supported by the server.',
            default => 'No description available.',
        };
    }


    /**
     * Determines whether the given HTTP status code is informational (1xx).
     *
     * @param int $code The HTTP status code.
     * @return bool True if informational, false otherwise.
     */
    public static function isInformational(int $code): bool {
        return $code >= 100 && $code < 200;
    }

    /**
     * Determines whether the given HTTP status code indicates success (2xx).
     *
     * @param int $code The HTTP status code.
     * @return bool True if successful, false otherwise.
     */
    public static function isSuccess(int $code): bool {
        return $code >= 200 && $code < 300;
    }

    /**
     * Determines whether the given HTTP status code is a redirection (3xx).
     *
     * @param int $code The HTTP status code.
     * @return bool True if redirect, false otherwise.
     */
    public static function isRedirect(int $code): bool {
        return $code >= 300 && $code < 400;
    }

    /**
     * Determines whether the given HTTP status code indicates a client error (4xx).
     *
     * @param int $code The HTTP status code.
     * @return bool True if client error, false otherwise.
     */
    public static function isClientError(int $code): bool {
        return $code >= 400 && $code < 500;
    }

    /**
     * Determines whether the given HTTP status code indicates a server error (5xx).
     *
     * @param int $code The HTTP status code.
     * @return bool True if server error, false otherwise.
     */
    public static function isServerError(int $code): bool {
        return $code >= 500 && $code < 600;
    }

    /**
     * Determines whether the given HTTP status code is any kind of error (4xx or 5xx).
     *
     * @param int $code The HTTP status code.
     * @return bool True if client or server error, false otherwise.
     */
    public static function isError(int $code): bool {
        return $code >= 400;
    }

    /**
     * Returns an array of all defined HTTP status codes and their reason phrases.
     *
     * @return array<int, string>
     */
    public static function all(): array {
        return array_filter(self::textMap());
    }

    /**
     * Returns the category of the HTTP status code as a string.
     *
     * @param int $code
     * @return string One of: 'informational', 'success', 'redirect', 'client_error', 'server_error', or 'unknown'
     */
    public static function category(int $code): string {
        return match (true) {
            self::isInformational($code) => 'informational',
            self::isSuccess($code)       => 'success',
            self::isRedirect($code)      => 'redirect',
            self::isClientError($code)   => 'client_error',
            self::isServerError($code)   => 'server_error',
            default                      => 'unknown',
        };
    }

    /**
     * Determines if the status code is one of the defined HTTP status codes.
     *
     * @param int $code
     * @return bool
     */
    public static function isValid(int $code): bool {
        return array_key_exists($code, self::textMap());
    }

    /**
     * Returns all HTTP status codes for a given category.
     *
     * @param string $category One of: 'informational', 'success', 'redirect', 'client_error', 'server_error'
     * @return array<int, string> Map of code => reason
     */
    public static function codesInCategory(string $category): array {
        return array_filter(self::textMap(), fn($label, $code) => self::category($code) === $category, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Returns a random valid HTTP status code.  Could be useful in testing?  Unlikely, but hey...
     *
     * @return int
     */
    public static function random(): int {
        $keys = array_keys(self::textMap());
        return $keys[array_rand($keys)];
    }

    /**
     * Determines if the given HTTP status code is considered retryable.
     * Typically 429, 502, 503, 504.
     *
     * @param int $code
     * @return bool
     */
    public static function isRetryable(int $code): bool {
        return in_array($code, [self::TOO_MANY_REQUESTS, self::BAD_GATEWAY, self::SERVICE_UNAVAILABLE, self::GATEWAY_TIMEOUT], true);
    }

    /**
     * Returns a full HTTP status line (e.g. "HTTP/1.1 404 Not Found").
     *
     * @param int $code
     * @param string $protocol HTTP version (default: HTTP/1.1)
     * @return string
     */
    public static function statusLine(int $code, string $protocol = 'HTTP/1.1'): string {
        return sprintf('%s %d %s', $protocol, $code, self::text($code));
    }

    /**
     * Sends the HTTP status header using the given code.
     *
     * @param int $code
     * @param string $protocol HTTP version (default: HTTP/1.1)
     * @return void
     */
    public static function sendHeader(int $code, string $protocol = 'HTTP/1.1'): void {
        if (!headers_sent() && self::isValid($code)) {
            header(self::statusLine($code, $protocol), true, $code);
        }
    }

    /**
     * Determines if the response is generally considered cacheable.
     *
     * @param int $code
     * @return bool
     */
    public static function isCacheable(int $code): bool {
        return in_array($code, [self::OK, self::NON_AUTHORITATIVE_INFORMATION, self::NO_CONTENT, self::PARTIAL_CONTENT, self::NOT_MODIFIED], true);
    }

    /**
     * Returns the HTTP status series (e.g., 2 for 2xx).
     *
     * @param int $code
     * @return int
     */
    public static function series(int $code): int {
        return (int) floor($code / 100);
    }

    /**
     * Indicates if the HTTP method producing this response should be idempotent.
     *
     * @param int $code
     * @return bool
     */
    public static function isIdempotent(int $code): bool {
        return in_array($code, [
            self::OK, self::NO_CONTENT, self::NOT_MODIFIED,
            self::BAD_REQUEST, self::UNAUTHORIZED, self::FORBIDDEN,
            self::NOT_FOUND, self::CONFLICT, self::GONE,
            self::INTERNAL_SERVER_ERROR, self::SERVICE_UNAVAILABLE
        ], true);
    }

    /**
     * Returns an associative array of all HTTP status codes and their reason phrases.
     *
     * Useful for displaying or serializing the complete list of supported HTTP status codes.
     *
     * @return array<int, string> An array where the keys are HTTP status codes and the values are their reason phrases.
     */
    public static function toArray(): array {
        return self::textMap();
    }

    /**
     * Returns a JSON-encoded string of all HTTP status codes and their reason phrases.
     *
     * This is helpful for APIs, documentation endpoints, or front-end tooling that needs access to the status code map.
     *
     * @param bool $pretty If true, the JSON will be pretty-printed for readability.
     * @return string A JSON string representing all known HTTP status codes and their descriptions.
     */
    public static function toJson(bool $pretty = false): string {
        return json_encode(self::toArray(), $pretty ? JSON_PRETTY_PRINT : 0);
    }

    /**
     * Checks whether the status code is deprecated or rarely used.
     *
     * @param int $code
     * @return bool
     */
    public static function isDeprecated(int $code): bool {
        return in_array($code, [self::USE_PROXY, self::IM_A_TEAPOT, self::PAYMENT_REQUIRED], true);
    }

    /**
     * Returns all known HTTP status codes as an array. useful for enum-style dropdowns or schema gen
     *
     * @return int[]
     */
    public static function allCodes(): array {
        return array_keys(self::textMap());
    }

    /**
     * Determines if the response should not include a body.
     *
     * @param int $code
     * @return bool
     */
    public static function isEmptyResponse(int $code): bool {
        return in_array($code, [self::NO_CONTENT, self::NOT_MODIFIED], true);
    }

    /**
     * Determines if the status code relates to authentication or authorization.
     *
     * @param int $code
     * @return bool
     */
    public static function isAuthRelated(int $code): bool {
        return in_array($code, [self::UNAUTHORIZED, self::FORBIDDEN, self::PROXY_AUTHENTICATION_REQUIRED], true);
    }

    /**
     * Recommends a retry delay (in seconds) for retryable status codes.
     *
     * @param int $code
     * @return int|null Suggested delay in seconds, or null if not retryable.
     */
    public static function recommendRetryDelay(int $code): ?int {
        return match ($code) {
            self::TOO_MANY_REQUESTS => 30,
            self::SERVICE_UNAVAILABLE => 60,
            self::GATEWAY_TIMEOUT, self::BAD_GATEWAY => 15,
            default => null,
        };
    }

    /**
     * Returns a structured metadata array for the given HTTP status code.
     *
     * @param int $code
     * @return array<string, mixed>
     */
    public static function meta(int $code): array {
        return [
            'code' => $code,
            'text' => self::text($code),
            'description' => self::description($code),
            'category' => self::category($code),
            'is_error' => self::isError($code),
            'is_retryable' => self::isRetryable($code),
            'is_cacheable' => self::isCacheable($code),
            'is_empty_response' => self::isEmptyResponse($code),
        ];
    }

    /**
     * Checks if the given code is officially defined in the HTTP standard.
     *
     * @param int $code
     * @return bool
     */
    public static function isStandard(int $code): bool {
        return in_array($code, self::allCodes(), true);
    }

    /**
     * Returns a link to documentation for the given HTTP status code.
     *
     * @param int $code
     * @return string
     */
    public static function docsUrl(int $code): string {
        return "https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/$code";
    }

    /**
     * Attempts to find the HTTP status code for a given reason phrase.
     *
     * @param string $text
     * @return int|null
     */
    public static function fromText(string $text): ?int {
        return array_search(ucwords(strtolower($text)), self::textMap(), true) ?: null;
    }

    /**
     * Checks whether a status code is non-standard/custom.
     *
     * @param int $code
     * @return bool
     */
    public static function isCustom(int $code): bool {
        return $code < 100 || $code >= 600;
    }


    /**
     * Checks if the status code implies a safe, cacheable, idempotent result.  Is it safe to retry automatically?
     * Not the same as isRetryable() — this is from an HTTP protocol/client perspective.
     *
     * @param int $code
     * @return bool
     */
    public static function isSafe(int $code): bool {
        return in_array($code, [self::OK, self::NO_CONTENT, self::NOT_MODIFIED], true);
    }

    /**
     * Determines if the status code represents a final response.
     *
     * @param int $code
     * @return bool
     */
    public static function isFinal(int $code): bool {
        return $code < 100 || $code >= 200;
    }

    /**
     * Returns all status codes grouped by category.
     *
     * @return array<string, array<int, string>>
     */
    public static function grouped(): array {
        $grouped = [];
        foreach (self::toArray() as $code => $text) {
            $group = self::category($code);
            $grouped[$group][$code] = $text;
        }
        return $grouped;
    }

    /**
     * Generates an HTML <select> element of all HTTP codes grouped by category for dashboards or admin panels, etc.
     *
     * @param string $name
     * @return string
     */
    public static function asHtmlSelect(string $name = 'http_status'): string {
        $out = "<select name=\"$name\" id=\"$name\">\n";
        foreach (self::grouped() as $group => $items) {
            $out .= "<optgroup label=\"" . ucfirst(str_replace('_', ' ', $group)) . "\">\n";
            foreach ($items as $code => $text) {
                $out .= "<option value=\"$code\">$code – $text</option>\n";
            }
            $out .= "</optgroup>\n";
        }
        return $out . "</select>\n";
    }

    /**
     * Suggests a PSR-3 log severity level for the given HTTP code.
     *
     * @param int $code
     * @return string
     */
    public static function loggableSeverity(int $code): string {
        return match (true) {
            self::isInformational($code) => 'debug',
            self::isSuccess($code) => 'info',
            self::isClientError($code) => 'warning',
            self::isServerError($code) => 'error',
            default => 'notice',
        };
    }

    /**
     * Returns a visual emoji cue for a given status code.
     *
     * @param int $code
     * @return string|null
     */
    public static function httpEmoji(int $code): ?string {
        return match (true) {
            $code >= 100 && $code < 200 => "\u{1F4E1}",  // satellite
            $code >= 200 && $code < 300 => "\u{2705}",   // checkmark
            $code >= 300 && $code < 400 => "\u{1F501}",  // repeat
            $code >= 400 && $code < 500 => "\u{26A0}\u{FE0F}", // warning sign with variation selector
            $code >= 500 && $code < 600 => "\u{1F480}",  // skull
            default => null,
        };
    }

    /**
     * Returns a CSS class (e.g., for badge coloring) based on HTTP code category.
     *
     * @param int $code
     * @return string
     */
    public static function statusClass(int $code): string {
        return match (true) {
            self::isInformational($code) => 'badge-info',
            self::isSuccess($code) => 'badge-success',
            self::isRedirect($code) => 'badge-secondary',
            self::isClientError($code) => 'badge-warning',
            self::isServerError($code) => 'badge-danger',
            default => 'badge-default',
        };
    }

    /**
     * Checks if the code suggests an internet or upstream connection failure.
     *
     * @param int $code
     * @return bool
     */
    public static function isInternetError(int $code): bool {
        return in_array($code, [self::BAD_GATEWAY, self::GATEWAY_TIMEOUT, self::SERVICE_UNAVAILABLE], true);
    }

    /**
     * Compares a flexible input (string, int, slug) against an HTTP status code.
     *
     * @param int|string $input
     * @param int $code
     * @return bool
     */
    public static function matches(int|string $input, int $code): bool {
        if (is_int($input)) return $input === $code;
        $input = strtoupper(trim(str_replace([' ', '-', '_'], '_', $input)));
        return defined("self::$input") && constant("self::$input") === $code;
    }

    /**
     * Suggests a developer or operations action for the given status code.
     *
     * @param int $code
     * @return string|null
     */
    public static function recommendedAction(int $code): ?string {
        return match ($code) {
            self::BAD_REQUEST => 'Validate request structure and parameters.',
            self::UNAUTHORIZED, self::FORBIDDEN => 'Check auth tokens or permissions.',
            self::NOT_FOUND => 'Verify URL or route exists.',
            self::TOO_MANY_REQUESTS => 'Implement exponential backoff or rate limit handling.',
            self::INTERNAL_SERVER_ERROR => 'Check server logs for unhandled exceptions.',
            self::BAD_GATEWAY, self::GATEWAY_TIMEOUT => 'Check upstream service health.',
            default => null,
        };
    }

    /**
     * Returns a spoken-friendly version of the HTTP status.
     *
     * @param int $code
     * @return string
     */
    public static function toSpeech(int $code): string {
        return match ($code) {
            self::OK => "Everything’s fine. The request was successful.",
            self::CREATED => "Great — your request worked and something new was created.",
            self::NO_CONTENT => "The request worked, but there’s nothing to show in response.",

            self::MOVED_PERMANENTLY => "This page has moved to a new address.",
            self::FOUND => "You’re being temporarily redirected.",
            self::NOT_MODIFIED => "The content hasn’t changed — you can use your cached version.",

            self::BAD_REQUEST => "Something about the request was invalid. Check what you’re sending.",
            self::UNAUTHORIZED => "You're not logged in or your credentials are missing.",
            self::FORBIDDEN => "You're logged in, but not allowed to access this.",
            self::NOT_FOUND => "Sorry, that page doesn’t exist.",
            self::METHOD_NOT_ALLOWED => "That action isn’t allowed here.",
            self::REQUEST_TIMEOUT => "The server waited but didn’t hear back in time.",
            self::CONFLICT => "The request can't be completed because of a conflict with the current state.",
            self::GONE => "This resource used to exist, but it’s been removed permanently.",
            self::PAYLOAD_TOO_LARGE => "The file or data you’re sending is too big.",
            self::TOO_MANY_REQUESTS => "Whoa there! You’ve sent too many requests too quickly.",

            self::INTERNAL_SERVER_ERROR => "Oops. The server ran into an issue.",
            self::NOT_IMPLEMENTED => "The server doesn’t support this request method.",
            self::BAD_GATEWAY => "A server between you and the origin had trouble.",
            self::SERVICE_UNAVAILABLE => "The server is currently down for maintenance or overloaded.",
            self::GATEWAY_TIMEOUT => "The server didn’t get a timely response from an upstream server.",

            self::IM_A_TEAPOT => "Yes, I’m a teapot. I cannot brew coffee. This is a joke. Sort of.",

            default => self::description($code),
        };
    }





}
