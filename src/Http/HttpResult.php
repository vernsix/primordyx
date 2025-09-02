<?php
/**
 * File: /vendor/vernsix/primordyx/src/HttpResult.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/HttpResult.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Http;

use Primordyx\Time\Timer;

/**
 * Comprehensive HTTP Request/Response Result Container with Performance Monitoring
 *
 * Encapsulates complete HTTP request lifecycle data including request parameters, response
 * content, cURL diagnostics, performance metrics, and debugging information. Provides
 * structured access to all HTTP transaction details with built-in Timer integration
 * for performance monitoring and analysis.
 *
 * ## Key Features
 * - **Complete Transaction Data**: Request and response details in single object
 * - **Performance Monitoring**: Automatic Timer integration with detailed metrics
 * - **cURL Diagnostics**: Full cURL error and info metadata capture
 * - **Response Analysis**: Convenience methods for status codes, content types, success detection
 * - **Debug Support**: Verbose logging, header inspection, and request replay data
 * - **Serialization**: Export capabilities for logging, debugging, and audit trails
 * - **Flexible Access**: Both getter/setter and read-only access patterns supported
 *
 * ## Data Organization
 * - **Request Data**: Method, URL, payload, and configuration used
 * - **Response Data**: Body, headers, status code, and content type
 * - **Performance Data**: Timing metrics via Timer class integration
 * - **Diagnostic Data**: cURL errors, connection info, and verbose logs
 * - **Metadata**: Configuration details and request/response serialization
 *
 * ## Timer Integration
 * HttpResult is where actual Timer class integration occurs:
 * - Constructor automatically starts named timer using HttpClientConfig name
 * - stopTimer() method finalizes timing and captures detailed metrics
 * - Timer name format: "http_client_{config_name}"
 * - Performance data accessible via timerDetails() method
 *
 * ## Usage Patterns
 * ```php
 * // HttpResult created automatically by HttpClient
 * $result = HttpClient::get('https://api.example.com/users');
 *
 * // Access response data
 * $statusCode = $result->httpCode();
 * $responseBody = $result->response();
 * $headers = $result->responseHeaders();
 *
 * // Analyze performance
 * $timing = $result->timerDetails();
 * echo "Request took: " . $timing['elapsed'] . " seconds";
 *
 * // Check for errors
 * if ($result->curlError()) {
 *     error_log("cURL Error: " . $result->curlError());
 * }
 *
 * // Export for logging
 * $logData = $result->asArray();
 * ```
 *
 * ## Response Analysis
 * Built-in convenience methods for common response analysis:
 * - Success detection via HTTP status code ranges
 * - Content-Type extraction and analysis
 * - HTTP status code access and interpretation
 * - Error condition detection and reporting
 *
 * @package Primordyx
 * @since 1.0.0
 */
class HttpResult
{

    /**
     * HTTP client configuration used for this request
     *
     * @var HttpClientConfig
     * @since 1.0.0
     */
    private HttpClientConfig $config;

    /**
     * Timer identifier for performance monitoring
     *
     * @var string
     * @since 1.0.0
     */
    private string $timerName;

    /**
     * Detailed performance metrics from Timer class
     *
     * @var array
     * @since 1.0.0
     */
    private array $timerDetails = [];

    /**
     * HTTP request method used (GET, POST, PUT, DELETE, etc.)
     *
     * @var string
     * @since 1.0.0
     */
    private string $method = '';

    /**
     * Target URL for the HTTP request
     *
     * @var string
     * @since 1.0.0
     */
    private string $url = '';

    /**
     * Request payload/body data sent with request
     *
     * @var mixed
     * @since 1.0.0
     */
    private mixed $payload = null;

    /**
     * Raw HTTP response body content
     *
     * @var mixed
     * @since 1.0.0
     */
    private mixed $response = null;

    /**
     * cURL error message if request failed
     *
     * @var mixed
     * @since 1.0.0
     */
    private mixed $curl_error = null;

    /**
     * cURL error number/code for request failures
     *
     * @var mixed
     * @since 1.0.0
     */
    private mixed $curl_errno = null;

