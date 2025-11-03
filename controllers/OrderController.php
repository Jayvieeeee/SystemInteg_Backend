<?php

class OrderController
{
    private $orderModel;
    private $addressModel;
    private $cartModel;

    public function __construct(OrderModel $orderModel, AddressModel $addressModel, CartModel $cartModel)
    {
        $this->orderModel = $orderModel;
        $this->addressModel = $addressModel;
        $this->cartModel = $cartModel;
    }

    // POST /orders
    public function createOrder($request, $userId)
    {
        $data = $request;

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

        $items = [];
        $totalPrice = 0;

        if (!empty($data['items']) && is_array($data['items'])) {
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

                $item['price'] = (float) $gallon['price'];
                $item['total_price'] = (float) ($quantity * $gallon['price']);
                $totalPrice += $item['total_price'];
                $items[] = $item;
            }

            $orderData = [
                'user_id' => $userId,
                'address_id' => $addressId,
                'pickup_datetime' => $data['pickup_datetime'],
                'payment_method' => $data['payment_method'],
                'total_price' => (float) $totalPrice,
                'items' => $items
            ];

            $orderId = $this->orderModel->create($orderData);
        } elseif (!empty($data['cart_items']) && is_array($data['cart_items'])) {
            $cartItemIds = implode(',', array_map('intval', $data['cart_items']));
            $cartItems = $this->orderModel->getSelectedCartItems($userId, $cartItemIds);

            if (!$cartItems) {
                throw new ValidationException("Selected cart items not found", 422);
            }

            foreach ($cartItems as &$cartItem) {
                $cartItem['price'] = (float) $cartItem['price'];
                $cartItem['total_price'] = (float) $cartItem['total_price'];
                $items[] = $cartItem;
            }

            $totalPrice = array_sum(array_column($items, 'total_price'));

            $orderData = [
                'user_id' => $userId,
                'address_id' => $addressId,
                'pickup_datetime' => $data['pickup_datetime'],
                'payment_method' => $data['payment_method'],
                'total_price' => (float) $totalPrice
            ];

            $orderId = $this->orderModel->createFromCart($userId, $items, $orderData);

            $this->cartModel->removeItems($userId, array_column($cartItems, 'cart_item_id'));
        } else {
            throw new ValidationException("Order must include items or cart_items", 422);
        }

        if ($data['payment_method'] == 'paypal') {
            $paypalService = new PayPalService();
            $paypalOrder = $paypalService->createOrder($orderId, $totalPrice);

            return [
                'success' => true,
                'message' => 'PayPal order created, proceed with payment',
                'order_id' => $orderId,
                'total_price' => (float) $totalPrice,
                'paypal' => [
                    'approve_link' => $paypalOrder['approve_link'],
                    'paypal_order_id' => $paypalOrder['id']
                ]
            ];
        }

        return [
            'success' => true,
            'message' => 'Order placed successfully',
            'order_id' => $orderId,
            'total_price' => (float) $totalPrice,
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

    // GET /orders/{id}
    public function getOrder($userId, $orderId)
    {
        $order = $this->orderModel->getById($orderId);
        if (!$order) {
            throw new NotFoundException("Order not found", 404);
        }
        if ($order['user_id'] != $userId) {
            throw new AuthException("You are not authorized to view this order", 403);
        }

        $order['total_price'] = (float) $order['total_price'];
        $order['items'] = $this->orderModel->getOrderItems($orderId);

        foreach ($order['items'] as &$item) {
            $item['price'] = (float) $item['price'];
            $item['total_price'] = (float) $item['total_price'];
        }

        return [
            'success' => true,
            'order' => $order
        ];
    }

    public function getUserOrders($userId)
    {
        $orders = $this->orderModel->getOrdersByUserId($userId);

        foreach ($orders as &$order) {
            $order['total_price'] = (float) $order['total_price'];
            $order['items'] = $this->orderModel->getOrderItems($order['order_id']);
            foreach ($order['items'] as &$item) {
                $item['price'] = (float) $item['price'];
                $item['total_price'] = (float) $item['total_price'];
            }
        }

        return [
            'success' => true,
            'orders' => $orders,
            'count' => count($orders)
        ];
    }

    public function getOrderStats($userId)
    {
        $stats = $this->orderModel->getOrderStatsByUser($userId);
        return [
            'success' => true,
            'data' => $stats
        ];
    }

    public function getLatestOrderByCustomer($userId)
    {
        if (!$userId) {
            throw new AuthException("Unauthorized: missing user_id");
        }

        $orders = $this->orderModel->getLatestOrdersByCustomer($userId, 5);

        return [
            'success' => true,
            'data' => $orders
        ];
    }
    public function cancelOrderCustomer($request, $userId, $orderId)
    {
        $result = $this->orderModel->cancelOrderCustomer($orderId, $userId);

        if (!$result['success']) {
            throw new ValidationException($result['message'], 422);
        }

        return [
            'success' => true,
            'message' => $result['message'],
            'order_id' => $orderId,
            'status' => 'cancelled'
        ];
    }
}
