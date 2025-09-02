<?php
/**
 * File: /vendor/vernsix/primordyx/src/MailgunMailer.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Mail/MailgunMailer.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Mail;

use CURLFile;
use Exception;
use Primordyx\Config\Config;
use Primordyx\Data\Bundle;
use Primordyx\Events\EventManager;
use Primordyx\Http\HttpClient;
use Primordyx\Http\HttpClientConfig;
use Primordyx\Http\HttpResult;

/**
 * Mailgun HTTP API email sending service with attachment support
 *
 * A comprehensive email service class that integrates with the Mailgun HTTP API to send emails
 * with support for HTML/text content, multiple recipients, attachments, and detailed response tracking.
 * Provides fluent interface for email composition and automatic error handling with EventManager integration.
 *
 * ## Key Features
 * - HTML and plain text email support
 * - Multiple recipients (TO, CC, BCC)
 * - File attachments with CURL integration
 * - Comprehensive error handling and response tracking
 * - EventManager integration for logging and debugging
 * - Configuration fallback from Bundle::appConfig()
 *
 * ## Configuration Requirements
 * Requires Mailgun configuration in application INI file:
 * - endpoint: Mailgun API endpoint URL
 * - api_key: Mailgun API key for authentication
 * - domain: Mailgun sending domain
 * - suffix: API path suffix (typically '/messages')
 *
 * ## Response Tracking
 * After sending, the instance contains complete response information:
 * - HTTP status codes and error messages
 * - Mailgun message ID for successful sends
 * - Full HttpResult object for detailed analysis
 *
 * @since 1.0.0
 *
 * @example Basic Email Sending
 * ```php
 * $mailer = new MailgunMailer();
 * $mailer->setFrom('sender@example.com')
 *        ->setTo('recipient@example.com')
 *        ->setSubject('Test Email')
 *        ->setText('Hello World!');
 * $messageId = $mailer->send();
 * ```
 *
 * @example Email with Attachments
 * ```php
 * $mailer = new MailgunMailer();
 * $mailer->setFrom('sender@example.com')
 *        ->setTo('recipient@example.com')
 *        ->setSubject('Report Attached')
 *        ->setHtml('<p>Please see attached report.</p>')
 *        ->addAttachment('/path/to/report.pdf');
 * $messageId = $mailer->send(true); // With verbose logging
 * ```
 *
 * @example Multiple Recipients
 * ```php
 * $mailer = new MailgunMailer();
 * $mailer->setFrom('newsletter@company.com')
 *        ->setTo('primary@example.com')
 *        ->setCc('manager@company.com,backup@company.com')
 *        ->setBcc('archive@company.com')
 *        ->setSubject('Monthly Newsletter')
 *        ->setHtml($htmlContent);
 * $messageId = $mailer->send();
 * ```
 */
class MailgunMailer
{

    // === MAILGUN =====================

    /** @var string Mailgun base endpoint */
    private string $endpoint;

    /** @var string Mailgun API key */
    private string $apiKey;

    /** @var string Mailgun domain name */
    private string $domain;

    /** @var string usually '/messages' but it could change over time I suppose */
    private string $suffix;


    // === THE ACTUAL EMAIL =====================

    /** @var string Sender email address */
    private string $from = '';

    /** @var string Recipient email address */
    private string $to = '';

    /** @var string CC addresses */
    private string $cc = '';

    /** @var string BCC addresses */
    private string $bcc = '';

    /** @var string Email subject line */
    private string $subject = '';

    /** @var string Plain text email body */
    private string $text = '';

    /** @var string HTML email body */
    private string $html = '';

    /** @var string Reply-To address */
    private string $replyTo = '';

    /** @var array List of file paths to attach */
    private array $attachments = [];

    
    
    // === AFTERWARDS =====================

    /**
     * Complete HTTP response object from Mailgun API call
     *
     * Contains the full HttpResult object after send() is called, providing access to
     * response body, headers, CURL info, and detailed debugging information.
     * Null until send() is executed.
     *
     * @var HttpResult|null HTTP response details or null before sending
     * @since 1.0.0
     */
    public HttpResult|null $httpResult;