    /**
     * Complete cURL information array from curl_getinfo()
     *
     * @var mixed
     * @since 1.0.0
     */
    private mixed $curl_info = null;

    /**
     * Verbose cURL debugging log entries
     *
     * @var array
     * @since 1.0.0
     */
    private array $verboseLog = [];

    /**
     * HTTP response headers as associative array
     *
     * @var array
     * @since 1.0.0
     */
    private array $responseHeaders = [];

    /**
     * Initialize HTTP result container and start performance timer
     *
     * Creates new result container for HTTP request/response data and automatically
     * starts named Timer using HttpClientConfig name for performance monitoring.
     * Timer name format: "http_client_{config_name}".
     *
     * ## Timer Integration
     * - Extracts configuration name for timer identification
     * - Starts Timer automatically using generated timer name
     * - Timer must be stopped manually via stopTimer() method
     * - Performance metrics captured when timer is stopped
     *
     * @param HttpClientConfig $config Configuration object used for the request
     * @since 1.0.0
     *
     * @example
     * ```php
     * $config = new HttpClientConfig('api_call');
     * $result = new HttpResult($config);
     * // Timer "http_client_api_call" is now running
     * ```
     */
    public function __construct(HttpClientConfig $config)
    {
        $this->config = $config;
        $this->timerName = 'http_client_' . $this->config->name();
        Timer::start($this->timerName);
    }

    /**
     * Export complete result data as associative array for logging and debugging
     *
     * Converts all HTTP transaction data into structured array format suitable for
     * JSON serialization, logging, debugging, and audit trail creation. Automatically
     * handles JSON payload and response detection for proper data formatting.
     *
     * ## Data Processing
     * - Payload decoded as JSON if request Content-Type indicates JSON
     * - Response decoded as JSON if response Content-Type indicates JSON
     * - All other data included as-is for complete transaction picture
     * - Configuration data included via HttpClientConfig::asArray()
     *
     * @return array<string, mixed> Complete HTTP transaction data array
     * @since 1.0.0
     *
     * @example
     * ```php
     * $result = HttpClient::postJson('https://api.example.com/users', $userData);
     * $exportData = $result->asArray();
     *
     * // Log complete transaction
     * error_log("API Request: " . json_encode($exportData, JSON_PRETTY_PRINT));
     *
     * // Access specific data
     * $statusCode = $exportData['curl_info']['http_code'];
     * $responseData = $exportData['response']; // Already decoded from JSON
     * ```
     */
    public function asArray(): array
    {
        $a = [
            'config' => $this->config->asArray(),
            'timerName' => $this->timerName,
            'timerDetails' => $this->timerDetails,
            'method' => $this->method,
            'url' => $this->url,
            'curl_error' => $this->curl_error,
            'curl_errno' => $this->curl_errno,
            'curl_info' => $this->curl_info,
            'verboseLog' => $this->verboseLog,
            'responseHeaders' => $this->responseHeaders
        ];

        if (in_array('Content-Type: application/json', $this->config->getAllHeaders(),true)) {
            $a['payload'] = json_decode($this->payload, true);
        } else {
            $a['payload'] = $this->payload;
        }

        if (isset($this->responseHeaders['content-type']) && ($this->responseHeaders['content-type'] === 'application/json')) {
            $a['response'] = json_decode($this->response, true);
        } else {
            $a['response'] = $this->response;
        }

        return $a;
    }

    /**
     * Alias for asArray() method for alternative naming convention
     *
     * Provides alternative method name for array export functionality.
     * Identical behavior to asArray() method.
     *
     * @return array<string, mixed> Complete HTTP transaction data array
     * @since 1.0.0
     */
    public function toArray(): array
    {
        return $this->asArray();
    }

    /**
     * Get the HttpClientConfig object used for this request
     *
     * Returns the original configuration object used to make the HTTP request.
     * Useful for inspecting request headers, authentication, timeouts, and
     * other configuration details after request completion.
     *
     * @return HttpClientConfig Original configuration object from request
     * @since 1.0.0
     *
     * @example
     * ```php
     * $result = HttpClient::get('https://api.example.com/users', $config);
     *
     * $requestConfig = $result->config();
     * $headers = $requestConfig->getAllHeaders();
     * $timeout = $requestConfig->timeout();
     * ```
     */
    public function config(): HttpClientConfig
    {
        return $this->config;
    }

