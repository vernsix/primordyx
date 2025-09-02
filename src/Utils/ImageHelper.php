<?php
/**
 * File: /vendor/vernsix/primordyx/src/ImageHelper.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Utils/ImageHelper.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Utils;

use GdImage;

/**
 * GD-based image manipulation and processing utilities for Primordyx applications
 *
 * Provides a comprehensive toolkit for common image operations including loading, saving,
 * resizing, and format conversion. Built around PHP's GD extension with graceful error
 * handling and detailed operation logging for debugging and monitoring.
 *
 * ## Core Features
 * - **Multi-format Support**: JPEG, PNG, GIF, and WebP image formats
 * - **Quality Control**: Configurable compression for JPEG and WebP output
 * - **Memory Management**: Explicit resource cleanup and state management
 * - **Operation Logging**: Detailed activity log for debugging and monitoring
 * - **Fluent Interface**: Method chaining for streamlined image workflows
 * - **Safety First**: Extensive validation and graceful error handling
 *
 * ## Supported Operations
 * - **Loading/Saving**: Load images from disk, save in various formats
 * - **Resizing**: Proportional scaling by width or height
 * - **Format Conversion**: Convert between supported image formats
 * - **File Management**: Copy, move, and delete image files
 * - **Information Gathering**: Dimensions, aspect ratio, file size, MIME types
 * - **Output Options**: Direct browser output, base64 data URIs, binary blobs
 *
 * ## Usage Patterns
 * The class supports both single-operation and chained workflows:
 *
 * ### Single Operations
 * ```php
 * $helper = new ImageHelper('/uploads/');
 * $success = $helper->resizeToWidth('large.jpg', 'thumb.jpg', 'jpg', 150);
 * ```
 *
 * ### Chained Operations
 * ```php
 * $helper = new ImageHelper('/uploads/')
 *     ->setQuality(85)
 *     ->load('original.jpg')
 *     ->saveAs('compressed.jpg', 'jpg')
 *     ->clearImage();
 * ```
 *
 * ## Error Handling and Logging
 * All operations are logged internally and can be retrieved via getLog(). Errors
 * are handled gracefully - methods return false/null on failure rather than throwing
 * exceptions, making the class safe for web applications.
 *
 * ## Memory Safety
 * The class provides explicit memory management through clearImage() and reset()
 * methods. Large images should be cleared after processing to prevent memory leaks
 * in long-running applications.
 *
 * @since 1.0.0
 *
 * @example Basic Image Resizing
 * ```php
 * $helper = new ImageHelper('/var/www/images/');
 * if ($helper->resizeToWidth('photo.jpg', 'thumb.jpg', 'jpg', 200)) {
 *     echo "Thumbnail created successfully";
 * }
 * ```
 *
 * @example Format Conversion with Quality Control
 * ```php
 * $helper = new ImageHelper('/uploads/')
 *     ->setQuality(90)
 *     ->load('image.png')
 *     ->saveAs('converted.jpg', 'jpg');
 * ```
 *
 * @see https://www.php.net/manual/en/book.image.php GD Extension Documentation
 */
class ImageHelper
{
    /**
     * Base directory path for all image operations
     *
     * Stores the normalized base path where image files are located. All filenames
     * passed to methods are resolved relative to this path. The path is normalized
     * during construction to ensure consistent trailing slash handling.
     *
     * @var string Normalized directory path with trailing slash
     * @since 1.0.0
     */
    protected string $path;

    /**
     * Operation activity log for debugging and monitoring
     *
     * Maintains a chronological record of all operations performed by this instance,
     * including successful operations, errors, and state changes. Each entry is a
     * descriptive string message that can be retrieved via getLog() or displayed
     * via dumpLog().
     *
     * @var array<int, string> Array of log message strings
     * @since 1.0.0
     */
    protected array $log = [];

    /**
     * Image quality setting for lossy format compression
     *
     * Controls the compression quality for JPEG and WebP output formats. When null,
     * default quality values are used (90 for JPEG, 80 for WebP). Quality is
     * automatically clamped to 0-100 range when set via setQuality().
     *
     * @var int|null Quality percentage (0-100) or null for format defaults
     * @since 1.0.0
     */
    protected ?int $quality = null;

