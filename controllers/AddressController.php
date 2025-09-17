<?php
class AddressController
{
    private $addressModel;

    public function __construct(AddressModel $addressModel)
    {
        $this->addressModel = $addressModel;
    }

    public function getUserAddresses($request, $userId)
    {
        $addresses = $this->addressModel->getUserAddresses($userId);

        return [
            'success' => true,
            'addresses' => $addresses,
            'count' => count($addresses)
        ];
    }

    public function getAddress($request, $id, $userId)
    {
        $address = $this->addressModel->getAddressById($id, $userId);

        if (!$address) {
            throw new NotFoundException("Address not found", 404);
        }

        return [
            'success' => true,
            'address' => $address
        ];
    }

    public function createAddress($data, $userId)
    {
        // Validate required fields
        $requiredFields = ['label', 'address', 'city', 'state', 'zip_code'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Missing required field: $field", 422);
            }
        }

        // Validate label
        $validLabels = ['home', 'office', 'other'];
        if (!in_array($data['label'], $validLabels)) {
            throw new ValidationException("Label must be one of: " . implode(', ', $validLabels), 422);
        }

        $addressData = [
            'user_id' => $userId,
            'label' => $data['label'],
            'address' => $data['address'],
            'city' => $data['city'],
            'state' => $data['state'],
            'zip_code' => $data['zip_code'],
            'is_default' => !empty($data['is_default']) ? 1 : 0
        ];

        $addressId = $this->addressModel->create($addressData);

        return [
            'success' => true,
            'message' => 'Address created successfully',
            'address_id' => $addressId
        ];
    }

    public function updateAddress($data, $id, $userId)
    {
        // Check if address exists and belongs to user
        $address = $this->addressModel->getAddressById($id, $userId);
        if (!$address) {
            throw new NotFoundException("Address not found", 404);
        }

        // Validate label if provided
        if (!empty($data['label'])) {
            $validLabels = ['home', 'office', 'other'];
            if (!in_array($data['label'], $validLabels)) {
                throw new ValidationException("Label must be one of: " . implode(', ', $validLabels), 422);
            }
        }

        $updateData = [
            'label' => $data['label'] ?? $address['label'],
            'address' => $data['address'] ?? $address['address'],
            'city' => $data['city'] ?? $address['city'],
            'state' => $data['state'] ?? $address['state'],
            'zip_code' => $data['zip_code'] ?? $address['zip_code'],
            'is_default' => isset($data['is_default']) ? ($data['is_default'] ? 1 : 0) : $address['is_default']
        ];

        $result = $this->addressModel->update($id, $updateData, $userId);

        if ($result) {
            return [
                'success' => true,
                'message' => 'Address updated successfully'
            ];
        } else {
            throw new Exception("Failed to update address");
        }
    }

    public function deleteAddress($request, $id, $userId)
    {
        // Check if address exists and belongs to user
        $address = $this->addressModel->getAddressById($id, $userId);
        if (!$address) {
            throw new NotFoundException("Address not found", 404);
        }

        $result = $this->addressModel->delete($id, $userId);

        if ($result) {
            return [
                'success' => true,
                'message' => 'Address deleted successfully'
            ];
        } else {
            throw new Exception("Failed to delete address");
        }
    }

    public function setDefaultAddress($request, $id, $userId)
    {
        // Check if address exists and belongs to user
        $address = $this->addressModel->getAddressById($id, $userId);
        if (!$address) {
            throw new NotFoundException("Address not found", 404);
        }

        $result = $this->addressModel->setDefaultAddress($id, $userId);

        if ($result) {
            return [
                'success' => true,
                'message' => 'Default address set successfully'
            ];
        } else {
            throw new Exception("Failed to set default address");
        }
    }
}