    /**
     * Get the Timer identifier used for performance monitoring
     *
     * Returns the name used for Timer class integration. Timer name is generated
     * from HttpClientConfig name with "http_client_" prefix for identification.
     *
     * @return string Timer name used for performance monitoring
     * @since 1.0.0
     *
     * @example
     * ```php
     * $result = HttpClient::get('https://api.example.com', $config);
     * $timerName = $result->getTimerName(); // "http_client_config_name"
     * ```
     */
    public function getTimerName(): string
    {
        return $this->timerName;
    }


    /**
     * Stop performance timer and capture detailed metrics
     *
     * Finalizes Timer tracking and captures comprehensive performance metrics
     * including elapsed time, memory usage, and timing breakdown. Should be
     * called once when HTTP request processing is complete.
     *
     * ## Performance Data Captured
     * - Total elapsed time for request/response cycle
     * - Memory usage during request processing
     * - Timing breakdown and performance analytics
     * - Timer metadata for performance analysis
     *
     * @return void
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Typically called automatically by HttpClient
     * $result->stopTimer();
     *
     * // Access captured metrics
     * $metrics = $result->timerDetails();
     * echo "Request completed in: " . $metrics['elapsed'] . " seconds";
     * ```
     */
    public function stopTimer(): void
    {
        Timer::stop($this->timerName);
        $this->timerDetails = Timer::getTimer($this->timerName);
    }

    /**
     * Get or set the target URL for HTTP request
     *
     * Getter/setter method for request URL. When called without parameters,
     * returns current URL. When called with URL parameter, updates stored
     * URL and returns previous value.
     *
     * @param string|null $url Optional new URL to set
     * @return string|null Previous URL value or current URL if getting
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Get current URL
     * $currentUrl = $result->url();
     *
     * // Set new URL (typically done by HttpClient)
     * $oldUrl = $result->url('https://api.example.com/v2/users');
     * ```
     */
    public function url(?string $url = null): ?string
    {
        $old = $this->url;
        if ($url !== null) {
            $this->url = $url;
        }
        return $old;
    }

    /**
     * Get or set the HTTP request method
     *
     * Getter/setter for HTTP method used in request (GET, POST, PUT, DELETE, etc.).
     * When called without parameters, returns current method. When called with
     * method parameter, updates stored method and returns previous value.
     *
     * @param string|null $method Optional new HTTP method to set
     * @return string|null Previous method value or current method if getting
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Get current method
     * $requestMethod = $result->method(); // "POST"
     *
     * // Set method (typically done by HttpClient)
     * $oldMethod = $result->method('PUT');
     * ```
     */
    public function method(?string $method = null): ?string
    {
        $old = $this->method;
        if ($method !== null) {
            $this->method = $method;
        }
        return $old;
    }

    /**
     * Get or set the request payload/body data
     *
     * Getter/setter for request body content sent with HTTP request. Supports
     * any data type including strings, arrays, and file resources. When called
     * without parameters, returns current payload.
     *
     * @param mixed|null $payload Optional new payload data to store
     * @return mixed Previous payload value or current payload if getting
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Get request payload
     * $sentData = $result->payload();
     *
     * // Examine payload type and content
     * if (is_array($sentData)) {
     *     echo "Form data sent: " . print_r($sentData, true);
     * } else {
     *     echo "Raw data sent: " . $sentData;
     * }
     * ```
     */
    public function payload(mixed $payload = null): mixed
    {
        $old = $this->payload;
        if ($payload !== null) {
            $this->payload = $payload;
        }
        return $old;
    }

