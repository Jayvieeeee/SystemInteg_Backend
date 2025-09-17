<?php

class OrderController
{
    private $orderModel;
    private $addressModel;

    public function __construct(OrderModel $orderModel, AddressModel $addressModel)
    {
        $this->orderModel = $orderModel;
        $this->addressModel = $addressModel;
    }

    // -------------------------
    // CREATE ORDER
    // -------------------------
    public function createOrder($request)
    {
        $data = $request; // $request contains input + injected userId
        $userId = $request['userId'] ?? null;

        if (!$userId) {
            throw new AuthException("Unauthorized: missing userId");
        }

        if (empty($data['pickup_datetime']) || empty($data['payment_method'])) {
            throw new ValidationException("Missing required fields: pickup_datetime, payment_method", 422);
        }

        // Validate address
        $addressId = null;
        $addressDetails = null;
        if (!empty($data['address_id'])) {
            $address = $this->addressModel->getAddressById($data['address_id'], $userId);
            if (!$address) {
                throw new ValidationException("Address not found or doesn't belong to user", 422);
            }
            $addressId = $data['address_id'];
            $addressDetails = $address;
        } else {
            $address = $this->addressModel->getDefaultAddress($userId);
            if (!$address) {
                throw new ValidationException("No address found. Please add an address first.", 422);
            }
            $addressId = $address['address_id'];
            $addressDetails = $address;
        }

        // -------------------------
        // CASE 1: Direct order
        // -------------------------
        if (!empty($data['items']) && is_array($data['items'])) {
            $totalPrice = 0;
            foreach ($data['items'] as &$item) {
                if (empty($item['gallon_id']) || empty($item['quantity'])) {
                    throw new ValidationException("Each item must have gallon_id and quantity", 422);
                }

                $gallon = $this->orderModel->getGallonById($item['gallon_id']);
                if (!$gallon) {
                    throw new ValidationException("Invalid gallon_id: {$item['gallon_id']}", 422);
                }

                $quantity = filter_var($item['quantity'], FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1]
                ]);
                if ($quantity === false) {
                    throw new ValidationException("Quantity must be a positive integer", 422);
                }

                $item['price'] = $gallon['price'];
                $item['total_price'] = $quantity * $gallon['price'];
                $totalPrice += $item['total_price'];
            }

            $orderData = [
                'user_id' => $userId,
                'address_id' => $addressId,
                'pickup_datetime' => $data['pickup_datetime'],
                'payment_method' => $data['payment_method'],
                'total_price' => $totalPrice,
                'items' => $data['items']
            ];

            $orderId = $this->orderModel->create($orderData);
            $items = $data['items'];

            // -------------------------
            // CASE 2: Cart checkout
            // -------------------------
        } elseif (!empty($data['cart_items']) && is_array($data['cart_items'])) {
            $cartItemIds = implode(',', array_map('intval', $data['cart_items']));
            $cartItems = $this->orderModel->getSelectedCartItems($userId, $cartItemIds);

            if (!$cartItems) {
                throw new ValidationException("Selected cart items not found", 422);
            }

            $totalPrice = array_sum(array_column($cartItems, 'total_price'));

            $orderData = [
                'user_id' => $userId,
                'address_id' => $addressId,
                'pickup_datetime' => $data['pickup_datetime'],
                'payment_method' => $data['payment_method'],
                'total_price' => $totalPrice
            ];

            $orderId = $this->orderModel->createFromCart($userId, $cartItems, $orderData);
            $items = $cartItems;
        } else {
            throw new ValidationException("Order must include items or cart_items", 422);
        }

        return [
            'success' => true,
            'message' => 'Order placed successfully',
            'order_id' => $orderId,
            'total_price' => $totalPrice,
            'order_summary' => [
                'pickup_datetime' => $data['pickup_datetime'],
                'payment_method' => $data['payment_method'],
                'address' => [
                    'label' => $addressDetails['label'],
                    'address' => $addressDetails['address']
                ],
                'items' => $items
            ]
        ];
    }

    // -------------------------
    // GET SINGLE ORDER
    // -------------------------
    public function getOrder($request)
    {
        $userId = $request['userId'] ?? null;
        $orderId = $request['order_id'] ?? null;

        if (!$userId) {
            throw new AuthException("Unauthorized: missing userId");
        }
        if (!$orderId) {
            throw new ValidationException("Missing order_id", 422);
        }

        $order = $this->orderModel->getById($orderId);
        if (!$order) {
            throw new NotFoundException("Order not found", 404);
        }
        if ($order['user_id'] != $userId) {
            throw new ValidationException("You are not authorized to view this order", 403);
        }

        $order['items'] = $this->orderModel->getOrderItems($orderId);

        return [
            'success' => true,
            'order' => $order
        ];
    }

    // -------------------------
    // GET USER ORDERS
    // -------------------------
    public function getUserOrders($request)
    {
        $userId = $request['userId'] ?? null;
        if (!$userId) {
            throw new AuthException("Unauthorized: missing userId");
        }

        $orders = $this->orderModel->getOrdersByUserId($userId);
        foreach ($orders as &$order) {
            $order['items'] = $this->orderModel->getOrderItems($order['order_id']);
        }

        return [
            'success' => true,
            'orders' => $orders,
            'count' => count($orders)
        ];
    }
}
