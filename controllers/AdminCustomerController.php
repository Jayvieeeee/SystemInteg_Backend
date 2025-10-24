<?php

class AdminCustomerController
{
    private $userModel;
    private $addressModel;
    private $orderModel;

    public function __construct(UserModel $userModel, AddressModel $addressModel, OrderModel $orderModel)
    {
        $this->userModel = $userModel;
        $this->addressModel = $addressModel;
        $this->orderModel = $orderModel;
    }

    /**
     * GET /admin/customers
     * Display all customers with summary info
     * 
     * Arguments from index.php:
     * 1. $userId (from auth middleware)
     */
    public function index($userId)
    {
        $customers = $this->userModel->getAllCustomers();

        return [
            "error" => false,
            "total_customers" => count($customers),
            "data" => $customers
        ];
    }

    /**
     * GET /admin/customers/{id}
     * Show detailed customer info + saved addresses + order history
     * 
     * Arguments from index.php:
     * 1. $userId (from auth middleware)
     * 2. $id (from route parameter {id})
     */
    public function show($userId, $id)
    {
        $customer = $this->userModel->getCustomerById($id);
        if (!$customer) {
            throw new NotFoundException("Customer not found.", 404);
        }

        $addresses = $this->addressModel->getAddressesByCustomerId($id);
        $orders = $this->orderModel->getOrdersByCustomerId($id);

        return [
            "error" => false,
            "customer" => $customer,
            "saved_addresses" => $addresses,
            "order_history" => $orders
        ];
    }
}