    /**
     * Get or set cURL verbose debugging log entries
     *
     * Getter/setter for detailed cURL communication log captured when verbose
     * logging is enabled in HttpClientConfig. Provides low-level debugging
     * information about HTTP connection, SSL handshake, and protocol details.
     *
     * @param array|null $verboseLog Optional new verbose log entries to store
     * @return array Previous or current verbose log entries
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Get verbose log for debugging
     * $debugLog = $result->verboseLog();
     * foreach ($debugLog as $line) {
     *     echo "cURL: $line\n";
     * }
     *
     * // Check if verbose logging was enabled
     * if (count($debugLog) > 1 && $debugLog[0] !== 'No verbose log available') {
     *     echo "Verbose logging was active for this request";
     * }
     * ```
     */
    public function verboseLog(?array $verboseLog = null): array
    {
        $old = $this->verboseLog;
        if ($verboseLog !== null) {
            $this->verboseLog = $verboseLog;
        }
        return $old;
    }

    /**
     * Get or set HTTP response headers as associative array
     *
     * Getter/setter for response headers captured from HTTP server response.
     * Headers are stored as associative array with header names as keys.
     * Supports duplicate headers via array values for headers with multiple values.
     *
     * @param array|null $responseHeaders Optional new response headers to store
     * @return array Previous or current response headers array
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Get all response headers
     * $headers = $result->responseHeaders();
     *
     * // Check specific headers
     * $contentType = $headers['content-type'] ?? 'unknown';
     * $cacheControl = $headers['cache-control'] ?? null;
     *
     * // Handle duplicate headers (stored as arrays)
     * if (is_array($headers['set-cookie'])) {
     *     foreach ($headers['set-cookie'] as $cookie) {
     *         echo "Cookie: $cookie\n";
     *     }
     * }
     * ```
     */
    public function responseHeaders(?array $responseHeaders = null): array
    {
        $old = $this->responseHeaders;
        if ($responseHeaders !== null) {
            $this->responseHeaders = $responseHeaders;
        }
        return $old;
    }

    /**
     * Get or set the raw HTTP response body content
     *
     * Getter/setter for complete response body returned by HTTP server.
     * Content is stored as-is without parsing or modification. For JSON
     * responses, use json_decode() to parse content.
     *
     * @param mixed|null $response Optional new response body content to store
     * @return mixed Previous or current response body content
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Get response content
     * $responseBody = $result->response();
     *
     * // Parse JSON responses
     * if ($result->contentType() === 'application/json') {
     *     $data = json_decode($responseBody, true);
     * }
     *
     * // Handle different content types
     * $contentType = $result->contentType();
     * switch ($contentType) {
     *     case 'application/json':
     *         $parsed = json_decode($responseBody, true);
     *         break;
     *     case 'application/xml':
     *         $parsed = simplexml_load_string($responseBody);
     *         break;
     *     default:
     *         $parsed = $responseBody;
     * }
     * ```
     */
    public function response(mixed $response = null): mixed
    {
        $old = $this->response;
        if ($response !== null) {
            $this->response = $response;
        }
        return $old;
    }

    /**
     * Get or set cURL error message for failed requests
     *
     * Getter/setter for cURL error message returned by curl_error(). Contains
     * human-readable description of connection or request failures. Empty
     * string indicates no cURL-level errors occurred.
     *
     * @param mixed|null $curl_error Optional new cURL error message to store
     * @return mixed Previous or current cURL error message
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Check for cURL errors
     * $error = $result->curlError();
     * if (!empty($error)) {
     *     error_log("HTTP request failed: $error");
     * }
     *
     * // Common cURL error handling
     * switch (true) {
     *     case str_contains($error, 'timeout'):
     *         echo "Request timed out - server may be overloaded";
     *         break;
     *     case str_contains($error, 'SSL'):
     *         echo "SSL/TLS connection failed - check certificates";
     *         break;
     *     case str_contains($error, 'resolve'):
     *         echo "DNS resolution failed - check hostname";
     *         break;
     * }
     * ```
     */
    public function curlError(mixed $curl_error = null): mixed
    {
        $old = $this->curl_error;
        if ($curl_error !== null) {
            $this->curl_error = $curl_error;
        }
        return $old;
    }