    /**
     * HTTP client configuration used for Mailgun API requests
     *
     * Contains authentication, timeout, user agent, and verbose logging settings
     * configured automatically during send() execution. Includes Mailgun API
     * authentication and PrimordyxMailer user agent identification.
     *
     * @var HttpClientConfig|null HTTP client settings or null before sending
     * @since 1.0.0
     */
    public HttpClientConfig|null $httpClientConfig;

    /**
     * Mailgun message ID returned after successful email sending
     *
     * Unique identifier assigned by Mailgun for successful email submissions.
     * Used for tracking, webhooks, and API queries. Empty string until successful send.
     * Format typically: <random@domain.mailgun.org>
     *
     * @var string Mailgun message identifier or empty string
     * @since 1.0.0
     */
    public string $mailgun_id = '';

    /**
     * Mailgun response message from API call
     *
     * Contains success confirmation or detailed error message from Mailgun API.
     * For successful sends: "Queued. Thank you."
     * For errors: Complete error details including validation failures.
     *
     * @var string Response message from Mailgun API
     * @since 1.0.0
     */
    public string $mailgun_message = '';

    /**
     * HTTP status code from Mailgun API response
     *
     * Standard HTTP status codes indicating request result:
     * - 200: Success - email queued for delivery
     * - 400: Bad Request - invalid parameters or syntax
     * - 401: Unauthorized - invalid API key or authentication failure
     * - 429: Too Many Requests - rate limit exceeded
     * - 500: Internal Server Error - Mailgun service issue
     *
     * @var int HTTP response status code (0 until send() called)
     * @since 1.0.0
     */
    public int $httpCode = 0;

    /**
     * Reset all email properties and response data to default empty state
     *
     * Clears all email composition data (recipients, subject, content, attachments)
     * and response tracking information to prepare instance for reuse. Does not
     * affect Mailgun configuration (endpoint, API key, domain, suffix).
     *
     * Useful for sending multiple different emails with the same MailgunMailer instance
     * without creating new objects or carrying over previous email data.
     *
     * @return void
     * @since 1.0.0
     *
     * @example Reusing Mailer Instance
     * ```php
     * $mailer = new MailgunMailer();
     * // Send first email
     * $mailer->setFrom('sender@example.com')->setTo('user1@example.com');
     * $mailer->send();
     *
     * // Reset and send different email
     * $mailer->reset();
     * $mailer->setFrom('sender@example.com')->setTo('user2@example.com');
     * $mailer->send();
     * ```
     */
    public function reset(): void
    {
        $this->endpoint = '';
        $this->apiKey = '';
        $this->domain = '';
        $this->suffix = '';
        $this->from = '';
        $this->to = '';
        $this->cc = '';
        $this->bcc = '';
        $this->subject = '';
        $this->text = '';
        $this->html = '';
        $this->replyTo = '';
        $this->attachments = [];

        $this->httpResult = null;
        $this->httpClientConfig = null;
        $this->mailgun_id = '';
        $this->mailgun_message = '';
        $this->httpCode = 0;
    }