    /**
     * Current loaded image resource for manipulation
     *
     * Holds the active GD image resource for processing operations. When null,
     * no image is currently loaded. Resources should be explicitly freed using
     * clearImage() or reset() to prevent memory leaks, especially when processing
     * multiple large images.
     *
     * @var GdImage|null Active image resource or null if no image loaded
     * @since 1.0.0
     */
    protected ?GdImage $img = null;

    /**
     * Initialize ImageHelper with base directory path for image operations
     *
     * Sets up the image helper instance with a specified base directory where all
     * image operations will be performed. The path is normalized to ensure consistent
     * behavior across different input formats.
     *
     * ## Path Normalization
     * - Trailing slashes are removed and a single slash is appended
     * - Relative and absolute paths are both supported
     * - Path is stored for use in all subsequent file operations
     *
     * @param string $path Base directory path for image file operations
     *
     * @since 1.0.0
     *
     * @example Initialize with Upload Directory
     * ```php
     * $helper = new ImageHelper('/var/www/uploads/images');
     * // Now all operations use /var/www/uploads/images/ as base path
     * ```
     */
    public function __construct(string $path)
    {
        $this->path = rtrim($path, '/') . 'ImageHelper.php/';
        $this->log[] = "Initialized imageHelper with path: " . $this->path;
    }

    /**
     * Configure compression quality for JPEG and WebP output formats
     *
     * Sets the quality level used when saving images in lossy compression formats.
     * Quality is automatically clamped to the valid 0-100 range to prevent invalid
     * values. This setting affects all subsequent save operations until changed.
     *
     * ## Quality Guidelines
     * - **90-100**: Highest quality, larger file sizes
     * - **70-90**: Good balance of quality and size (recommended)
     * - **50-70**: Moderate compression, smaller files
     * - **0-50**: High compression, significant quality loss
     *
     * @param int $percent Quality percentage, automatically clamped to 0-100 range
     * @return self Returns this instance for method chaining
     *
     * @since 1.0.0
     *
     * @example Set High Quality for Important Images
     * ```php
     * $helper->setQuality(95)
     *     ->load('photo.jpg')
     *     ->saveAs('high_quality.jpg', 'jpg');
     * ```
     */
    public function setQuality(int $percent): self
    {
        $this->quality = max(0, min(100, $percent));
        $this->log[] = "Set quality to {". $this->quality;
        return $this;
    }

    /**
     * Load an image file from disk into memory for processing
     *
     * Loads the specified image file using the appropriate GD function based on
     * file extension or explicit type parameter. The loaded image becomes the
     * active resource for subsequent operations like resizing or saving.
     *
     * ## Supported Formats
     * - **JPEG**: .jpg, .jpeg files via imagecreatefromjpeg()
     * - **PNG**: .png files via imagecreatefrompng()
     * - **GIF**: .gif files via imagecreatefromgif()
     * - **WebP**: .webp files via imagecreatefromwebp() (if available)
     *
     * ## Error Handling
     * If the file doesn't exist or cannot be loaded, the operation is logged
     * as an error and the method returns safely without throwing exceptions.
     *
     * @param string $filename Image filename relative to base path
     * @param string|null $type Optional format override ('jpg', 'png', 'gif', 'webp')
     * @return self Returns this instance for method chaining
     *
     * @since 1.0.0
     *
     * @example Load Image with Auto-Detection
     * ```php
     * $helper->load('photo.jpg'); // Format detected from extension
     * ```
     *
     * @example Load with Explicit Type
     * ```php
     * $helper->load('image_without_extension', 'png');
     * ```
     *
     * @see self::hasImage() Check if image loaded successfully
     */
    public function load(string $filename, string $type = null): self
    {
        $file = $this->path . $filename;
        if (!file_exists($file)) {
            $this->log[] = "ERROR: File does not exist: " . $file;
            return $this;
        }

        $type = $type ?? pathinfo($file, PATHINFO_EXTENSION);
        $type = strtolower($type);

        $this->img = match ($type) {
            'jpg', 'jpeg' => imagecreatefromjpeg($file),
            'png'         => imagecreatefrompng($file),
            'gif'         => imagecreatefromgif($file),
            'webp'        => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($file) : false,
            default       => false,
        };

        $this->log[] = $this->img ? "Loaded image: $file" : "ERROR: Failed to load image: $file";
        return $this;
    }