    /**
     * Get or set cURL error number/code for failed requests
     *
     * Getter/setter for cURL error number returned by curl_errno(). Provides
     * numeric error code for programmatic error handling and classification.
     * Zero indicates no cURL-level errors occurred.
     *
     * @param mixed|null $curl_errno Optional new cURL error number to store
     * @return mixed Previous or current cURL error number
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Check for specific error conditions
     * $errorCode = $result->curlErrNo();
     *
     * switch ($errorCode) {
     *     case 0:
     *         echo "No cURL errors";
     *         break;
     *     case 6:  // CURLE_COULDNT_RESOLVE_HOST
     *         echo "Could not resolve hostname";
     *         break;
     *     case 7:  // CURLE_COULDNT_CONNECT
     *         echo "Could not connect to server";
     *         break;
     *     case 28: // CURLE_OPERATION_TIMEDOUT
     *         echo "Request timed out";
     *         break;
     *     default:
     *         echo "cURL error $errorCode: " . $result->curlError();
     * }
     * ```
     */
    public function curlErrNo(mixed $curl_errno = null): mixed
    {
        $old = $this->curl_errno;
        if ($curl_errno !== null) {
            $this->curl_errno = $curl_errno;
        }
        return $old;
    }

    /**
     * Get or set complete cURL information array from curl_getinfo()
     *
     * Getter/setter for comprehensive request metadata including timing details,
     * connection information, SSL data, HTTP status code, content lengths,
     * IP addresses, ports, and performance metrics.
     *
     * ## Available Information
     * - HTTP status code, content type, and response size
     * - Timing data: total_time, namelookup_time, connect_time, etc.
     * - Network details: primary_ip, primary_port, local_ip, local_port
     * - SSL information: ssl_verify_result and certificate data
     * - Transfer metrics: speed_upload, speed_download, size_upload, size_download
     *
     * @param mixed|null $curl_info Optional new cURL info array to store
     * @return mixed Previous or current cURL information array
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Get complete cURL information
     * $info = $result->curlInfo();
     *
     * // Extract timing information
     * $totalTime = $info['total_time'];
     * $connectTime = $info['connect_time'];
     * $transferTime = $totalTime - $connectTime;
     *
     * // Network diagnostics
     * echo "Connected to: " . $info['primary_ip'] . ":" . $info['primary_port'];
     * echo "Transfer speed: " . number_format($info['speed_download']) . " bytes/sec";
     *
     * // Performance analysis
     * if ($info['total_time'] > 5.0) {
     *     echo "Slow request detected - consider optimizing";
     * }
     * ```
     *
     *  Example keys:
     * ```php
     * array(26) {
     *    ["url"] => string(36) "https://api.example.com/v1/status"
     *    ["content_type"] => string(31) "application/json; charset=utf-8"
     *    ["http_code"] => int(200)
     *    ["header_size"] => int(289)
     *    ["request_size"] => int(158)
     *    ["filetime"] => int(-1)
     *    ["ssl_verify_result"] => int(0)
     *    ["redirect_count"] => int(0)
     *    ["total_time"] => float(0.234)
     *    ["namelookup_time"] => float(0.015)
     *    ["connect_time"] => float(0.045)
     *    ["pretransfer_time"] => float(0.051)
     *    ["size_upload"] => float(0)
     *    ["size_download"] => float(347)
     *    ["speed_download"] => float(1482)
     *    ["speed_upload"] => float(0)
     *    ["download_content_length"] => float(-1)
     *    ["upload_content_length"] => float(0)
     *    ["starttransfer_time"] => float(0.091)
     *    ["redirect_time"] => float(0)
     *    ["redirect_url"] => string(0) ""
     *    ["primary_ip"] => string(13) "93.184.216.34"
     *    ["certinfo"] => array(0) {}
     *    ["primary_port"] => int(443)
     *    ["local_ip"] => string(13) "192.168.1.45"
     *    ["local_port"] => int(59832)
     *  }
     * ```
     *
     */
    public function curlInfo(mixed $curl_info = null): mixed
    {
        /*
         * A typical $curl_info array will look like this...
         *
         *
         */
        $old = $this->curl_info;
        if ($curl_info !== null) {
            $this->curl_info = $curl_info;
        }
        return $old;
    }