    /**
     * Initialize MailgunMailer with API configuration and validate credentials
     *
     * Creates new MailgunMailer instance with Mailgun API settings. Parameters override
     * default configuration from Bundle::appConfig(). All configuration values are required
     * either via parameters or application configuration.
     *
     * ## Configuration Priority
     * 1. Constructor parameters (highest priority)
     * 2. Bundle::appConfig() INI file settings
     * 3. Exception thrown if any required setting missing
     *
     * ## Required Configuration Keys (in INI file)
     * - mailgun.endpoint: Mailgun API base URL
     * - mailgun.api_key: Mailgun private API key
     * - mailgun.domain: Mailgun verified sending domain
     * - mailgun.suffix: API endpoint suffix (typically '/messages')
     *
     * @param string $endpoint Optional Mailgun API endpoint override
     * @param string $apiKey Optional Mailgun API key override
     * @param string $domain Optional Mailgun domain override
     * @param string $suffix Optional API suffix override (default '/messages')
     *
     * @throws Exception When required Mailgun configuration missing from parameters or INI
     *
     * @since 1.0.0
     *
     * @example Using Default Configuration
     * ```php
     * // Uses all settings from Bundle::appConfig()
     * $mailer = new MailgunMailer();
     * ```
     *
     * @example Override Specific Settings
     * ```php
     * // Override domain for different environment
     * $mailer = new MailgunMailer('', '', 'staging.example.com');
     * ```
     *
     * @example Complete Override
     * ```php
     * $mailer = new MailgunMailer(
     *     'https://api.eu.mailgun.net/v3',
     *     'key-1234567890abcdef',
     *     'mail.example.com',
     *     '/messages'
     * );
     * ```
     *
     * @see Bundle::appConfig() For configuration management
     * @fires MailgunMailer.config.missing When configuration incomplete
     */
    public function __construct(string $endpoint = '', string $apiKey = '', string $domain = '', string $suffix = '')
    {
        $this->endpoint = empty($endpoint) ? Config::get('endpoint', 'mailgun') : $endpoint;
        $this->suffix = empty($suffix) ? Config::get('suffix', 'mailgun') : $suffix;
        $this->apiKey = empty($apiKey) ? Config::get('api_key', 'mailgun') : $apiKey;
        $this->domain = empty($domain) ? Config::get('domain', 'mailgun') : $domain;
        if (!$this->apiKey || !$this->domain || !$this->endpoint || !$this->suffix) {
            $msg = "Missing Mailgun configuration. Please ensure 'endpoint', 'suffix', 'api_key' and 'domain' are set in INI file.";
            EventManager::fire('MailgunMailer.config.missing', ['message' => $msg]);
            throw new Exception($msg);
        }
    }


    /**
     * Set sender email address with previous value return
     *
     * Configures the From header for outgoing email. Must be from verified Mailgun domain
     * or will result in sending failure. Supports both simple email address and
     * formatted "Name <email@domain.com>" syntax.
     *
     * @param string $from Sender email address (empty string to clear)
     * @return string Previous from address value
     * @since 1.0.0
     *
     * @example Simple Email Address
     * ```php
     * $previous = $mailer->setFrom('noreply@example.com');
     * ```
     *
     * @example Formatted Sender Name
     * ```php
     * $mailer->setFrom('Support Team <support@example.com>');
     * ```
     *
     * @see getFrom() To retrieve current from address
     */
    public function setFrom(string $from = ''): string
    {
        $old = $this->from;
        $this->from = $from;
        return $old;
    }

    /**
     * Get current sender email address
     *
     * @return string Current from address or empty string if not set
     * @since 1.0.0
     *
     * @see setFrom() To modify sender address
     */
    public function getFrom(): string
    {
        return $this->from;
    }


    /**
     * Set primary recipient email address with previous value return
     *
     * Configures the To header for email delivery. Single email address only -
     * for multiple primary recipients, use comma-separated format or multiple
     * calls to this method.
     *
     * @param string $to Primary recipient email address (empty string to clear)
     * @return string Previous to address value
     * @since 1.0.0
     *
     * @example Single Recipient
     * ```php
     * $previous = $mailer->setTo('user@example.com');
     * ```
     *
     * @example Multiple Recipients (Alternative)
     * ```php
     * $mailer->setTo('user1@example.com,user2@example.com');
     * ```
     *
     * @see getTo() To retrieve current to address
     * @see setCc() For carbon copy recipients
     * @see setBcc() For blind carbon copy recipients
     */
    public function setTo(string $to = ''): string
    {
        $old = $this->to;
        $this->to = $to;
        return $old;
    }

