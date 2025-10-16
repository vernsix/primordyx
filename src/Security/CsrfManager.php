<?php

/**
 * File: web/vendor/vernsix/primordyx/src/Security/CsrfManager.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/web/vendor/vernsix/primordyx/src/Security/CsrfManager.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Security;

use Primordyx\Data\Safe;
use Primordyx\Utils\RandomStuff;
use Random\RandomException;

/**
 * CSRF Token Management System
 *
 * Provides comprehensive CSRF protection with automatic token generation, validation,
 * and cleanup using the Primordyx Safe session management system. Designed as a
 * standalone security component that integrates seamlessly with the framework's
 * secure session architecture.
 *
 * ## Security Features
 * - **One-time tokens**: Tokens are consumed after successful validation
 * - **Cryptographically secure**: Uses random_bytes() for token generation
 * - **Safe session storage**: Uses Primordyx Safe class instead of raw $_SESSION
 * - **Form-specific tokens**: Different tokens for different forms on same page
 * - **Timing attack protection**: Uses hash_equals() for secure comparison
 * - **Automatic cleanup**: Removes old tokens to prevent accumulation
 *
 * ## Integration with Primordyx Safe
 * - Leverages Safe::set(), Safe::get(), Safe::has(), Safe::forget() for storage
 * - Benefits from Safe's database-backed session security
 * - Works with Safe's expiration and cleanup mechanisms
 * - Maintains framework consistency and security standards
 *
 * ## Token Storage Architecture
 * Tokens are stored using the Safe session system with keys formatted as:
 * "csrf_token_{$formName}" allowing multiple independent form tokens per session.
 *
 * ## Usage Examples
 * ```php
 * // Generate token for form
 * $token = CsrfManager::generateToken('user_registration');
 *
 * // Validate submitted token
 * if (CsrfManager::validateToken($_POST['csrf_token'], 'user_registration')) {
 *     // Process form safely
 * }
 *
 * // Generate HTML field
 * echo CsrfManager::field('contact_form');
 * ```
 *
 * ## Form Integration
 * Designed to work seamlessly with FormBuilder but can be used independently:
 * - FormBuilder automatically handles token generation and validation
 * - Manual forms can use static methods for token management
 * - Middleware can use validation methods for API protection
 *
 * @package Primordyx\Security
 * @since 1.0.0
 */
class CsrfManager
{
    /**
     * Default token length in bytes (32 bytes = 64 hex characters)
     */
    protected const TOKEN_LENGTH = 32;

    /**
     * Session key prefix for CSRF tokens
     */
    protected const TOKEN_PREFIX = 'csrf_token_';

