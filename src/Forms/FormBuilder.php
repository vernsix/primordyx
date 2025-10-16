<?php
/**
 * File: /vendor/vernsix/primordyx/src/FormBuilder.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/FormBuilder.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Forms;

use Primordyx\Data\Validator;
use Primordyx\Security\CsrfManager;

/**
 * Fluent HTML Form Builder with CSS Integration and Validation
 *
 * Comprehensive form building system that generates HTML forms with automatic CSRF
 * protection, validation integration, and modular CSS styling. Designed to work
 * seamlessly with the Primordyx framework's existing Validator class and follows
 * the modular CSS approach for styling isolation.
 *
 * ## Core Features
 * - **Fluent Interface**: Chainable methods for readable form construction
 * - **CSRF Protection**: Automatic token generation and validation integration
 * - **Validation Integration**: Direct integration with Primordyx Validator class
 * - **Error Handling**: Automatic error display and form repopulation
 * - **Modular CSS**: CSS-friendly class generation with configurable prefixes
 * - **Field Types**: Comprehensive support for common HTML input types
 * - **Data Binding**: Automatic form repopulation after validation failures
 *
 * ## Supported Field Types
 * - **Text inputs**: text, email, password, url, tel, number
 * - **Text areas**: Multi-line text input with configurable rows
 * - **Select dropdowns**: Single and multiple selection with option groups
 * - **Checkboxes**: Single checkboxes and checkbox groups
 * - **Radio buttons**: Radio button groups with proper grouping
 * - **File uploads**: File input with validation support
 * - **Hidden fields**: For additional data passing
 *
 * ## CSS Architecture
 * Uses BEM-style naming convention with configurable prefix (default: 'px-form'):
 * - `.px-form` - Form container
 * - `.px-form__field` - Individual field wrapper
 * - `.px-form__label` - Field labels
 * - `.px-form__input` - All input elements
 * - `.px-form__error` - Error message display
 * - `.px-form__input--error` - Error state styling
 *
 * ## Usage Patterns
 * ```php
 * // Basic form creation
 * $form = FormBuilder::create('/users', 'POST')
 *     ->text('name', 'Full Name', ['required' => true])
 *     ->email('email', 'Email Address')
 *     ->password('password', 'Password');
 *
 * // Validation integration
 * if ($form->validate($rules)) {
 *     // Process form
 * } else {
 *     // Display errors automatically
 * }
 *
 * // Render form
 * echo $form->render();
 * ```
 *
 * @package Primordyx\Forms
 * @since 1.0.0
 */
class FormBuilder
{
    /**
     * @var array<array> Collection of form fields
     */
    protected array $fields = [];

    /**
     * @var array<string, array<string>> Validation errors indexed by field name
     */
    protected array $errors = [];

    /**
     * @var array<string, mixed> Form data for repopulation
     */
    protected array $data = [];

    /**
     * @var string Form action URL
     */
    protected string $action;

    /**
     * @var string HTTP method (GET, POST, PUT, DELETE)
     */
    protected string $method;

    /**
     * @var string Unique form identifier for CSRF tokens
     */
    protected string $formName;

    /**
     * @var string CSS class prefix for modular styling
     */
    protected string $cssPrefix = 'px-form';

    /**
     * @var array<string, mixed> Additional form attributes
     */
    protected array $attributes = [];

    /**
     * FormBuilder constructor
     *
     * @param string $action Form action URL
     * @param string $method HTTP method (default: POST)
     */
    public function __construct(string $action = '', string $method = 'POST')
    {
        $this->action = $action;
        $this->method = strtoupper($method);
        $this->formName = 'form_' . uniqid();
    }

    /**
     * Static factory method for creating forms
     *
     * @param string $action Form action URL
     * @param string $method HTTP method
     * @return self New FormBuilder instance
     */
    public static function create(string $action = '', string $method = 'POST'): self
    {
        return new self($action, $method);
    }

    /**
     * Add text input field
     *
     * @param string $name Field name attribute
     * @param string $label Display label
     * @param array $options Field options (required, placeholder, etc.)
     * @return self
     */
    public function text(string $name, string $label, array $options = []): self
    {
        $this->addField('text', $name, $label, $options);
        return $this;
    }

