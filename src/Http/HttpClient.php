<?php
/**
 * File: /vendor/vernsix/primordyx/src/HttpClient.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/HttpClient.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Http;

use Primordyx\Events\EventManager;
use Primordyx\Utils\Callback;

/**
 * Modern HTTP Client with cURL Backend and Comprehensive Request Support
 *
 * Static HTTP client providing secure, configurable web requests with full RESTful method
 * support, automatic performance monitoring, request/response debugging, and optional
 * mocking capabilities. Built on cURL with enterprise-grade security and monitoring features.
 *
 * ## Key Features
 * - **RESTful Methods**: Complete support for GET, POST, PUT, DELETE, and custom HTTP methods
 * - **JSON Integration**: Native JSON encoding with automatic Content-Type header management
 * - **Security First**: Basic auth, Bearer tokens, custom headers with secure defaults
 * - **Performance Monitoring**: Automatic Timer integration via HttpResult for request tracking
 * - **Debug Support**: Verbose cURL logging, response inspection, and request replay tools
 * - **Test-Friendly**: Built-in mocking system for unit testing and development
 * - **Configuration Driven**: Flexible request configuration via HttpClientConfig objects
 *
 * ## Request Flow Architecture
 * 1. **Method Call**: Developer calls static method (get, post, etc.)
 * 2. **Configuration**: HttpClientConfig provides headers, auth, timeouts
 * 3. **HttpResult Creation**: Automatic timer start and request tracking
 * 4. **cURL Execution**: Secure HTTP request with comprehensive error handling
 * 5. **Response Processing**: Headers, body, and metadata captured in HttpResult
 * 6. **Performance Tracking**: Timer stopped with detailed metrics collection
 *
 * ## Security Features
 * - **Secure Defaults**: HTTPS preferred, secure authentication handling
 * - **Header Management**: Case-insensitive header handling with duplicate support
 * - **Authentication**: HTTP Basic Auth and OAuth2 Bearer token support
 * - **Request Isolation**: Each request isolated with separate configuration
 * - **Error Handling**: Comprehensive cURL error capture without information leakage
 *
 * ## Usage Patterns
 * ```php
 * // Simple GET request
 * $result = HttpClient::get('https://api.example.com/users');
 *
 * // POST with authentication
 * $config = new HttpClientConfig('api_request');
 * $config->bearerToken('abc123');
 * $result = HttpClient::postJson('https://api.example.com/users', $userData, $config);
 *
 * // Advanced configuration
 * $config = new HttpClientConfig();
 * $config->timeout(60);
 * $config->setBasicAuth('user', 'pass');
 * $config->addHeader('X-Custom', 'value');
 * $result = HttpClient::put('https://api.example.com/resource/123', $data, $config);
 * ```
 *
 * ## Performance and Debugging
 * - HttpResult objects automatically track request timing via Timer integration
 * - Verbose logging captures complete cURL communication for troubleshooting
 * - Request/response inspection available through HttpResult methods
 * - Last request always accessible via lastHttpResult() for debugging
 *
 * ## Testing and Mocking
 * - Complete request mocking via fakeResponder() for unit tests
 * - Mock responses return proper HttpResult objects for compatibility
 * - EventManager integration for request lifecycle monitoring
 * - Legacy utilities for cURL command generation and file downloads
 *
 * @package Primordyx
 * @since 1.0.0
 */
class HttpClient
{

    /**
     * Most recently executed HTTP request result for debugging and inspection
     *
     * Automatically populated with HttpResult object from each HTTP request.
     * Provides access to last request details without requiring explicit result storage.
     * Useful for debugging, logging, and post-request analysis.
     *
     * @var HttpResult|null
     * @since 1.0.0
     */
    public static HttpResult|null $lastHttpResult = null;

    /**
     * Optional mock response handler for testing and development
     *
     * When configured, this callable intercepts all HTTP requests and returns mock
     * HttpResult objects instead of performing real network requests. Essential for
     * unit testing and development environments requiring predictable API responses.
     *
     * Signature: function(string $method, string $url, mixed $payload, HttpClientConfig $config): HttpResult
     *
     * @var callable|null
     * @since 1.0.0
     */
    protected static $fakeResponder = null;

