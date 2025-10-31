<?php
class ImageController
{
    public function serveImage($filename)
    {
        // ✅ FIXED: Changed DIR to _DIR_
        $imagePath = __DIR__ . '/../images/' . $filename;

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

        header('Content-Type: ' . ($contentTypes[$extension] ?? 'image/jpeg'));
        header('Content-Length: ' . filesize($imagePath));

        // For better performance, add cache headers
        header('Cache-Control: public, max-age=86400'); // Cache for 1 day

        readfile($imagePath);
        exit;
    }
}