    /**
     * Generate a cryptographically secure CSRF token
     *
     * Creates a new CSRF token using random_bytes() and stores it in the Safe
     * session system for later validation. Each form can have its own token to
     * prevent token reuse across different forms on the same page.
     *
     * ## Token Storage
     * Tokens are stored in Safe session storage with the key format:
     * "csrf_token_{$formName}" allowing multiple independent form tokens.
     *
     * ## Security Considerations
     * - Uses 32 bytes of cryptographically secure random data
     * - Converted to hexadecimal for safe transport in HTML forms
     * - Each call generates a new token, overwriting any existing token for the form
     * - Benefits from Safe's secure database-backed session storage
     *
     * @param string $formName Unique identifier for the form (default: 'default')
     * @return string 64-character hexadecimal token
     *
     * @throws \RuntimeException If secure random number generation fails
     *
     * @example Generate Token for Specific Form
     * ```php
     * $loginToken = CsrfManager::generateToken('login_form');
     * $registerToken = CsrfManager::generateToken('register_form');
     * // Both can exist simultaneously on the same page
     * ```
     *
     * @since 1.0.0
     */
    public static function generateToken(string $formName = 'default'): string
    {
        try {
            $token = RandomStuff::csrfToken(true);
            $sessionKey = self::TOKEN_PREFIX . $formName;

            // Store token using Safe session system
            Safe::set($sessionKey, $token);

            return $token;

        } catch (RandomException $e) {
            throw new \RuntimeException(
                'Failed to generate cryptographically secure CSRF token: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Validate CSRF token with automatic cleanup
     *
     * Validates a submitted CSRF token against the stored Safe session token using
     * timing-attack resistant comparison. Tokens are automatically cleaned up
     * after successful validation to enforce one-time usage.
     *
     * ## Validation Process
     * 1. Retrieves stored token from Safe session using form name
     * 2. Performs timing-attack resistant comparison with hash_equals()
     * 3. Removes token from Safe session if validation succeeds
     * 4. Returns boolean result without revealing timing information
     *
     * ## Security Benefits
     * - **One-time usage**: Prevents token replay attacks
     * - **Timing attack resistance**: Uses hash_equals() for secure comparison
     * - **Automatic cleanup**: Uses Safe::forget() to remove tokens after use
     * - **Session isolation**: Each form's tokens are independent
     * - **Safe integration**: Benefits from Safe's security architecture
     *
     * @param string $token The submitted token to validate
     * @param string $formName Form identifier (must match generation call)
     * @return bool True if token is valid and matches stored token
     *
     * @example Token Validation in Controller
     * ```php
     * if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     *     $token = $_POST['csrf_token'] ?? '';
     *     $formName = $_POST['form_name'] ?? 'default';
     *
     *     if (!CsrfManager::validateToken($token, $formName)) {
     *         Utils::jsonResponse(['error' => 'Invalid security token'], 403);
     *         return;
     *     }
     *
     *     // Process form safely...
     * }
     * ```
     *
     * @since 1.0.0
     */
    public static function validateToken(string $token, string $formName = 'default'): bool
    {
        $sessionKey = self::TOKEN_PREFIX . $formName;

        // Get stored token from Safe session
        $storedToken = Safe::get($sessionKey);

        // Validate token exists and matches
        if (empty($storedToken) || !is_string($storedToken)) {
            return false;
        }

        // Use hash_equals for timing attack resistance
        $valid = hash_equals($storedToken, $token);

        // Clean up token after validation (one-time use)
        if ($valid) {
            Safe::forget($sessionKey);
        }

        return $valid;
    }

    /**
     * Generate HTML hidden input field with CSRF token
     *
     * Convenience method that generates a complete HTML hidden input field
     * containing a CSRF token. Useful for manual form creation or when not
     * using FormBuilder class.
     *
     * ## Generated HTML
     * Returns a hidden input field with name "csrf_token" and a newly generated
     * token value. The field is ready to be inserted into any HTML form.
     *
     * @param string $formName Form identifier for token generation
     * @return string Complete HTML hidden input field
     *
     * @example Manual Form Creation
     * ```php
     * echo '<form method="POST" action="/contact">';
     * echo CsrfManager::field('contact_form');
     * echo '<input type="email" name="email" required>';
     * echo '<button type="submit">Send</button>';
     * echo '</form>';
     * ```
     *
     * @since 1.0.0
     */
    public static function field(string $formName = 'default'): string
    {
        $token = self::generateToken($formName);
        return '<input type="hidden" name="csrf_token" value="' .
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Generate hidden form name field
     *
     * Generates a hidden input field containing the form name identifier.
     * Useful when multiple forms exist on the same page and you need to
     * identify which form was submitted.
     *
     * @param string $formName Form identifier
     * @return string HTML hidden input field with form name
     *
     * @example Multiple Forms Page
     * ```php
     * echo '<form method="POST" action="/login">';
     * echo CsrfManager::field('login_form');
     * echo CsrfManager::formNameField('login_form');
     * echo '<!-- login fields -->';
     * echo '</form>';
     *
     * echo '<form method="POST" action="/register">';
     * echo CsrfManager::field('register_form');
     * echo CsrfManager::formNameField('register_form');
     * echo '<!-- registration fields -->';
     * echo '</form>';
     * ```
     *
     * @since 1.0.0
     */
    public static function formNameField(string $formName): string
    {
        return '<input type="hidden" name="form_name" value="' .
            htmlspecialchars($formName, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Check if a valid token exists for a form
     *
     * Checks whether a CSRF token has been generated and stored for the specified
     * form without consuming or validating it. Useful for conditional form rendering
     * or debugging token state.
     *
     * @param string $formName Form identifier to check
     * @return bool True if a token exists for the form
     *
     * @example Conditional Token Generation
     * ```php
     * if (!CsrfManager::hasToken('user_form')) {
     *     // Generate token if none exists
     *     $token = CsrfManager::generateToken('user_form');
     * }
     * ```
     *
     * @since 1.0.0
     */
    public static function hasToken(string $formName = 'default'): bool
    {
        $sessionKey = self::TOKEN_PREFIX . $formName;
        return Safe::has($sessionKey);
    }

    /**
     * Get stored token without consuming it
     *
     * Retrieves the stored CSRF token for a form without removing it from storage.
     * Useful for debugging or situations where you need to inspect the token
     * without triggering validation.
     *
     * @param string $formName Form identifier
     * @return string|null The stored token, or null if none exists
     *
     * @example Token Inspection
     * ```php
     * $storedToken = CsrfManager::getToken('api_form');
     * if ($storedToken) {
     *     // Token exists, form can be rendered
     * }
     * ```
     *
     * @since 1.0.0
     */
    public static function getToken(string $formName = 'default'): ?string
    {
        $sessionKey = self::TOKEN_PREFIX . $formName;
        $token = Safe::get($sessionKey);

        return is_string($token) ? $token : null;
    }

    /**
     * Remove a specific form's CSRF token
     *
     * Manually removes a CSRF token from Safe session storage. Useful for
     * cleanup operations or when you need to invalidate a form's token
     * without validation.
     *
     * @param string $formName Form identifier to remove
     * @return bool True if token existed and was removed
     *
     * @example Manual Token Cleanup
     * ```php
     * // Cancel form operation
     * if (CsrfManager::removeToken('checkout_form')) {
     *     // Token was removed, form is now invalid
     * }
     * ```
     *
     * @since 1.0.0
     */
    public static function removeToken(string $formName = 'default'): bool
    {
        $sessionKey = self::TOKEN_PREFIX . $formName;
        $existed = Safe::has($sessionKey);

        if ($existed) {
            Safe::forget($sessionKey);
        }

        return $existed;
    }

    /**
     * Clear all CSRF tokens from Safe session
     *
     * Removes all CSRF tokens from the current Safe session. Useful for logout
     * operations or when performing session cleanup to prevent token accumulation.
     *
     * ## When to Use
     * - User logout to clear all form tokens
     * - Session cleanup operations
     * - Testing scenarios requiring clean state
     * - Security incidents requiring token invalidation
     *
     * @return int Number of tokens that were removed
     *
     * @example Logout Cleanup
     * ```php
     * function logout() {
     *     $removedTokens = CsrfManager::clearAllTokens();
     *     Safe::destroy(); // Or appropriate Safe cleanup
     *     // ... redirect to login
     * }
     * ```
     *
     * @since 1.0.0
     */
    public static function clearAllTokens(): int
    {
        $removedCount = 0;

        // Get all session data from Safe
        $allSessionData = Safe::all();

        // Iterate through all session keys
        foreach ($allSessionData as $key => $value) {
            // Check if this key is a CSRF token (starts with our prefix)
            if (str_starts_with($key, self::TOKEN_PREFIX)) {
                Safe::forget($key);
                $removedCount++;
            }
        }

        return $removedCount;
    }

    /**
     * Generate multiple tokens for forms
     *
     * Convenience method to generate CSRF tokens for multiple forms at once.
     * Returns an associative array with form names as keys and tokens as values.
     *
     * @param array $formNames Array of form identifiers
     * @return array Associative array of form names to tokens
     *
     * @example Multiple Form Setup
     * ```php
     * $tokens = CsrfManager::generateMultipleTokens([
     *     'login', 'register', 'contact'
     * ]);
     *
     * // $tokens = [
     * //     'login' => 'abc123...',
     * //     'register' => 'def456...',
     * //     'contact' => 'ghi789...'
     * // ]
     * ```
     *
     * @since 1.0.0
     */
    public static function generateMultipleTokens(array $formNames): array
    {
        $tokens = [];

        foreach ($formNames as $formName) {
            $tokens[$formName] = self::generateToken($formName);
        }

        return $tokens;
    }

    /**
     * Refresh an existing token
     *
     * Generates a new token for a form, replacing any existing token.
     * Useful for AJAX applications or long-lived forms that need token refresh.
     *
     * @param string $formName Form identifier to refresh
     * @return string New token value
     *
     * @example Token Refresh for AJAX
     * ```php
     * // In an AJAX endpoint
     * $newToken = CsrfManager::refreshToken('dynamic_form');
     * Utils::jsonResponse(['csrf_token' => $newToken]);
     * ```
     *
     * @since 1.0.0
     */
    public static function refreshToken(string $formName = 'default'): string
    {
        // Remove existing token first
        self::removeToken($formName);

        // Generate new token
        return self::generateToken($formName);
    }
}