    /**
     * Configure mock response handler for testing and development
     *
     * Installs or removes a callable that intercepts all HTTP requests made by HttpClient.
     * When active, the mock handler receives request details and must return a properly
     * configured HttpResult object. Essential for unit testing APIs without network dependencies.
     *
     * ## Mock Handler Signature
     * ```php
     * function(string $method, string $url, mixed $payload, HttpClientConfig $config): HttpResult
     * ```
     *
     * ## Handler Responsibilities
     * - Create HttpResult with provided config
     * - Set appropriate response data, headers, and status
     * - Call stopTimer() to complete performance tracking
     * - Return properly configured HttpResult object
     *
     * ## EventManager Integration
     * Fires 'HttpClient.request.CallingFakeResponder' event when mock handler is invoked.
     *
     * @param callable|null $callback Mock handler function or null to disable mocking
     * @return void
     * @since 1.0.0
     *
     * @example Basic Mock Setup
     * ```php
     * HttpClient::fakeResponder(function($method, $url, $payload, $config) {
     *     $result = new HttpResult($config);
     *     $result->method($method);
     *     $result->url($url);
     *     $result->response('{"success": true, "mock": true}');
     *     $result->stopTimer();
     *     return $result;
     * });
     *
     * // All requests now return mock data
     * $response = HttpClient::get('https://api.example.com');
     * ```
     *
     * @example Conditional Mocking
     * ```php
     * HttpClient::fakeResponder(function($method, $url, $payload, $config) {
     *     $result = new HttpResult($config);
     *     $result->method($method)->url($url);
     *
     *     if (str_contains($url, '/users')) {
     *         $result->response('{"users": [{"id": 1, "name": "Test User"}]}');
     *     } else {
     *         $result->response('{"error": "Not found"}');
     *         $result->curlInfo(['http_code' => 404]);
     *     }
     *
     *     $result->stopTimer();
     *     return $result;
     * });
     * ```
     *
     * @example Disable Mocking
     * ```php
     * HttpClient::fakeResponder(null); // Restore normal HTTP behavior
     * ```
     */
    public static function fakeResponder(?callable $callback): void
    {
        self::$fakeResponder = $callback;
    }




    /**
     * Execute HTTP GET request to retrieve data from remote endpoint
     *
     * Performs idempotent GET request to specified URL with optional configuration.
     * Automatically creates HttpResult with Timer integration for performance monitoring.
     * No request body is sent with GET requests per HTTP standard.
     *
     * @param string $url Target URL to request (must include protocol: http:// or https://)
     * @param HttpClientConfig|null $config Optional request configuration (headers, auth, timeouts)
     * @return HttpResult Complete response object with body, headers, timing, and metadata
     * @since 1.0.0
     *
     * @example Simple Data Retrieval
     * ```php
     * $result = HttpClient::get('https://api.github.com/users/octocat');
     * $userData = json_decode($result->response(), true);
     * ```
     *
     * @example With Authentication
     * ```php
     * $config = new HttpClientConfig('github_api');
     * $config->bearerToken('ghp_abc123...');
     * $result = HttpClient::get('https://api.github.com/user', $config);
     * ```
     *
     * @example With Custom Headers
     * ```php
     * $config = new HttpClientConfig();
     * $config->addHeader('Accept', 'application/vnd.github.v3+json');
     * $config->userAgent('MyApp/1.0');
     * $result = HttpClient::get('https://api.github.com/repos/owner/repo', $config);
     * ```
     */
    public static function get(string $url, HttpClientConfig $config = null): HttpResult
    {
        return self::request('GET', $url,null,$config);
    }