    /**
     * Save the currently loaded image to disk in specified format
     *
     * Exports the active image resource to a file using the specified format.
     * Uses appropriate quality settings for lossy formats (JPEG/WebP) and
     * provides detailed logging of the operation result.
     *
     * ## Format Support
     * - **JPEG**: Uses quality setting (default 90) via imagejpeg()
     * - **PNG**: Lossless compression via imagepng()
     * - **GIF**: Lossless with palette via imagegif()
     * - **WebP**: Uses quality setting (default 80) via imagewebp()
     *
     * ## Return Behavior
     * Returns true only when both the GD save operation succeeds AND the
     * target file exists on disk, providing reliable success confirmation.
     *
     * @param string $filename Target filename relative to base path
     * @param string $type Output format ('jpg', 'png', 'gif', 'webp')
     * @return bool True if image saved successfully, false on failure
     *
     * @since 1.0.0
     *
     * @example Save Loaded Image as JPEG
     * ```php
     * if ($helper->load('source.png')->save('converted.jpg', 'jpg')) {
     *     echo "Conversion successful";
     * }
     * ```
     *
     * @see self::saveAs() Fluent interface version for method chaining
     * @see self::setQuality() Configure compression quality before saving
     */
    public function save(string $filename, string $type): bool
    {
        if (!$this->img) {
            $this->log[] = "ERROR: No image loaded to save.";
            return false;
        }

        $target = $this->path . $filename;
        $type = strtolower($type);

        $result = match ($type) {
            'jpg', 'jpeg' => imagejpeg($this->img, $target, $this->quality ?? 90),
            'png'         => imagepng($this->img, $target),
            'gif'         => imagegif($this->img, $target),
            'webp'        => function_exists('imagewebp') && imagewebp($this->img, $target, $this->quality ?? 80),
            default       => false,
        };

        $exists = file_exists($target);
        $this->log[] = ($result && $exists)
            ? ("Saved image to: " . $target)
            : ("ERROR: Failed to save image to: " . $target);

        return $result && $exists;
    }

    /**
     * Save the currently loaded image and return instance for method chaining
     *
     * Identical functionality to save() but returns the instance instead of
     * a boolean result, enabling fluent interface patterns. Use when you need
     * to continue chaining operations after saving.
     *
     * @param string $filename Target filename relative to base path
     * @param string $type Output format ('jpg', 'png', 'gif', 'webp')
     * @return self Returns this instance for method chaining
     *
     * @since 1.0.0
     *
     * @example Chained Save Operations
     * ```php
     * $helper->load('original.jpg')
     *     ->saveAs('copy1.jpg', 'jpg')
     *     ->saveAs('copy2.png', 'png')
     *     ->clearImage();
     * ```
     *
     * @see self::save() Return boolean result instead of chaining
     */
    public function saveAs(string $filename, string $type): self
    {
        $this->save($filename, $type);
        return $this;
    }

    /**
     * Resize image to specified width while maintaining aspect ratio
     *
     * Loads the source image, calculates proportional height based on the target
     * width, performs the resize operation, and saves the result. The aspect ratio
     * is preserved by calculating the new height automatically.
     *
     * ## Resize Algorithm
     * 1. Load source image using specified or detected format
     * 2. Calculate new height: `originalHeight * (targetWidth / originalWidth)`
     * 3. Use imagescale() for high-quality resampling
     * 4. Save result in specified format
     *
     * ## Performance Notes
     * This method loads, processes, and saves in a single operation. For multiple
     * operations on the same source image, consider using load() once followed
     * by multiple processing steps.
     *
     * @param string $originalFile Source image filename
     * @param string $newFile Target filename for resized image
     * @param string $type Output format (default: 'jpg')
     * @param int $width Target width in pixels (default: 200)
     * @return bool True if resize and save successful, false on failure
     *
     * @since 1.0.0
     *
     * @example Create Thumbnail
     * ```php
     * $success = $helper->resizeToWidth('large_photo.jpg', 'thumbnail.jpg', 'jpg', 150);
     * ```
     *
     * @see self::resizeToHeight() Resize by height instead of width
     */
    public function resizeToWidth(string $originalFile, string $newFile, string $type = 'jpg', int $width = 200): bool
    {
        $this->load($originalFile, $type);
        if (!$this->img) return false;

        $originalWidth = imagesx($this->img);
        $originalHeight = imagesy($this->img);
        $newHeight = (int)($originalHeight * ($width / $originalWidth));

        $this->img = imagescale($this->img, $width, $newHeight);
        return $this->save($newFile, $type);
    }

