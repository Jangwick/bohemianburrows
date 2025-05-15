<?php
// This script creates a product placeholder image
$directory = __DIR__ . '/assets/images';

// Check if directory exists, if not create it
if (!file_exists($directory)) {
    mkdir($directory, 0755, true);
}

// Create a simple placeholder image
$width = 300;
$height = 300;
$image = imagecreatetruecolor($width, $height);

// Set background to light gray
$bg_color = imagecolorallocate($image, 240, 240, 240);
imagefilledrectangle($image, 0, 0, $width, $height, $bg_color);

// Add a border
$border_color = imagecolorallocate($image, 200, 200, 200);
imagerectangle($image, 0, 0, $width - 1, $height - 1, $border_color);

// Add text
$text_color = imagecolorallocate($image, 100, 100, 100);
$text = "No Image";
$font_size = 5; // Built-in font size (1-5)
$text_width = imagefontwidth($font_size) * strlen($text);
$text_height = imagefontheight($font_size);
$x = ($width - $text_width) / 2;
$y = ($height - $text_height) / 2;
imagestring($image, $font_size, $x, $y, $text, $text_color);

// Save the image
imagepng($image, $directory . '/product-placeholder.png');
imagedestroy($image);

echo "Placeholder image created successfully at: " . $directory . '/product-placeholder.png';
?>