    /**
     * Execute HTTP POST request to send data to remote endpoint
     *
     * Performs POST request with optional payload to create resources or submit data.
     * Supports multiple payload formats including strings, arrays, and file uploads.
     * Automatically configures cURL options based on payload type.
     *
     * ## Payload Handling
     * - **String payloads**: Sent as-is with Content-Type from headers
     * - **Array payloads**: URL-encoded for form submissions or multipart for file uploads
     * - **Null payload**: Empty POST request (valid for some APIs)
     * - **File uploads**: Use CURLFile objects in array payload
     *
     * @param string $url Target URL to send data to (must include protocol)
     * @param mixed|null $payload Request body data (string, array, or null)
     * @param HttpClientConfig|null $config Optional request configuration
     * @return HttpResult Complete response object with server response and metadata
     * @since 1.0.0
     *
     * @example Form Data Submission
     * ```php
     * $formData = ['name' => 'John Doe', 'email' => 'john@example.com'];
     * $result = HttpClient::post('https://api.example.com/users', $formData);
     * ```
     *
     * @example Raw Data Post
     * ```php
     * $xmlData = '<user><name>John</name><email>john@example.com</email></user>';
     * $config = new HttpClientConfig();
     * $config->addHeader('Content-Type', 'application/xml');
     * $result = HttpClient::post('https://api.example.com/users', $xmlData, $config);
     * ```
     *
     * @example File Upload
     * ```php
     * $uploadData = [
     *     'document' => new CURLFile('/path/to/file.pdf', 'application/pdf'),
     *     'description' => 'Important document'
     * ];
     * $result = HttpClient::post('https://api.example.com/upload', $uploadData);
     * ```
     */
    public static function post(string $url, mixed $payload = null, HttpClientConfig $config = null): HttpResult
    {
        return self::request('POST', $url, $payload, $config);
    }

    /**
     * Execute HTTP POST request with automatic JSON encoding and headers
     *
     * Convenience method that automatically JSON-encodes array payload and sets
     * appropriate Content-Type header for JSON API communication. Equivalent to
     * calling post() with json_encode() and manual header configuration.
     *
     * ## Automatic Configuration
     * - Payload automatically JSON-encoded using json_encode()
     * - Content-Type header automatically set to 'application/json'
     * - HttpClientConfig created automatically if not provided
     * - All other POST functionality available (auth, timeouts, etc.)
     *
     * @param string $url Target API endpoint for JSON data submission
     * @param array $payload Associative array to be JSON-encoded as request body
     * @param HttpClientConfig|null $config Optional configuration (auto-created if null)
     * @return HttpResult Complete response object with API response and metadata
     * @since 1.0.0
     *
     * @example REST API Resource Creation
     * ```php
     * $userData = [
     *     'name' => 'Jane Smith',
     *     'email' => 'jane@example.com',
     *     'role' => 'admin'
     * ];
     * $result = HttpClient::postJson('https://api.example.com/v1/users', $userData);
     * $newUser = json_decode($result->response(), true);
     * ```
     *
     * @example With Authentication
     * ```php
     * $config = new HttpClientConfig('create_user');
     * $config->bearerToken('Bearer abc123...');
     *
     * $result = HttpClient::postJson(
     *     'https://api.example.com/v1/users',
     *     ['name' => 'John', 'email' => 'john@example.com'],
     *     $config
     * );
     * ```
     *
     * @example Complex Nested Data
     * ```php
     * $orderData = [
     *     'customer' => ['id' => 123, 'name' => 'John Doe'],
     *     'items' => [
     *         ['sku' => 'ABC123', 'quantity' => 2, 'price' => 29.99],
     *         ['sku' => 'XYZ789', 'quantity' => 1, 'price' => 49.99]
     *     ],
     *     'shipping' => ['method' => 'overnight', 'address' => '...']
     * ];
     * $result = HttpClient::postJson('https://api.example.com/orders', $orderData);
     * ```
     */
    public static function postJson(string $url, array $payload, HttpClientConfig $config = null): HttpResult
    {
        if ($config === null) {
            $config = new HttpClientConfig(); // fallback default
        }
        $config->addHeader('Content-Type', 'application/json');
        return self::post($url, json_encode($payload), $config);
    }

