<?php

class GallonController
{
    private $gallonModel;

    public function __construct($gallonModel)
    {
        $this->gallonModel = $gallonModel;
    }

    public function getAllGallons()
    {
        return $this->gallonModel->getAll();
    }

    public function getGallon($id)
    {
        return $this->gallonModel->getById($id);
    }

    public function createGallon($request, $userId)
    {
        $uploadedImagePath = null;

        // ✅ Handle file upload if present
        if (isset($request['_files']['image']) && $request['_files']['image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = $this->handleImageUpload($request['_files']['image']);

            if ($uploadResult['success']) {
                $uploadedImagePath = $uploadResult['path'];
            } else {
                return [
                    'success' => false,
                    'message' => $uploadResult['message'] ?? 'Failed to upload image'
                ];
            }
        }

        // ✅ Validate required fields
        if (empty($request['name']) || empty($request['size']) || !isset($request['price'])) {
            return [
                'success' => false,
                'message' => 'Name, size, and price are required'
            ];
        }

        // ✅ Prepare data for insertion
        $data = [
            'name' => trim($request['name']),
            'image_url' => $uploadedImagePath ?? ($request['image_url'] ?? null),
            'size' => trim($request['size']),
            'price' => (float)$request['price']
        ];

        $result = $this->gallonModel->create($data);

        // ✅ Return the full image URL in the response
        if ($result && isset($result['gallon_id'])) {
            $result['image_url'] = $uploadedImagePath;
        }

        return $result;
    }

    public function updateGallon($request, $userId, $id)
    {
        $uploadedImagePath = null;

        // ✅ Handle file upload if present (NEW!)
        if (isset($request['_files']['image']) && $request['_files']['image']['error'] === UPLOAD_ERR_OK) {
            // Get existing gallon to delete old image
            $existingGallon = $this->gallonModel->getById($id);

            $uploadResult = $this->handleImageUpload($request['_files']['image']);

            if ($uploadResult['success']) {
                $uploadedImagePath = $uploadResult['path'];

                // Delete old image if exists
                if ($existingGallon && isset($existingGallon['image_url'])) {
                    $this->deleteImage($existingGallon['image_url']);
                }
            } else {
                return [
                    'success' => false,
                    'message' => $uploadResult['message'] ?? 'Failed to upload image'
                ];
            }
        }

        // ✅ Prepare update data (only include fields that are provided)
        $updateData = [];

        if (isset($request['name'])) {
            $updateData['name'] = trim($request['name']);
        }

        if ($uploadedImagePath) {
            $updateData['image_url'] = $uploadedImagePath;
        } elseif (isset($request['image_url'])) {
            $updateData['image_url'] = $request['image_url'];
        }

        if (isset($request['size'])) {
            $updateData['size'] = trim($request['size']);
        }

        if (isset($request['price'])) {
            $updateData['price'] = (float)$request['price'];
        }

        if (empty($updateData)) {
            return [
                'success' => false,
                'message' => 'No data to update'
            ];
        }

        return $this->gallonModel->update((int)$id, $updateData);
    }

    public function deleteGallon($request, $id)
    {
        // Get gallon data to delete associated image
        $gallon = $this->gallonModel->getById($id);

        if ($gallon && isset($gallon['image_url'])) {
            $this->deleteImage($gallon['image_url']);
        }

        return $this->gallonModel->delete($id);
    }

    /**
     * Handle image upload with proper validation and error handling
     * @return array ['success' => bool, 'path' => string|null, 'message' => string|null]
     */
    private function handleImageUpload($file)
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        // Validate file type
        if (!in_array($file['type'], $allowedTypes)) {
            return [
                'success' => false,
                'message' => 'Invalid file type. Only JPEG, PNG, and WEBP are allowed.'
            ];
        }

        // Validate file size
        if ($file['size'] > $maxSize) {
            return [
                'success' => false,
                'message' => 'File size too large. Maximum size is 5MB.'
            ];
        }

        $uploadDir = __DIR__ . '/../api/images/';

        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return [
                    'success' => false,
                    'message' => 'Failed to create upload directory.'
                ];
            }
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('gallon_', true) . '.' . strtolower($extension);
        $uploadPath = $uploadDir . $filename;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return [
                'success' => true,
                'path' => '/images/' . $filename
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to move uploaded file.'
        ];
    }

    /**
     * Delete image file from server
     */
    private function deleteImage($imagePath)
    {
        if (empty($imagePath)) {
            return false;
        }

        $filePath = __DIR__ . '/..' . $imagePath;

        if (file_exists($filePath) && is_file($filePath)) {
            return unlink($filePath);
        }

        return false;
    }
}
