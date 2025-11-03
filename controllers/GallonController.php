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
            $uploadedImagePath = $this->handleImageUpload($request['_files']['image']);
        }

        // ✅ Always prefer uploaded image (not client-provided URL)
        $data = [
            'name'  => $request['name'] ?? '',
            'image_url' => $uploadedImagePath, // ✅ Only use uploaded path
            'size'  => $request['size'] ?? '',
            'price' => isset($request['price']) ? (float)$request['price'] : 0
        ];

        $result = $this->gallonModel->create($data);

        if ($result && isset($result['gallon_id'])) {
            $result['image_url'] = $uploadedImagePath;
        }

        return $result;
    }

    private function handleImageUpload($file)
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowedTypes)) {
            return false;
        }

        if ($file['size'] > $maxSize) {
            return false;
        }

        $uploadDir = __DIR__ . '/../images/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('gallon_') . '.' . $extension;
        $uploadPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return '/images/' . $filename;
        }

        return false;
    }

    public function updateGallon($request, $userId, $id)
    {
        $uploadedImagePath = null;
        if (isset($request['_files']['image']) && $request['_files']['image']['error'] === UPLOAD_ERR_OK) {
            $uploadedImagePath = $this->handleImageUpload($request['_files']['image']);

            $existingGallon = $this->gallonModel->getById($id);
            if ($existingGallon && !empty($existingGallon['image_url'])) {
                $oldImagePath = __DIR__ . '/../' . $existingGallon['image_url'];
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
        }

        $updateData = [];

        // Always include these fields if they exist in request
        if (isset($request['name'])) {
            $updateData['name'] = $request['name'];
        }
        if (isset($request['size'])) {
            $updateData['size'] = $request['size'];
        }
        if (isset($request['price'])) {
            $updateData['price'] = (float)$request['price'];
        }

        // Handle image URL - always include if we have a new image or explicit image_url
        if ($uploadedImagePath) {
            $updateData['image_url'] = $uploadedImagePath;
        } elseif (isset($request['image_url']) && $request['image_url'] !== '') {
            $updateData['image_url'] = $request['image_url'];
        }

        if (empty($updateData)) {
            return [
                'success' => false,
                'message' => 'No fields to update'
            ];
        }

        // Debug log what we're updating
        error_log("Updating gallon $id with data: " . print_r($updateData, true));

        $result = $this->gallonModel->update((int)$id, $updateData);

        return [
            'success' => $result !== false,
            'message' => $result !== false ? 'Gallon updated successfully' : 'Failed to update gallon',
            'image_url' => $uploadedImagePath ?? ($request['image_url'] ?? null),
            'data' => $result
        ];
    }

    public function deleteGallon($request, $id)
    {
        return $this->gallonModel->delete($id);
    }
}