    /**
     * Add email input field with built-in validation
     *
     * @param string $name Field name attribute
     * @param string $label Display label
     * @param array $options Field options
     * @return self
     */
    public function email(string $name, string $label, array $options = []): self
    {
        $this->addField('email', $name, $label, $options);
        return $this;
    }

    /**
     * Add password input field
     *
     * @param string $name Field name attribute
     * @param string $label Display label
     * @param array $options Field options
     * @return self
     */
    public function password(string $name, string $label, array $options = []): self
    {
        $this->addField('password', $name, $label, $options);
        return $this;
    }

    /**
     * Add URL input field
     *
     * @param string $name Field name attribute
     * @param string $label Display label
     * @param array $options Field options
     * @return self
     */
    public function url(string $name, string $label, array $options = []): self
    {
        $this->addField('url', $name, $label, $options);
        return $this;
    }

    /**
     * Add telephone input field
     *
     * @param string $name Field name attribute
     * @param string $label Display label
     * @param array $options Field options
     * @return self
     */
    public function tel(string $name, string $label, array $options = []): self
    {
        $this->addField('tel', $name, $label, $options);
        return $this;
    }

    /**
     * Add number input field
     *
     * @param string $name Field name attribute
     * @param string $label Display label
     * @param array $options Field options (min, max, step)
     * @return self
     */
    public function number(string $name, string $label, array $options = []): self
    {
        $this->addField('number', $name, $label, $options);
        return $this;
    }

    /**
     * Add textarea field
     *
     * @param string $name Field name attribute
     * @param string $label Display label
     * @param array $options Field options (rows, cols, placeholder)
     * @return self
     */
    public function textarea(string $name, string $label, array $options = []): self
    {
        $this->addField('textarea', $name, $label, $options);
        return $this;
    }

    /**
     * Add select dropdown field
     *
     * @param string $name Field name attribute
     * @param string $label Display label
     * @param array $choices Key-value pairs for options
     * @param array $options Field options (multiple, size)
     * @return self
     */
    public function select(string $name, string $label, array $choices, array $options = []): self
    {
        $options['choices'] = $choices;
        $this->addField('select', $name, $label, $options);
        return $this;
    }

    /**
     * Add checkbox field
     *
     * @param string $name Field name attribute
     * @param string $label Display label
     * @param string $value Checkbox value (default: '1')
     * @param array $options Field options
     * @return self
     */
    public function checkbox(string $name, string $label, string $value = '1', array $options = []): self
    {
        $options['value'] = $value;
        $this->addField('checkbox', $name, $label, $options);
        return $this;
    }

    /**
     * Add radio button group
     *
     * @param string $name Field name attribute (shared across group)
     * @param string $label Group label
     * @param array $choices Key-value pairs for radio options
     * @param array $options Field options
     * @return self
     */
    public function radio(string $name, string $label, array $choices, array $options = []): self
    {
        $options['choices'] = $choices;
        $this->addField('radio', $name, $label, $options);
        return $this;
    }

    /**
     * Add file upload field
     *
     * @param string $name Field name attribute
     * @param string $label Display label
     * @param array $options Field options (accept, multiple)
     * @return self
     */
    public function file(string $name, string $label, array $options = []): self
    {
        $this->addField('file', $name, $label, $options);
        return $this;
    }

    /**
     * Add hidden input field
     *
     * @param string $name Field name attribute
     * @param string $value Field value
     * @return self
     */
    public function hidden(string $name, string $value): self
    {
        $this->addField('hidden', $name, '', ['value' => $value]);
        return $this;
    }

    /**
     * Internal method to add field to collection
     *
     * @param string $type Field type
     * @param string $name Field name
     * @param string $label Field label
     * @param array $options Field options
     * @return void
     */
    private function addField(string $type, string $name, string $label, array $options): void
    {
        $this->fields[] = [
            'type' => $type,
            'name' => $name,
            'label' => $label,
            'value' => $this->getFieldValue($type, $name, $options),
            'error' => $this->errors[$name] ?? null,
            'options' => $options
        ];
    }