    /**
     * Execute HTTP PUT request to update or create resource at specific endpoint
     *
     * Performs idempotent PUT request to update entire resource or create new resource
     * at specified URL. Supports same payload formats as POST method but with different
     * semantic meaning per REST conventions.
     *
     * ## REST Semantics
     * - **Update Existing**: Replace entire resource with provided data
     * - **Create New**: Create resource at specific URL if it doesn't exist
     * - **Idempotent**: Multiple identical requests should have same result
     * - **Complete Replacement**: Partial updates typically use PATCH method
     *
     * @param string $url Target resource URL for update/creation
     * @param mixed|null $payload Complete resource data (string, array, or null)
     * @param HttpClientConfig|null $config Optional request configuration
     * @return HttpResult Complete response with server confirmation and metadata
     * @since 1.0.0
     *
     * @example Update User Resource
     * ```php
     * $userData = ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'active' => true];
     * $result = HttpClient::put('https://api.example.com/users/123', $userData);
     * ```
     *
     * @example JSON Resource Update
     * ```php
     * $config = new HttpClientConfig('update_user');
     * $config->addHeader('Content-Type', 'application/json');
     * $config->bearerToken('abc123');
     *
     * $userData = json_encode(['name' => 'Updated Name', 'status' => 'active']);
     * $result = HttpClient::put('https://api.example.com/users/456', $userData, $config);
     * ```
     *
     * @example Create Resource at Specific URL
     * ```php
     * // Create new document with specific ID
     * $docData = ['title' => 'New Document', 'content' => 'Document content...'];
     * $result = HttpClient::put('https://api.example.com/documents/new-doc-id', $docData);
     * ```
     */
    public static function put(string $url, mixed $payload = null, HttpClientConfig $config = null): HttpResult
    {
        return self::request('PUT', $url, $payload, $config);
    }

    /**
     * Execute HTTP DELETE request to remove resource from remote endpoint
     *
     * Performs idempotent DELETE request to remove specified resource. No request
     * body is typically sent with DELETE requests per REST conventions, though
     * some APIs may accept additional parameters via query string or headers.
     *
     * ## REST Semantics
     * - **Resource Removal**: Delete specified resource from server
     * - **Idempotent**: Multiple identical requests should have same result
     * - **No Payload**: DELETE requests typically don't include request body
     * - **Status Codes**: Usually returns 200, 204, or 404 depending on API design
     *
     * @param string $url Target resource URL to delete
     * @param HttpClientConfig|null $config Optional configuration (typically for auth/headers)
     * @return HttpResult Response confirmation with status code and any returned data
     * @since 1.0.0
     *
     * @example Simple Resource Deletion
     * ```php
     * $result = HttpClient::delete('https://api.example.com/users/123');
     * if ($result->curlInfo()['http_code'] === 204) {
     *     echo "User deleted successfully";
     * }
     * ```
     *
     * @example Authenticated Deletion
     * ```php
     * $config = new HttpClientConfig('delete_resource');
     * $config->bearerToken('abc123...');
     * $result = HttpClient::delete('https://api.example.com/documents/456', $config);
     * ```
     *
     * @example Bulk Deletion with Query Parameters
     * ```php
     * $config = new HttpClientConfig();
     * $config->setBasicAuth('admin', 'password');
     * $result = HttpClient::delete('https://api.example.com/posts?status=draft', $config);
     * ```
     */
    public static function delete(string $url,HttpClientConfig $config = null): HttpResult
    {
        return self::request('DELETE', $url, null, $config);
    }


    /**
     * Core HTTP request implementation using cURL
     *
     * Internal method that executes HTTP requests with cURL, handles authentication,
     * captures responses, and manages performance tracking via HttpResult objects.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string $url Target URL
     * @param mixed|null $payload Request body data or null
     * @param HttpClientConfig|null $config Request configuration or null for defaults
     * @return HttpResult Complete response object with metadata
     * @since 1.0.0
     */
    private static function request(string $method, string $url, mixed $payload = null, HttpClientConfig $config = null): HttpResult
    {
        if ($config === null) {
            $config = new HttpClientConfig(); // fallback default
        }

        // Optional mock response for testing
        if (self::$fakeResponder !== null) {
            EventManager::fire('HttpClient.request.CallingFakeResponder', [ 'fakeResponder' => Callback::info(self::$fakeResponder) ] );
            $mockResult = call_user_func(self::$fakeResponder, $method, $url, $payload, $config);
            if ($mockResult instanceof HttpResult) {
                self::$lastHttpResult = $mockResult;
                return $mockResult;
            }
        }

        EventManager::fire('HttpClient.request.payload', ['payload' => print_r($payload,true) ]);

        $httpResult = new HttpResult($config); // timer starts in __construct()
        $httpResult->method($method);
        $httpResult->url($url);
        $httpResult->payload($payload);

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);