    /**
     * Resize image to specified height while maintaining aspect ratio
     *
     * Similar to resizeToWidth() but constrains the height dimension instead.
     * Automatically calculates the proportional width to maintain the original
     * aspect ratio of the image.
     *
     * ## Resize Algorithm
     * 1. Load source image using specified or detected format
     * 2. Calculate new width: `originalWidth * (targetHeight / originalHeight)`
     * 3. Use imagescale() for high-quality resampling
     * 4. Save result in specified format
     *
     * @param string $originalFile Source image filename
     * @param string $newFile Target filename for resized image
     * @param string $type Output format (default: 'jpg')
     * @param int $height Target height in pixels (default: 200)
     * @return bool True if resize and save successful, false on failure
     *
     * @since 1.0.0
     *
     * @example Create Fixed Height Thumbnail
     * ```php
     * $success = $helper->resizeToHeight('portrait.jpg', 'thumb.jpg', 'jpg', 100);
     * ```
     *
     * @see self::resizeToWidth() Resize by width instead of height
     */
    public function resizeToHeight(string $originalFile, string $newFile, string $type = 'jpg', int $height = 200): bool
    {
        $this->load($originalFile, $type);
        if (!$this->img) return false;

        $originalWidth = imagesx($this->img);
        $originalHeight = imagesy($this->img);
        $newWidth = (int)($originalWidth * ($height / $originalHeight));

        $this->img = imagescale($this->img, $newWidth, $height);
        return $this->save($newFile, $type);
    }

    /**
     * Resize image to fit within specified dimensions while maintaining aspect ratio
     *
     * Scales the image so it fits entirely within the specified box dimensions.
     * The image will be as large as possible while fitting completely inside the box.
     * Aspect ratio is preserved - the image is not stretched or distorted.
     *
     * @param string $originalFile Source image filename relative to base path
     * @param string $newFile      Target filename for resized image
     * @param string $type         Output format (default: 'jpg')
     * @param int    $maxWidth     Maximum width in pixels (default: 200)
     * @param int    $maxHeight    Maximum height in pixels (default: 200)
     *
     * @return bool True if resize and save succeeded, false otherwise
     *
     * @example
     * // Fit image into 300x300 box
     * $helper->resizeToFitBox('photo.jpg', 'thumbnail.jpg', 'jpg', 300, 300);
     *
     * @since 1.0.0
     */
    public function resizeToFitBox(string $originalFile, string $newFile, string $type = 'jpg', int $maxWidth = 200, int $maxHeight = 200): bool
    {
        $this->load($originalFile, $type);
        if (!$this->img) return false;

        $originalWidth = imagesx($this->img);
        $originalHeight = imagesy($this->img);
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        $newWidth = (int)($originalWidth * $ratio);
        $newHeight = (int)($originalHeight * $ratio);

        $this->img = imagescale($this->img, $newWidth, $newHeight);
        return $this->save($newFile, $type);
    }

    /**
     * Convert image from one format to another
     *
     * Loads an image in its original format and saves it in the target format.
     * Useful for batch conversion or format standardization. Format detection
     * is automatic based on file extension.
     *
     * @param string $originalFile Source image filename relative to base path
     * @param string $newFile      Target filename for converted image
     * @param string $targetType   Target format ('jpg', 'png', 'gif', 'webp')
     *
     * @return bool True if conversion succeeded, false otherwise
     *
     * @example
     * $helper->convert('photo.png', 'photo.jpg', 'jpg');
     * $helper->convert('old.gif', 'new.webp', 'webp');
     *
     * @since 1.0.0
     */
    public function convert(string $originalFile, string $newFile, string $targetType): bool
    {
        $this->load($originalFile);
        return $this->img && $this->save($newFile, $targetType);
    }