    /**
     * Get or set detailed performance metrics from Timer class
     *
     * Getter/setter for comprehensive timing and performance data captured
     * by Timer class during HTTP request processing. Contains elapsed time,
     * memory usage, and detailed timing breakdown for performance analysis.
     *
     * @param mixed|null $timerDetails Optional new timer details array to store
     * @return array Previous or current timer performance data
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Get performance metrics
     * $metrics = $result->timerDetails();
     *
     * // Display timing information
     * echo "Request elapsed time: " . $metrics['elapsed'] . " seconds\n";
     * echo "Memory used: " . $metrics['memory_used'] . " bytes\n";
     *
     * // Performance monitoring
     * if ($metrics['elapsed'] > 2.0) {
     *     error_log("Slow API request detected: " . $metrics['elapsed'] . "s");
     * }
     * ```
     */
    public function timerDetails(mixed $timerDetails = null): array
    {
        $old = $this->timerDetails;
        if ($timerDetails !== null) {
            $this->timerDetails = $timerDetails;
        }
        return $old;
    }

    /**
     * Get HTTP status code from response for quick status checking
     *
     * Convenience method that extracts HTTP status code from cURL info array.
     * Returns standard HTTP status codes (200, 404, 500, etc.) for response
     * analysis and error handling.
     *
     * @return int HTTP status code (200, 404, 500, etc.) or 0 if unavailable
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Check response status
     * $status = $result->httpCode();
     *
     * switch ($status) {
     *     case 200:
     *         echo "Request successful";
     *         break;
     *     case 404:
     *         echo "Resource not found";
     *         break;
     *     case 500:
     *         echo "Server error occurred";
     *         break;
     *     default:
     *         echo "HTTP status: $status";
     * }
     *
     * // Status range checking
     * if ($status >= 400) {
     *     echo "Client or server error occurred";
     * }
     * ```
     */
    public function httpCode(): int
    {
        return $this->curlInfo()['http_code'] ?? 0;
    }

    /**
     * Determine if HTTP request was successful based on status code
     *
     * Convenience method that checks if HTTP status code indicates success.
     * Success is defined as 2xx status codes (200-299 range) per HTTP standards.
     * Does not account for application-level errors in response body.
     *
     * @return bool True if HTTP status code indicates success (2xx range), false otherwise
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Simple success checking
     * if ($result->wasSuccessful()) {
     *     $data = json_decode($result->response(), true);
     *     processSuccessfulResponse($data);
     * } else {
     *     handleHttpError($result->httpCode(), $result->response());
     * }
     *
     * // Combined with cURL error checking
     * if ($result->curlError()) {
     *     echo "Network error: " . $result->curlError();
     * } elseif (!$result->wasSuccessful()) {
     *     echo "HTTP error " . $result->httpCode() . ": " . $result->response();
     * } else {
     *     echo "Request completed successfully";
     * }
     * ```
     */
    public function wasSuccessful(): bool
    {
        $code = $this->httpCode();
        return $code >= 200 && $code < 300;
    }

    /**
     * Get Content-Type header from HTTP response for content handling
     *
     * Convenience method that extracts Content-Type header from cURL info array.
     * Useful for determining how to parse response body content (JSON, XML, HTML, etc.).
     * Returns full Content-Type header value including charset if present.
     *
     * @return string|null Content-Type header value (e.g., 'application/json; charset=utf-8') or null if unavailable
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Parse response based on content type
     * $contentType = $result->contentType();
     * $responseBody = $result->response();
     *
     * if (str_starts_with($contentType, 'application/json')) {
     *     $data = json_decode($responseBody, true);
     * } elseif (str_starts_with($contentType, 'application/xml')) {
     *     $data = simplexml_load_string($responseBody);
     * } elseif (str_starts_with($contentType, 'text/html')) {
     *     $data = parseHtmlResponse($responseBody);
     * } else {
     *     $data = $responseBody; // Handle as raw content
     * }
     *
     * // Extract charset from content type
     * if (preg_match('/charset=([^;]+)/', $contentType, $matches)) {
     *     $charset = $matches[1];
     *     echo "Response charset: $charset";
     * }
     * ```
     */
    public function contentType(): ?string
    {
        return $this->curlInfo()['content_type'] ?? null;
    }

}
