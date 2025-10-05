<?php
class ImageController
{
    public function serveImage($filename)
    {
        // The AuthMiddleware has already validated the user at this point
        // You can access user info from the request if needed, but for basic
        // image serving, you don't need to do anything extra

        $imagePath = __DIR__ . '/../images/' . $filename;

        // Check if file exists
        if (!file_exists($imagePath)) {
            header("HTTP/1.0 404 Not Found");
            exit;
        }

        // Get file extension and set appropriate content type
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $contentTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];

        // Set image headers
        header('Content-Type: ' . ($contentTypes[$extension] ?? 'image/jpeg'));
        header('Content-Length: ' . filesize($imagePath));

        // For better performance, add cache headers
        header('Cache-Control: public, max-age=86400'); // Cache for 1 day

        // Output the image file
        readfile($imagePath);
        exit;
    }
}
