<?php
class ImageController
{
    public function serveImage($filename)
    {
        // ✅ Remove leading slash
        $filename = ltrim($filename, '/');

        // ✅ Remove 'images/' prefix if present
        if (strpos($filename, 'images/') === 0) {
            $filename = substr($filename, 7);
        }

        // ✅ If no extension, try to find the file with common extensions
        if (!pathinfo($filename, PATHINFO_EXTENSION)) {
            $extensions = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
            $baseDir = __DIR__ . '/../api/images/';

            foreach ($extensions as $ext) {
                $testPath = $baseDir . $filename . '.' . $ext;
                if (file_exists($testPath)) {
                    $filename = $filename . '.' . $ext;
                    break;
                }
            }
        }

        $imagePath = __DIR__ . '/../api/images/' . $filename;

        if (!file_exists($imagePath)) {
            header("HTTP/1.0 404 Not Found");
            echo json_encode(['error' => 'Image not found: ' . $filename]);
            exit;
        }

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
        header('Cache-Control: public, max-age=86400');

        readfile($imagePath);
        exit;
    }
}