        if (!empty($config->authUser())) {
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($curl, CURLOPT_USERPWD, $config->authUser() . ':' . ($config->authPass() ?? ''));
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FORBID_REUSE, true);  // rarely needed, but I don't trust anything

        curl_setopt($curl, CURLOPT_TIMEOUT, $config->timeout());

        // what type of request is this?
        if ($method === 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        } elseif ($method !== 'GET') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            if ($payload !== null) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
            }
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $config->headers());

        $verboseStream = null;
        if ($config->verboseLogging()) {
            curl_setopt($curl, CURLOPT_VERBOSE, true);
            $verboseStream = fopen('php://temp', 'w+');
            curl_setopt($curl, CURLOPT_STDERR, $verboseStream);
        }

        // Usual options
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false); // Important: we don't want headers in the body

        // this is very obvious what's happening here so... we set a callback to add the response headers to an array
        // that we can use later
        $responseHeaders = [];
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$responseHeaders) {
            $len = strlen($header);
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                if (!isset($responseHeaders[$key])) {
                    $responseHeaders[$key] = $value;
                } else {
                    if (!is_array($responseHeaders[$key])) {
                        $responseHeaders[$key] = [$responseHeaders[$key]];
                    }
                    $responseHeaders[$key][] = $value;
                }
            }
            return $len;
        });

        // Here we go! ------------------------------------------------------------------------------------
        $response = curl_exec($curl);
        $httpResult->response($response);
        $httpResult->curlError( $curlError = curl_error($curl) );
        $httpResult->curlErrNo( curl_errno($curl) );
        $httpResult->curlInfo( curl_getinfo($curl) );
        if ($config->verboseLogging() && $verboseStream) {
            rewind($verboseStream);
            $httpResult->verboseLog( preg_split("/\r\n|\n/", stream_get_contents($verboseStream)) );
            // $httpResult->verboseLog(    explode(  "\r\n",stream_get_contents($verboseStream)   )   );
            fclose($verboseStream);
        } else {
            $httpResult->verboseLog( ['No verbose log available'] );
        }
        $httpResult->responseHeaders($responseHeaders);
        $httpResult->stopTimer();
        EventManager::fire('HttpClient.curl_exec', ['curl_exec' => $response, 'HttpResult' => $httpResult->asArray()] );

        curl_close($curl);

        if (!empty($curlError)) {
            EventManager::fire('HttpClient.curlError', ['HttpResult' => $httpResult->asArray()] );
        }

        // keep just in case...
        self::$lastHttpResult = $httpResult;

        return $httpResult;

    }

    /**
     * Retrieve the most recent HttpResult object for debugging and inspection
     *
     * Returns the HttpResult from the last HTTP request made by any HttpClient method.
     * Useful for post-request analysis, debugging, and accessing detailed request
     * metadata without storing HttpResult objects explicitly.
     *
     * ## Use Cases
     * - **Debugging**: Inspect failed requests without complex error handling
     * - **Logging**: Access detailed request/response data for audit trails
     * - **Testing**: Verify request details in unit tests and integration tests
     * - **Performance Analysis**: Review timing and cURL metadata after requests
     *
     * @return HttpResult Last executed request result or null if no requests made
     * @since 1.0.0
     *
     * @example Debug Request Details
     * ```php
     * HttpClient::get('https://api.example.com/users');
     * $last = HttpClient::lastHttpResult();
     *
     * echo "Request took: " . $last->timerDetails['elapsed'] . " seconds\n";
     * echo "HTTP Status: " . $last->curlInfo()['http_code'] . "\n";
     * echo "Response size: " . strlen($last->response()) . " bytes\n";
     * ```
     *
     * @example Error Analysis
     * ```php
     * $result = HttpClient::post('https://api.example.com/data', $payload);
     * if ($result->curlError()) {
     *     $debug = HttpClient::lastHttpResult();
     *     error_log("cURL Error: " . $debug->curlError());
     *     error_log("Request URL: " . $debug->url());
     *     error_log("Request Method: " . $debug->method());
     * }
     * ```
     */
    public static function lastHttpResult(): HttpResult {
        return self::$lastHttpResult;
    }



    // LEGACY STUFF THAT I JUST DID NOT WANT TO DELETE YET. ==========================================================

    /**
     * Generate equivalent cURL command line for debugging and testing purposes
     *
     * Legacy utility that constructs shell-safe cURL command equivalent to HttpClient
     * request parameters. Useful for debugging, documentation, and manual request testing
     * outside of PHP environment.
     *
     * ## Command Generation
     * - Escapes all arguments for shell safety using escapeshellarg()
     * - Includes headers with -H flags
     * - Handles payload data with -d flag
     * - Uses POST method by default (legacy behavior)
     * - Generates executable command for terminal use
     *
     * @param string $url Target URL for the cURL command
     * @param array<string> $headers Raw header strings (e.g., "Content-Type: application/json")
     * @param mixed $payload Request body data (string or array)
     * @return string Shell-safe cURL command equivalent to request parameters
     * @since 1.0.0
     *
     * @example Generate Debug Command
     * ```php
     * $headers = ['Content-Type: application/json', 'Authorization: Bearer abc123'];
     * $payload = '{"name": "test", "email": "test@example.com"}';
     * $command = HttpClient::buildCurlCommand('https://api.example.com/users', $headers, $payload);
     *
     * // Output: curl -X POST 'https://api.example.com/users' -H 'Content-Type: application/json'
     * //         -H 'Authorization: Bearer abc123' -d '{"name": "test", "email": "test@example.com"}'
     * echo $command;
     * ```
     *
     * @example Array Payload Handling
     * ```php
     * $headers = ['Accept: application/json'];
     * $payload = ['user' => 'john', 'action' => 'login'];
     * $command = HttpClient::buildCurlCommand('https://api.example.com/auth', $headers, $payload);
     *
     * // Payload automatically JSON-encoded for command generation
     * ```
     */
    public static function buildCurlCommand(string $url, array $headers, mixed $payload): string
    {
        $cmd = ["curl -X POST", escapeshellarg($url)];
        foreach ($headers as $h) {
            $cmd[] = "-H " . escapeshellarg($h);
        }
        if ($payload) {
            $cmd[] = "-d " . escapeshellarg(is_string($payload) ? $payload : json_encode($payload));
        }
        return implode(' ', $cmd);
    }

    /**
     * Download file from URL directly to local filesystem using cURL
     *
     * Legacy utility for simple file downloads without the full HttpClient/HttpResult
     * overhead. Writes downloaded content directly to specified file path using cURL
     * file stream handling for memory-efficient large file downloads.
     *
     * ## Download Features
     * - **Memory Efficient**: Streams directly to file without loading into memory
     * - **Redirect Following**: Automatically follows HTTP redirects (FOLLOWLOCATION)
     * - **Timeout Protection**: 30-second timeout to prevent hung downloads
     * - **Overwrite Behavior**: Target file is overwritten if it exists
     * - **Simple Error Handling**: Returns boolean success/failure status
     *
     * ## Use Cases
     * - Quick file downloads in utility scripts
     * - Backup and synchronization tools
     * - Asset downloads for applications
     * - Simple file retrieval without response analysis needs
     *
     * @param string $url Source URL to download from
     * @param string $destPath Local filesystem path for downloaded file (will be overwritten)
     * @return bool True if download completed successfully, false on any error
     * @since 1.0.0
     *
     * @example Download Application Asset
     * ```php
     * $success = HttpClient::downloadToFile(
     *     'https://github.com/user/repo/archive/main.zip',
     *     '/tmp/repo-archive.zip'
     * );
     *
     * if ($success) {
     *     echo "Archive downloaded successfully";
     * } else {
     *     echo "Download failed";
     * }
     * ```
     *
     * @example Backup Configuration File
     * ```php
     * $configUrl = 'https://api.example.com/config/production.json';
     * $localPath = '/var/backups/config-' . date('Y-m-d') . '.json';
     *
     * if (HttpClient::downloadToFile($configUrl, $localPath)) {
     *     echo "Configuration backup created: $localPath";
     * }
     * ```
     */
    public static function downloadToFile(string $url, string $destPath): bool
    {
        $fp = fopen($destPath, 'w');
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $ok = curl_exec($curl) !== false;
        fclose($fp);
        curl_close($curl);
        return $ok;
    }

}