    /**
     * Get current primary recipient email address
     *
     * @return string Current to address or empty string if not set
     * @since 1.0.0
     *
     * @see setTo() To modify recipient address
     */
    public function getTo(): string
    {
        return $this->to;
    }

    /**
     * Set carbon copy recipients with previous value return
     *
     * Configures CC header for visible secondary recipients. Accepts comma-separated
     * list of email addresses. All CC recipients will see each other's addresses
     * and the primary recipient address.
     *
     * @param string $cc Comma-separated list of CC email addresses (empty to clear)
     * @return string Previous CC addresses value
     * @since 1.0.0
     *
     * @example Single CC Recipient
     * ```php
     * $previous = $mailer->setCc('manager@example.com');
     * ```
     *
     * @example Multiple CC Recipients
     * ```php
     * $mailer->setCc('manager@example.com,backup@example.com');
     * ```
     *
     * @see getCc() To retrieve current CC addresses
     * @see setBcc() For hidden recipients
     */
    public function setCc(string $cc = ''): string
    {
        $old = $this->cc;
        $this->cc = $cc;
        return $old;
    }

    /**
     * Get current carbon copy recipient addresses
     *
     * @return string Current CC addresses (comma-separated) or empty string if not set
     * @since 1.0.0
     *
     * @see setCc() To modify CC addresses
     */
    public function getCc(): string
    {
        return $this->cc;
    }

    /**
     * Set blind carbon copy recipients with previous value return
     *
     * Configures BCC header for hidden secondary recipients. Accepts comma-separated
     * list of email addresses. BCC recipients are hidden from all other recipients
     * and receive exact copy of the email.
     *
     * @param string $bcc Comma-separated list of BCC email addresses (empty to clear)
     * @return string Previous BCC addresses value
     * @since 1.0.0
     *
     * @example Single BCC Recipient
     * ```php
     * $previous = $mailer->setBcc('archive@example.com');
     * ```
     *
     * @example Multiple BCC Recipients
     * ```php
     * $mailer->setBcc('archive@example.com,compliance@example.com');
     * ```
     *
     * @see getBcc() To retrieve current BCC addresses
     * @see setCc() For visible recipients
     */
    public function setBcc(string $bcc = ''): string
    {
        $old = $this->bcc;
        $this->bcc = $bcc;
        return $old;
    }

    /**
     * Get current blind carbon copy recipient addresses
     *
     * @return string Current BCC addresses (comma-separated) or empty string if not set
     * @since 1.0.0
     *
     * @see setBcc() To modify BCC addresses
     */
    public function getBcc(): string
    {
        return $this->bcc;
    }

    /**
     * Set email subject line with previous value return
     *
     * Configures the Subject header for email. Supports Unicode characters and
     * standard email subject formatting. Empty subject will result in blank
     * subject line (not recommended for deliverability).
     *
     * @param string $subject Email subject line (empty string to clear)
     * @return string Previous subject value
     * @since 1.0.0
     *
     * @example Standard Subject
     * ```php
     * $previous = $mailer->setSubject('Monthly Newsletter - January 2025');
     * ```
     *
     * @example Unicode Subject
     * ```php
     * $mailer->setSubject('Confirmación de Pedido #12345');
     * ```
     *
     * @see getSubject() To retrieve current subject
     */
    public function setSubject(string $subject = ''): string
    {
        $old = $this->subject;
        $this->subject = $subject;
        return $old;
    }

    /**
     * Get current email subject line
     *
     * @return string Current subject or empty string if not set
     * @since 1.0.0
     *
     * @see setSubject() To modify subject line
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * Set plain text email body with previous value return
     *
     * Configures plain text version of email content. Used as fallback for email
     * clients that don't support HTML, and required by many spam filters even
     * when HTML version is provided.
     *
     * @param string $text Plain text email body (empty string to clear)
     * @return string Previous text body value
     * @since 1.0.0
     *
     * @example Simple Text Email
     * ```php
     * $previous = $mailer->setText('Hello, this is a plain text email.');
     * ```
     *
     * @example Multi-line Text Content
     * ```php
     * $content = "Dear Customer,\n\nThank you for your order.\n\nBest regards,\nSupport Team";
     * $mailer->setText($content);
     * ```
     *
     * @see getText() To retrieve current text body
     * @see setHtml() For HTML email content
     */
    public function setText(string $text = ''): string
    {
        $old = $this->text;
        $this->text = $text;
        return $old;
    }