    /**
     * Get field value based on type and data
     *
     * @param string $type Field type
     * @param string $name Field name
     * @param array $options Field options
     * @return mixed Field value
     */
    private function getFieldValue(string $type, string $name, array $options): mixed
    {
        // Never populate password fields for security
        if ($type === 'password') {
            return '';
        }

        // Use explicit value if provided
        if (isset($options['value'])) {
            return $options['value'];
        }

        // Use data if available
        return $this->data[$name] ?? '';
    }

    /**
     * Set CSS class prefix for styling
     *
     * @param string $prefix CSS class prefix
     * @return self
     */
    public function cssPrefix(string $prefix): self
    {
        $this->cssPrefix = $prefix;
        return $this;
    }

    /**
     * Add form attributes
     *
     * @param array $attributes Attribute key-value pairs
     * @return self
     */
    public function attributes(array $attributes): self
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }

    /**
     * Populate form with data (for repopulation after validation errors)
     *
     * @param array $data Form data
     * @return self
     */
    public function withData(array $data): self
    {
        $this->data = $data;

        // Update existing field values
        foreach ($this->fields as &$field) {
            $field['value'] = $this->getFieldValue($field['type'], $field['name'], $field['options']);
        }

        return $this;
    }

    /**
     * Set validation errors for display
     *
     * @param array $errors Validation errors
     * @return self
     */
    public function withErrors(array $errors): self
    {
        $this->errors = $errors;

        // Update existing field errors
        foreach ($this->fields as &$field) {
            $field['error'] = $errors[$field['name']] ?? null;
        }

        return $this;
    }

    /**
     * Validate form data using Primordyx Validator class
     *
     * @param array $rules Validation rules
     * @return bool True if validation passes
     */
    public function validate(array $rules): bool
    {
        $this->errors = Validator::validate($this->data, $rules);
        $this->withErrors($this->errors);
        return empty($this->errors);
    }

    /**
     * Get form data (useful after validation)
     *
     * @return array Form data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get validation errors
     *
     * @return array Validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if form has validation errors
     *
     * @return bool True if errors exist
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Render complete form HTML with optional CSRF protection
     *
     * @param bool $includeCsrf Whether to include CSRF protection (default: true)
     * @return string Complete HTML form
     */
    public function render(bool $includeCsrf = true): string
    {
        // Build form attributes
        $attrs = $this->attributes;
        $attrs['class'] = ($attrs['class'] ?? '') . ' ' . $this->cssPrefix;
        $attrs['method'] = $this->method;
        $attrs['action'] = $this->action;

        // Handle file uploads
        if ($this->hasFileFields()) {
            $attrs['enctype'] = 'multipart/form-data';
        }

        $attrString = $this->buildAttributeString($attrs);

        $html = "<form{$attrString}>\n";

        // Add CSRF protection if requested
        if ($includeCsrf) {
            $html .= "    " . CsrfManager::field($this->formName) . "\n";
            $html .= "    " . CsrfManager::formNameField($this->formName) . "\n\n";
        }

        foreach ($this->fields as $field) {
            $html .= $this->renderField($field);
        }

        $html .= "    <div class=\"{$this->cssPrefix}__actions\">\n";
        $html .= "        <button type=\"submit\" class=\"{$this->cssPrefix}__button\">Submit</button>\n";
        $html .= "    </div>\n";
        $html .= "</form>";

        return $html;
    }

    /**
     * Check if form has file upload fields
     *
     * @return bool True if file fields exist
     */
    private function hasFileFields(): bool
    {
        foreach ($this->fields as $field) {
            if ($field['type'] === 'file') {
                return true;
            }
        }
        return false;
    }

    /**
     * Build HTML attribute string from array
     *
     * @param array $attributes Attribute key-value pairs
     * @return string HTML attribute string
     */
    private function buildAttributeString(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $key => $value) {
            if ($value !== null && $value !== '') {
                $parts[] = $key . '="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '"';
            }
        }
        return empty($parts) ? '' : ' ' . implode(' ', $parts);
    }

    /**
     * Render individual form field with error handling
     *
     * @param array $field Field configuration
     * @return string HTML for field
     */
    private function renderField(array $field): string
    {
        // Skip hidden fields from normal rendering
        if ($field['type'] === 'hidden') {
            return $this->renderHiddenField($field);
        }

        $errorClass = $field['error'] ? " {$this->cssPrefix}__input--error" : '';
        $required = ($field['options']['required'] ?? false) ? ' required' : '';
        $value = htmlspecialchars((string)($field['value'] ?? ''), ENT_QUOTES, 'UTF-8');

        $html = "    <div class=\"{$this->cssPrefix}__field\">\n";

        // Label (except for checkboxes which have special handling)
        if ($field['type'] !== 'checkbox') {
            $html .= "        <label class=\"{$this->cssPrefix}__label\" for=\"{$field['name']}\">{$field['label']}</label>\n";
        }

        // Render field based on type
        switch ($field['type']) {
            case 'textarea':
                $html .= $this->renderTextarea($field, $errorClass, $required, $value);
                break;

            case 'select':
                $html .= $this->renderSelect($field, $errorClass, $required, $value);
                break;

            case 'checkbox':
                $html .= $this->renderCheckbox($field, $errorClass, $required);
                break;

            case 'radio':
                $html .= $this->renderRadio($field, $errorClass, $required);
                break;

            default:
                $html .= $this->renderInput($field, $errorClass, $required, $value);
                break;
        }

        // Error messages
        if ($field['error']) {
            $errors = is_array($field['error']) ? $field['error'] : [$field['error']];
            foreach ($errors as $error) {
                $html .= "        <div class=\"{$this->cssPrefix}__error\">{$error}</div>\n";
            }
        }

        $html .= "    </div>\n\n";

        return $html;
    }

    /**
     * Render hidden input field
     *
     * @param array $field Field configuration
     * @return string HTML for hidden field
     */
    private function renderHiddenField(array $field): string
    {
        $value = htmlspecialchars((string)($field['value'] ?? ''), ENT_QUOTES, 'UTF-8');
        return "    <input type=\"hidden\" name=\"{$field['name']}\" value=\"{$value}\">\n";
    }

    /**
     * Render standard input field
     *
     * @param array $field Field configuration
     * @param string $errorClass Error CSS class
     * @param string $required Required attribute
     * @param string $value Field value
     * @return string HTML for input
     */
    private function renderInput(array $field, string $errorClass, string $required, string $value): string
    {
        $attrs = $this->buildFieldAttributes($field, $errorClass, $required);
        $attrs['value'] = $value;
        $attrString = $this->buildAttributeString($attrs);

        return "        <input{$attrString}>\n";
    }

    /**
     * Render textarea field
     *
     * @param array $field Field configuration
     * @param string $errorClass Error CSS class
     * @param string $required Required attribute
     * @param string $value Field value
     * @return string HTML for textarea
     */
    private function renderTextarea(array $field, string $errorClass, string $required, string $value): string
    {
        $attrs = $this->buildFieldAttributes($field, $errorClass, $required);
        $attrs['rows'] = $field['options']['rows'] ?? 4;
        $attrs['cols'] = $field['options']['cols'] ?? null;
        unset($attrs['type'], $attrs['value']); // Remove invalid attributes for textarea

        $attrString = $this->buildAttributeString($attrs);

        return "        <textarea{$attrString}>{$value}</textarea>\n";
    }

    /**
     * Render select dropdown field
     *
     * @param array $field Field configuration
     * @param string $errorClass Error CSS class
     * @param string $required Required attribute
     * @param string $value Field value
     * @return string HTML for select
     */
    private function renderSelect(array $field, string $errorClass, string $required, string $value): string
    {
        $attrs = $this->buildFieldAttributes($field, $errorClass, $required);
        if ($field['options']['multiple'] ?? false) {
            $attrs['multiple'] = 'multiple';
            $attrs['name'] = $field['name'] . '[]';
        }
        unset($attrs['type'], $attrs['value']); // Remove invalid attributes for select

        $attrString = $this->buildAttributeString($attrs);

        $html = "        <select{$attrString}>\n";

        foreach ($field['options']['choices'] ?? [] as $optValue => $optLabel) {
            $selected = ($value == $optValue) ? ' selected' : '';
            $optValue = htmlspecialchars((string)$optValue, ENT_QUOTES, 'UTF-8');
            $optLabel = htmlspecialchars((string)$optLabel, ENT_QUOTES, 'UTF-8');
            $html .= "            <option value=\"{$optValue}\"{$selected}>{$optLabel}</option>\n";
        }

        $html .= "        </select>\n";

        return $html;
    }

    /**
     * Render checkbox field
     *
     * @param array $field Field configuration
     * @param string $errorClass Error CSS class
     * @param string $required Required attribute
     * @return string HTML for checkbox
     */
    private function renderCheckbox(array $field, string $errorClass, string $required): string
    {
        $checkboxValue = $field['options']['value'] ?? '1';
        $checked = ($this->data[$field['name']] ?? false) == $checkboxValue ? ' checked' : '';

        $attrs = $this->buildFieldAttributes($field, $errorClass, $required);
        $attrs['value'] = $checkboxValue;
        $attrString = $this->buildAttributeString($attrs);

        $html = "        <label class=\"{$this->cssPrefix}__checkbox\">\n";
        $html .= "            <input{$attrString}{$checked}>\n";
        $html .= "            <span class=\"{$this->cssPrefix}__checkbox-label\">{$field['label']}</span>\n";
        $html .= "        </label>\n";

        return $html;
    }

    /**
     * Render radio button group
     *
     * @param array $field Field configuration
     * @param string $errorClass Error CSS class
     * @param string $required Required attribute
     * @return string HTML for radio group
     */
    private function renderRadio(array $field, string $errorClass, string $required): string
    {
        $currentValue = $this->data[$field['name']] ?? '';

        $html = "        <fieldset class=\"{$this->cssPrefix}__radio-group\">\n";
        $html .= "            <legend class=\"{$this->cssPrefix}__legend\">{$field['label']}</legend>\n";

        foreach ($field['options']['choices'] ?? [] as $optValue => $optLabel) {
            $checked = ($currentValue == $optValue) ? ' checked' : '';
            $radioId = $field['name'] . '_' . $optValue;

            $attrs = [
                'type' => 'radio',
                'id' => $radioId,
                'name' => $field['name'],
                'class' => $this->cssPrefix . '__input' . $errorClass,
                'value' => $optValue
            ];

            if ($field['options']['required'] ?? false) {
                $attrs['required'] = 'required';
            }

            $attrString = $this->buildAttributeString($attrs);

            $html .= "            <label class=\"{$this->cssPrefix}__radio\">\n";
            $html .= "                <input{$attrString}{$checked}>\n";
            $html .= "                <span class=\"{$this->cssPrefix}__radio-label\">{$optLabel}</span>\n";
            $html .= "            </label>\n";
        }

        $html .= "        </fieldset>\n";

        return $html;
    }

    /**
     * Build common field attributes
     *
     * @param array $field Field configuration
     * @param string $errorClass Error CSS class
     * @param string $required Required attribute
     * @return array Field attributes
     */
    private function buildFieldAttributes(array $field, string $errorClass, string $required): array
    {
        $attrs = [
            'type' => $field['type'],
            'id' => $field['name'],
            'name' => $field['name'],
            'class' => $this->cssPrefix . '__input' . $errorClass
        ];

        // Add optional attributes
        if ($field['options']['placeholder'] ?? false) {
            $attrs['placeholder'] = $field['options']['placeholder'];
        }

        if ($field['options']['required'] ?? false) {
            $attrs['required'] = 'required';
        }

        if ($field['options']['readonly'] ?? false) {
            $attrs['readonly'] = 'readonly';
        }

        if ($field['options']['disabled'] ?? false) {
            $attrs['disabled'] = 'disabled';
        }

        // Number field specific attributes
        if ($field['type'] === 'number') {
            if (isset($field['options']['min'])) {
                $attrs['min'] = $field['options']['min'];
            }
            if (isset($field['options']['max'])) {
                $attrs['max'] = $field['options']['max'];
            }
            if (isset($field['options']['step'])) {
                $attrs['step'] = $field['options']['step'];
            }
        }

        // File field specific attributes
        if ($field['type'] === 'file') {
            if ($field['options']['accept'] ?? false) {
                $attrs['accept'] = $field['options']['accept'];
            }
            if ($field['options']['multiple'] ?? false) {
                $attrs['multiple'] = 'multiple';
            }
        }

        return $attrs;
    }
}

