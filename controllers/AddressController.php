<?php
class AddressController
{
    private $addressModel;

    public function __construct(AddressModel $addressModel)
    {
        $this->addressModel = $addressModel;
    }

    public function getUserAddresses($userId)
    {
        error_log("JWT userId sa GET: " . $userId);
        $addresses = $this->addressModel->getUserAddresses($userId);

        return [
            'success' => true,
            'addresses' => $addresses,
            'count' => count($addresses)
        ];
    }


    public function getAddress($id, $userId)
    {

        error_log("Debug - Address ID received: " . $id);
        error_log("Debug - User ID from token: " . $userId);

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

        $requiredFields = ['label', 'address'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Missing required field: $field", 422);
            }
        }


        $label = trim($data['label']);
        if ($label === '') {
            throw new ValidationException("Label cannot be empty", 422);
        }


        $defaultAddress = $this->addressModel->getDefaultAddress($userId);
        $isDefault = !empty($data['is_default']) ? 1 : 0;


        if ($isDefault && $defaultAddress) {
            $this->addressModel->setDefaultAddress($defaultAddress['address_id'], $userId, false);
        }

        $addressData = [
            'user_id' => $userId,
            'label' => $label,
            'address' => $data['address'],
            'is_default' => $isDefault
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
        $address = $this->addressModel->getAddressById($id, $userId);
        if (!$address) {
            throw new NotFoundException("Address not found", 404);
        }

        if (isset($data['label']) && $data['label'] === '') {
            throw new ValidationException("Label cannot be empty", 422);
        }

        $updateData = [
            'label' => $data['label'] ?? $address['label'],
            'address' => $data['address'] ?? $address['address'],
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

    public function deleteAddress($id, $userId)
    {
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

    public function setDefaultAddress($id, $userId)
    {
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
