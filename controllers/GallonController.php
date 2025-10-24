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

            if (!$uploadedImagePath) {
                return [
                    'success' => false,
                    'message' => 'Failed to upload image'
                ];
            }
        }

        // ✅ Prepare data for insertion
        $data = [
            'name' => $request['name'] ?? '',
            'image_url' => $uploadedImagePath ?? ($request['image_url'] ?? null),
            'size' => $request['size'] ?? '',
            'price' => isset($request['price']) ? (float)$request['price'] : 0
        ];

        $result = $this->gallonModel->create($data);

        // ✅ Return the full image URL in the response
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
            // ✅ Return the full URL path, not just filename
            return '/images/' . $filename;
        }

        return false;
    }
    public function updateGallon($request, $userId, $id)
    {

        $updateData = [
            'name'      => $request['name'] ?? null,
            'image_url' => $request['image_url'] ?? null,
            'size'      => $request['size'] ?? null,
            'price'     => isset($request['price']) ? (float)$request['price'] : null
        ];

        return $this->gallonModel->update((int)$id, $updateData);
    }

    public function deleteGallon($request, $id)
    {
        return $this->gallonModel->delete($id);
    }
}