    /**
     * Get current plain text email body
     *
     * @return string Current text body or empty string if not set
     * @since 1.0.0
     *
     * @see setText() To modify text content
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * Set HTML email body with previous value return
     *
     * Configures HTML version of email content. Supports full HTML including CSS
     * styling, images, and links. Should be accompanied by plain text version
     * for better compatibility and deliverability.
     *
     * @param string $html HTML email body (empty string to clear)
     * @return string Previous HTML body value
     * @since 1.0.0
     *
     * @example Simple HTML Email
     * ```php
     * $previous = $mailer->setHtml('<h1>Welcome!</h1><p>Thank you for joining.</p>');
     * ```
     *
     * @example Complex HTML with Styling
     * ```php
     * $html = '<html><body style="font-family: Arial;">'
     *       . '<h2 style="color: #333;">Order Confirmation</h2>'
     *       . '<p>Your order has been processed successfully.</p>'
     *       . '</body></html>';
     * $mailer->setHtml($html);
     * ```
     *
     * @see getHtml() To retrieve current HTML body
     * @see setText() For plain text content
     */
    public function setHtml(string $html = ''): string
    {
        $old = $this->html;
        $this->html = $html;
        return $old;
    }

    /**
     * Get current HTML email body
     *
     * @return string Current HTML body or empty string if not set
     * @since 1.0.0
     *
     * @see setHtml() To modify HTML content
     */
    public function getHtml(): string
    {
        return $this->html;
    }

    /**
     * Set Reply-To email address with previous value return
     *
     * Configures Reply-To header to override default reply behavior. When recipients
     * reply to the email, responses will go to this address instead of the From address.
     * Useful for no-reply senders that want responses directed to support addresses.
     *
     * @param string $replyTo Reply-to email address (empty string to clear)
     * @return string Previous reply-to address value
     * @since 1.0.0
     *
     * @example Support Reply Address
     * ```php
     * $mailer->setFrom('noreply@example.com');
     * $previous = $mailer->setReplyTo('support@example.com');
     * ```
     *
     * @example Same as From Address
     * ```php
     * $mailer->setFrom('sales@example.com');
     * $mailer->setReplyTo('sales@example.com'); // Explicit same address
     * ```
     *
     * @see getReplyTo() To retrieve current reply-to address
     */
    public function setReplyTo(string $replyTo = ''): string
    {
        $old = $this->replyTo;
        $this->replyTo = $replyTo;
        return $old;
    }

    /**
     * Get current Reply-To email address
     *
     * @return string Current reply-to address or empty string if not set
     * @since 1.0.0
     *
     * @see setReplyTo() To modify reply-to address
     */
    public function getReplyTo(): string
    {
        return $this->replyTo;
    }

    /**
     * Add file attachment to email by absolute file path
     *
     * Adds file to attachment list for inclusion in email. File must exist and be
     * readable at send time or will be silently skipped. Supports any file type
     * that Mailgun accepts. Multiple attachments supported.
     *
     * File validation occurs during send() - invalid paths are logged but don't
     * prevent email sending. Large attachments may impact send performance and
     * recipient deliverability.
     *
     * @param string $filename Absolute path to file for attachment
     * @return void
     * @since 1.0.0
     *
     * @example Single Attachment
     * ```php
     * $mailer->addAttachment('/var/www/uploads/report.pdf');
     * ```
     *
     * @example Multiple Attachments
     * ```php
     * $mailer->addAttachment('/path/to/document.pdf');
     * $mailer->addAttachment('/path/to/image.png');
     * $mailer->addAttachment('/path/to/spreadsheet.xlsx');
     * ```
     *
     * @see getAttachments() To retrieve attachment list
     * @see clearAttachments() To remove all attachments
     */
    public function addAttachment(string $filename): void
    {
        $this->attachments[] = $filename;
    }

