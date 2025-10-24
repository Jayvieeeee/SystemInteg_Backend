<?php

class AdminRiderController
{
    private $userModel;
    private $orderModel;

    public function __construct(UserModel $userModel, OrderModel $orderModel)
    {
        $this->userModel = $userModel;
        $this->orderModel = $orderModel;
    }


    public function index()
    {
        $riders = $this->userModel->getAllByRole('rider');

        return [
            'error' => false,
            'riders' => $riders
        ];
    }

    public function show($request = [], $params = [])
    {
        if (!is_array($params)) {
            $params = ['id' => $params];
        } elseif (isset($params[0]) && !isset($params['id'])) {
            $params['id'] = $params[0];
        }

        $riderId = $params['id'] ?? null;

        if (!$riderId) {
            throw new ValidationException("Rider ID is required", 422);
        }

        $rider = $this->userModel->findById($riderId);

        if (!$rider || $rider['role'] !== 'rider') {
            throw new NotFoundException("Rider not found", 404);
        }

        $deliveryHistory = $this->orderModel->getDeliveriesByRiderId($riderId);

        return [
            'error' => false,
            'rider' => [
                'user_id'    => $rider['user_id'],
                'name'       => $rider['first_name'] . ' ' . $rider['last_name'],
                'contact_no' => $rider['contact_no'],
                'email'      => $rider['email'],
                'status'     => $rider['status'],
                'created_at' => $rider['created_at'],
            ],
            'delivery_history' => $deliveryHistory
        ];
    }



    public function updateRiderStatus($request = [], $userId = null, $riderId = null)
    {
        if (!$riderId) {
            throw new ValidationException("Rider ID is required", 422);
        }

        $rawInput = file_get_contents("php://input");
        $inputData = json_decode($rawInput, true);

        if (!$inputData || !isset($inputData['status'])) {
            throw new ValidationException("Status field is required", 422);
        }

        $newStatus = strtolower(trim($inputData['status']));

        if (!in_array($newStatus, ['active', 'inactive'])) {
            throw new ValidationException("Invalid status value", 422);
        }

        $rider = $this->userModel->findById($riderId);
        if (!$rider || $rider['role'] !== 'rider') {
            throw new NotFoundException("Rider not found", 404);
        }

        $updated = $this->userModel->updateRiderStatus($riderId, $newStatus);

        if (!$updated) {
            throw new AppException("Failed to update rider status", 500);
        }

        return [
            'error' => false,
            'message' => "Rider account has been " . ($newStatus === 'active' ? 'activated' : 'deactivated') . " successfully"
        ];
    }


    public function addRider($data)
    {
        // Check if email or username exists (active users only)
        if ($this->userModel->exists($data['email'], $data['username'])) {
            throw new ConflictException("Email or username already exists", 409);
        }

        // Check if contact number already exists (active users only)
        if ($this->userModel->contactExists($data['contact_no'])) {
            throw new ConflictException("Contact number already exists", 409);
        }

        // Hash password
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);

        // Set role as rider
        $data['role'] = 'rider';

        // Default status
        $data['status'] = 'inactive';

        // Create user
        $user = $this->userModel->create($data);

        return [
            "error" => false,
            "message" => "Rider account created successfully",
            "user" => $user
        ];
    }
}