    /**
     * Crop the current image to specified dimensions
     *
     * Extracts a rectangular region from the current image. Coordinates are
     * relative to the top-left corner (0,0). The image must be loaded first.
     *
     * @param int $x      X coordinate of crop area's top-left corner
     * @param int $y      Y coordinate of crop area's top-left corner
     * @param int $width  Width of crop area in pixels
     * @param int $height Height of crop area in pixels
     *
     * @return self Returns this instance for method chaining
     *
     * @example
     * $helper->load('photo.jpg')
     *        ->crop(100, 50, 400, 300)
     *        ->save('cropped.jpg', 'jpg');
     *
     * @since 1.0.0
     */
    public function crop(int $x, int $y, int $width, int $height): self
    {
        if ($this->img) {
            $this->img = imagecrop($this->img, ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height]);
            $this->log[] = "Cropped image to ($x, $y, $width, $height)";
        }
        return $this;
    }

    /**
     * Crop the current image to a square using the smaller dimension
     *
     * Creates a square image by cropping equally from opposite sides of the
     * longer dimension. The crop is centered, preserving the middle of the image.
     *
     * @return self Returns this instance for method chaining
     *
     * @example
     * $helper->load('rectangle.jpg')
     *        ->cropToSquare()
     *        ->save('square.jpg', 'jpg');
     *
     * @since 1.0.0
     */
    public function cropToSquare(): self
    {
        if ($this->img) {
            $w = imagesx($this->img);
            $h = imagesy($this->img);
            $size = min($w, $h);
            $x = (int)(($w - $size) / 2);
            $y = (int)(($h - $size) / 2);
            $this->crop($x, $y, $size, $size);
        }
        return $this;
    }

    /**
     * Rotate the current image by specified degrees
     *
     * Rotates the image clockwise by the specified angle. Positive values
     * rotate clockwise, negative values rotate counter-clockwise. The background
     * of exposed areas after rotation is black.
     *
     * @param int $degrees Rotation angle in degrees (positive = clockwise)
     *
     * @return self Returns this instance for method chaining
     *
     * @example
     * $helper->load('photo.jpg')
     *        ->rotate(90)  // 90 degrees clockwise
     *        ->save('rotated.jpg', 'jpg');
     *
     * @since 1.0.0
     */
    public function rotate(int $degrees): self
    {
        if ($this->img) {
            $this->img = imagerotate($this->img, $degrees, 0);
            $this->log[] = "Rotated image by $degrees degrees";
        }
        return $this;
    }

    /**
     * Flip the current image horizontally or vertically
     *
     * Mirrors the image along the specified axis. Horizontal flip creates
     * a mirror image (left becomes right), vertical flip inverts top and bottom.
     *
     * @param bool $horizontal True for horizontal flip, false for vertical (default: true)
     *
     * @return self Returns this instance for method chaining
     *
     * @example
     * $helper->load('photo.jpg')
     *        ->flip()        // Horizontal flip
     *        ->flip(false)   // Then vertical flip
     *        ->save('flipped.jpg', 'jpg');
     *
     * @since 1.0.0
     */
    public function flip(bool $horizontal = true): self
    {
        if ($this->img) {
            $mode = $horizontal ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL;
            imageflip($this->img, $mode);
            $this->log[] = "Flipped image " . ($horizontal ? "horizontally" : "vertically");
        }
        return $this;
    }

    /**
     * Load image from binary string data
     *
     * Creates an image resource from raw binary data, such as from a database
     * BLOB field or uploaded file content. Format is auto-detected from the
     * binary data headers.
     *
     * @param string $blob Binary image data
     *
     * @return self Returns this instance for method chaining
     *
     * @example
     * $binaryData = file_get_contents('php://input');
     * $helper->loadFromBlob($binaryData)
     *        ->save('uploaded.jpg', 'jpg');
     *
     * @since 1.0.0
     */
    public function loadFromBlob(string $blob): self
    {
        $img = imagecreatefromstring($blob);
        if ($img === false) {
            $this->log[] = "ERROR: Failed to create image from blob";
        } else {
            $this->img = $img;
            $this->log[] = "Loaded image from blob";
        }
        return $this;
    }

    /**
     * Convert current image to binary string
     *
     * Outputs the current image as binary data suitable for database storage,
     * API responses, or further processing. Format and quality settings apply.
     *
     * @param string $type Output format ('jpg', 'png', 'gif', 'webp')
     *
     * @return string|false Binary image data or false on failure
     *
     * @example
     * $blob = $helper->load('photo.jpg')
     *                ->cropToSquare()
     *                ->saveToBlob('png');
     *
     * @since 1.0.0
     */
    public function saveToBlob(string $type): string|false
    {
        if (!$this->img) {
            $this->log[] = "ERROR: No image loaded to convert to blob.";
            return false;
        }

        ob_start();
        $success = match (strtolower($type)) {
            'jpg', 'jpeg' => imagejpeg($this->img, null, $this->quality ?? 90),
            'png'         => imagepng($this->img),
            'gif'         => imagegif($this->img),
            'webp'        => function_exists('imagewebp') && imagewebp($this->img, null, $this->quality ?? 80),
            default       => false,
        };
        $blob = ob_get_clean();

        if ($success === false) {
            $this->log[] = "ERROR: Failed to write image to blob.";
            return false;
        }

        $this->log[] = "Converted image to blob";
        return $blob;
    }

    /**
     * Delete an image file from the filesystem
     *
     * Permanently removes the specified file from disk. Logs the operation
     * result. Returns true only if file existed and was successfully deleted.
     *
     * @param string $file Filename to delete relative to base path
     *
     * @return bool True if file was deleted, false if file didn't exist
     *
     * @example
     * if ($helper->delete('temp.jpg')) {
     *     echo "Temporary file removed";
     * }
     *
     * @since 1.0.0
     */
    public function delete(string $file): bool
    {
        $file = $this->path . $file;
        if (file_exists($file)) {
            unlink($file);
            $this->log[] = "Deleted file: " . $file;
            return true;
        }
        $this->log[] = "Delete skipped: file not found: " . $file;
        return false;
    }

    /**
     * Get width and height of an image file
     *
     * Reads image dimensions without loading the full image into memory.
     * Efficient for checking sizes before processing.
     *
     * @param string $file Image filename relative to base path
     *
     * @return array|null Array with 'width' and 'height' keys, or null on failure
     *
     * @example
     * $dims = $helper->getDimensions('photo.jpg');
     * echo "Size: {$dims['width']}x{$dims['height']}";
     *
     * @since 1.0.0
     */
    public function getDimensions(string $file): ?array
    {
        $file = $this->path . $file;
        if (!file_exists($file)) return null;
        $info = getimagesize($file);
        return $info ? ['width' => $info[0], 'height' => $info[1]] : null;
    }

    /**
     * Calculate aspect ratio of an image file
     *
     * Returns the width-to-height ratio as a floating point number.
     * Values > 1 indicate landscape orientation, < 1 indicate portrait.
     *
     * @param string $file Image filename relative to base path
     *
     * @return float|null Aspect ratio (width/height) or null on failure
     *
     * @example
     * $ratio = $helper->getAspectRatio('photo.jpg');
     * if ($ratio > 1.5) echo "Wide image";
     *
     * @since 1.0.0
     */
    public function getAspectRatio(string $file): ?float
    {
        $dim = $this->getDimensions($file);
        return $dim ? $dim['width'] / $dim['height'] : null;
    }

    /**
     * Get file size in bytes
     *
     * Returns the size of the image file on disk. Useful for checking
     * file sizes before upload or after compression.
     *
     * @param string $file Image filename relative to base path
     *
     * @return int|null File size in bytes or null if file doesn't exist
     *
     * @example
     * $bytes = $helper->getFileSize('photo.jpg');
     * $mb = round($bytes / 1048576, 2);
     *
     * @since 1.0.0
     */
    public function getFileSize(string $file): ?int
    {
        $file = $this->path . $file;
        return file_exists($file) ? filesize($file) : null;
    }

    /**
     * Get MIME type of an image file
     *
     * Detects the actual MIME type of the file based on its contents,
     * not the file extension. Useful for validation and HTTP headers.
     *
     * @param string $file Image filename relative to base path
     *
     * @return string|null MIME type (e.g., 'image/jpeg') or null if file doesn't exist
     *
     * @example
     * $mime = $helper->getMimeType('photo.jpg');
     * header("Content-Type: $mime");
     *
     * @since 1.0.0
     */
    public function getMimeType(string $file): ?string
    {
        $file = $this->path . $file;
        return file_exists($file) ? mime_content_type($file) : null;
    }

    /**
     * Check if a file is a valid image
     *
     * Validates that a file contains actual image data that can be processed.
     * More reliable than checking file extensions.
     *
     * @param string $file Image filename relative to base path
     *
     * @return bool True if file is a valid image, false otherwise
     *
     * @example
     * if ($helper->isValidImage('upload.tmp')) {
     *     $helper->convert('upload.tmp', 'saved.jpg', 'jpg');
     * }
     *
     * @since 1.0.0
     */
    public function isValidImage(string $file): bool
    {
        $file = $this->path . $file;
        return (bool) @getimagesize($file);
    }

    /**
     * Check if an image type is supported by this class
     *
     * Verifies whether the specified format can be processed.
     * Currently supports: jpg, jpeg, png, gif, webp.
     *
     * @param string $type Image type to check ('jpg', 'png', 'gif', 'webp')
     *
     * @return bool True if type is supported, false otherwise
     *
     * @example
     * if ($helper->isSupported('webp')) {
     *     $helper->convert('photo.jpg', 'photo.webp', 'webp');
     * }
     *
     * @since 1.0.0
     */
    public function isSupported(string $type): bool
    {
        return in_array(strtolower($type), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    }

    /**
     * Check if an image is currently loaded in memory
     *
     * Determines whether there is an active image resource available
     * for manipulation operations.
     *
     * @return bool True if image is loaded, false otherwise
     *
     * @example
     * if (!$helper->hasImage()) {
     *     $helper->load('default.jpg');
     * }
     *
     * @since 1.0.0
     */
    public function hasImage(): bool
    {
        return $this->img instanceof GdImage;
    }

    /**
     * Check if GD extension is available
     *
     * Verifies that the PHP GD library is installed and available.
     * ImageHelper requires GD for all image operations.
     *
     * @return bool True if GD extension is loaded, false otherwise
     *
     * @example
     * if (!$helper->hasGD()) {
     *     die('GD extension required');
     * }
     *
     * @since 1.0.0
     */
    public function hasGD(): bool
    {
        return function_exists('gd_info');
    }

    /**
     * Get the operation log array
     *
     * Returns all logged messages from operations performed since
     * initialization or last reset. Includes both success and error messages.
     *
     * @return array Sequential array of log message strings
     *
     * @example
     * $helper->load('photo.jpg')->rotate(90)->save('output.jpg', 'jpg');
     * foreach ($helper->getLog() as $message) {
     *     echo $message . "\n";
     * }
     *
     * @since 1.0.0
     */
    public function getLog(): array
    {
        return $this->log;
    }

    /**
     * Clear the current image from memory and destroy the resource
     *
     * Frees the memory used by the current image resource. Useful for
     * memory management when processing multiple images sequentially.
     *
     * @return self Returns this instance for method chaining
     *
     * @example
     * $helper->load('large.jpg')
     *        ->save('processed.jpg', 'jpg')
     *        ->clearImage(); // Free memory
     *
     * @since 1.0.0
     */
    public function clearImage(): self
    {
        if ($this->img instanceof GdImage) {
            imagedestroy($this->img);
            $this->log[] = "Cleared current image resource";
        }
        $this->img = null;
        return $this;
    }

    /**
     * Reset the ImageHelper to initial state
     *
     * Clears the current image, empties the log, and resets quality settings.
     * Returns the instance to a clean state as if newly constructed.
     *
     * @return self Returns this instance for method chaining
     *
     * @example
     * $helper->reset()->load('next-image.jpg');
     *
     * @since 1.0.0
     */
    public function reset(): self
    {
        $this->clearImage();
        $this->log[] = "Reset imageHelper state";
        $this->log = [];
        $this->quality = null;
        return $this;
    }

    /**
     * Get width of the current loaded image
     *
     * Returns the width in pixels of the image currently in memory.
     * Returns null if no image is loaded.
     *
     * @return int|null Width in pixels or null if no image loaded
     *
     * @example
     * $helper->load('photo.jpg');
     * echo "Width: " . $helper->getWidth() . "px";
     *
     * @since 1.0.0
     */
    public function getWidth(): ?int {
        return $this->img ? imagesx($this->img) : null;
    }

    /**
     * Get height of the current loaded image
     *
     * Returns the height in pixels of the image currently in memory.
     * Returns null if no image is loaded.
     *
     * @return int|null Height in pixels or null if no image loaded
     *
     * @example
     * $helper->load('photo.jpg');
     * echo "Height: " . $helper->getHeight() . "px";
     *
     * @since 1.0.0
     */
    public function getHeight(): ?int {
        return $this->img ? imagesy($this->img) : null;
    }

    /**
     * Convert current image to base64 data URI
     *
     * Creates a data URI suitable for embedding images directly in HTML,
     * CSS, or JavaScript. Includes the appropriate MIME type prefix.
     *
     * @param string $type Output format ('jpg', 'png', 'gif', 'webp')
     *
     * @return string|false Data URI string or false on failure
     *
     * @example
     * $dataUri = $helper->load('icon.png')->toDataUri('png');
     * echo "<img src='$dataUri' alt='Embedded'>";
     *
     * @since 1.0.0
     */
    public function toDataUri(string $type): string|false {
        $blob = $this->saveToBlob($type);
        return $blob !== false ? 'data:image/' . $type . ';base64,' . base64_encode($blob) : false;
    }

    /**
     * Output the operation log to the browser or console
     *
     * Displays all logged operations for debugging purposes.
     * Can output as plain text or HTML formatted list.
     *
     * @param bool $html True for HTML formatted output, false for plain text (default: false)
     *
     * @return void
     *
     * @example
     * // Plain text output
     * $helper->dumpLog();
     *
     * // HTML formatted
     * $helper->dumpLog(true);
     *
     * @since 1.0.0
     */
    public function dumpLog(bool $html = false): void
    {
        if ($html) {
            echo "<ul>\n";
            foreach ($this->log as $line) {
                echo "<li>" . htmlspecialchars($line) . "</li>\n";
            }
            echo "</ul>\n";
        } else {
            foreach ($this->log as $line) {
                echo $line . PHP_EOL;
            }
        }
    }

    /**
     * Get file extension for a given MIME type
     *
     * Maps MIME types to their conventional file extensions.
     * Useful for generating appropriate filenames when saving.
     *
     * @param string $mime MIME type string (e.g., 'image/jpeg')
     *
     * @return string|null File extension ('jpg', 'png', etc.) or null if not recognized
     *
     * @example
     * $ext = $helper->getExtensionForMime('image/png'); // Returns 'png'
     *
     * @since 1.0.0
     */
    public function getExtensionForMime(string $mime): ?string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => null,
        };
    }

    /**
     * Output current image directly to browser with appropriate headers
     *
     * Sends the image directly to the browser with the correct Content-Type header.
     * Useful for dynamic image generation scripts. The script should not output
     * anything else before or after this method.
     *
     * @param string $type Output format ('jpg', 'png', 'gif', 'webp')
     *
     * @return void
     *
     * @example
     * $helper->load('photo.jpg')
     *        ->cropToSquare()
     *        ->output('jpg');  // Sends image directly to browser
     * exit;
     *
     * @since 1.0.0
     */
    public function output(string $type): void
    {
        if (!$this->img) return;
        header('Content-Type: image/' . $type);
        match (strtolower($type)) {
            'jpg', 'jpeg' => imagejpeg($this->img, null, $this->quality ?? 90),
            'png'         => imagepng($this->img),
            'gif'         => imagegif($this->img),
            'webp'        => function_exists('imagewebp') ? imagewebp($this->img, null, $this->quality ?? 80) : null,
            default       => null,
        };
    }

}