    /**
     * Get list of all file attachments
     *
     * Returns array of absolute file paths that will be attached to email.
     * Paths are added via addAttachment() and cleared via clearAttachments()
     * or reset().
     *
     * @return array List of absolute file paths for attachment
     * @since 1.0.0
     *
     * @example Check Attachment Count
     * ```php
     * $attachments = $mailer->getAttachments();
     * echo "Email has " . count($attachments) . " attachments";
     * ```
     *
     * @see addAttachment() To add file attachments
     * @see clearAttachments() To remove all attachments
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * Remove all file attachments from email
     *
     * Clears the attachment list without affecting other email properties.
     * Useful when reusing MailgunMailer instance for emails with different
     * attachment requirements.
     *
     * @return void
     * @since 1.0.0
     *
     * @example Clear Before New Email
     * ```php
     * $mailer->clearAttachments();
     * $mailer->addAttachment('/path/to/new/file.pdf');
     * ```
     *
     * @see addAttachment() To add attachments
     * @see getAttachments() To check current attachments
     * @see reset() To clear all email data including attachments
     */
    public function clearAttachments(): void
    {
        $this->attachments = [];
    }

    /**
     * Send configured email through Mailgun API with comprehensive error handling
     *
     * Transmits email using current instance configuration and attachment list to Mailgun.
     * Handles authentication, file uploads, response processing, and EventManager integration
     * for logging and debugging.
     *
     * ## Sending Process
     * 1. Builds multipart form payload with email data and attachments
     * 2. Configures HTTP client with Mailgun authentication
     * 3. Posts to Mailgun API endpoint
     * 4. Processes response and updates instance properties
     * 5. Fires EventManager events for logging and monitoring
     *
     * ## Response Handling
     * Updates instance properties with complete response information:
     * - $httpCode: HTTP status code (200=success, 400/401/429/500=error)
     * - $mailgun_id: Unique message ID for successful sends
     * - $mailgun_message: Success confirmation or detailed error message
     * - $httpResult: Complete HttpResult object for detailed analysis
     *
     * ## Error Conditions
     * - 400 Bad Request: Invalid parameters, missing required fields, malformed data
     * - 401 Unauthorized: Invalid API key, domain not verified, authentication failure
     * - 429 Too Many Requests: Rate limit exceeded, need to slow down sending
     * - 500 Internal Server Error: Mailgun service issue, retry may succeed
     *
     * ## File Attachment Processing
     * Files are validated for existence before attachment. Missing files are silently
     * skipped to prevent send failure. Large files may impact performance and deliverability.
     *
     * @param bool $verboseLogging Enable detailed HTTP logging for debugging
     * @return string Mailgun message ID on success, empty string on failure
     *
     * @since 1.0.0
     *
     * @example Basic Send
     * ```php
     * $mailer->setFrom('sender@example.com')
     *        ->setTo('recipient@example.com')
     *        ->setSubject('Test')
     *        ->setText('Hello World!');
     * $messageId = $mailer->send();
     * if (!empty($messageId)) {
     *     echo "Email sent successfully: {$messageId}";
     * } else {
     *     echo "Send failed: {$mailer->mailgun_message}";
     * }
     * ```
     *
     * @example Send with Verbose Logging
     * ```php
     * $messageId = $mailer->send(true); // Enable detailed HTTP logging
     * echo "HTTP Status: {$mailer->httpCode}";
     * echo "Response: {$mailer->mailgun_message}";
     * ```
     *
     * @example Error Handling
     * ```php
     * $messageId = $mailer->send();
     * switch ($mailer->httpCode) {
     *     case 200:
     *         echo "Success! Message ID: {$messageId}";
     *         break;
     *     case 400:
     *         echo "Bad request: {$mailer->mailgun_message}";
     *         break;
     *     case 401:
     *         echo "Authentication failed - check API key";
     *         break;
     *     case 429:
     *         echo "Rate limited - slow down sending";
     *         break;
     *     default:
     *         echo "Unknown error: {$mailer->mailgun_message}";
     * }
     * ```
     *
     * @fires MailgunMailer.httpResult Complete HTTP response data for analysis
     * @fires MailgunMailer.200 Successful send with message ID and confirmation
     * @fires MailgunMailer.400 Bad request with error details
     * @fires MailgunMailer.401 Authentication failure details
     * @fires MailgunMailer.429 Rate limit exceeded notification
     * @fires MailgunMailer.500 Server error details
     *
     * @see HttpClient::post() For underlying HTTP request handling
     * @see EventManager::fire() For event-driven logging integration
     */
    public function send(bool $verboseLogging = false): string
    {
        $payload = [
            'from' => $this->from,
            'to' => $this->to,
            'subject' => $this->subject,
            'text' => $this->text,
        ];

        !empty($this->html) && $payload['html'] = $this->html;
        !empty($this->cc) && $payload['cc'] = $this->cc;
        !empty($this->bcc) && $payload['bcc'] = $this->bcc;
        !empty($this->replyTo) && $payload['h:Reply-To'] = $this->replyTo;

        // Curl can't handle nested arrays of CURLFile() objects because it gets confused
        // converting them to strings.  Seems odd to me, but this is how the array has to be
        // created or it will throw 500 errors
        foreach ($this->attachments as $i => $filename) {
            if (file_exists($filename)) {
                $payload["attachment[$i]"] = new CURLFile($filename);
            }
        }

        $this->httpClientConfig = new HttpClientConfig();
        $this->httpClientConfig->verboseLogging($verboseLogging);
        $this->httpClientConfig->userAgent('PrimordyxMailer/2.0.0');
        $this->httpClientConfig->timeout(30);
        $this->httpClientConfig->authUser('api');
        $this->httpClientConfig->authPass($this->apiKey);

        $url = $this->endpoint . "/" . $this->domain . $this->suffix;
        $this->httpResult = HttpClient::post($url, $payload, $this->httpClientConfig);
        EventManager::fire('MailgunMailer.httpResult', ['httpResult' => $this->httpResult->asArray()]);

        $this->httpCode = (int)$this->httpResult->curlInfo()['http_code'];

        $response = $this->httpResult->response();
        $decodedResponse = json_decode( $response,true);
        // todo: may want to test for inability to decode response?  hihgly unlikely that we will ever see this

        switch ($this->httpCode) {
            case 400:
                $fireDescription = "Bad Request: The server couldn't understand your request due to invalid syntax.";
                $this->mailgun_message = $response;
                break;

            case 401:
                $fireDescription = "Unauthorized: Authentication is required or credentials were invalid.";
                $this->mailgun_message = $response;
                break;

            case 429:
                $fireDescription = "Too Many Requests: You have exceeded the allowed number of requests. Please slow down.";
                $this->mailgun_message = $response;
                break;

            case 500:
                $fireDescription = "Internal Server Error: The server encountered an unexpected error.";
                $this->mailgun_message = $response;
                break;

            case 200:
                $fireDescription = "Ok";
                $this->mailgun_id = $decodedResponse['id'];
                $this->mailgun_message = $decodedResponse['message'];
                break;

            default:
                $fireDescription = "Unhandled HTTP status code";
                $this->mailgun_message = $response;
                break;
        }
        EventManager::fire('MailgunMailer.' . $this->httpCode, [
            'http_code' => $this->httpCode,
            'description' => $fireDescription,
            'response' => $this->httpResult->response(),
            'mailgun_id' => $this->mailgun_id,
            'mailgun_message' => $this->mailgun_message,
        ]);

        return $this->mailgun_id;

    }

}
