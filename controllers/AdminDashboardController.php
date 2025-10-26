<?php

class AdminDashboardController
{
    private $orderModel;

    public function __construct(OrderModel $orderModel)
    {
        $this->orderModel = $orderModel;
    }

    public function getDashboardStats($userId)
    {
        if (!$userId) {
            throw new AuthException("Unauthorized: missing user_id");
        }
        return $this->getAdminDashboard();
    }

    private function getAdminDashboard()
    {
        $stats = $this->orderModel->getOrderStats();
        $salesRevenue = $this->orderModel->getSalesRevenue();
        $recentOrders = $this->orderModel->getLatestOrders(5);
        $allOrders = $this->orderModel->getAllOrders();
        $totalCustomers = $this->orderModel->getTotalCustomers();
        $ordersToPickup = $this->orderModel->getOrdersToPickup();
        $ordersToDeliver = $this->orderModel->getOrdersToDeliver();

        return [
            'success' => true,
            'data' => [
                'stats' => [
                    'completed_today' => $stats['completed_today'] ?? 0,
                    'total_sales_today' => (float) ($salesRevenue['today'] ?? 0),
                    'pending_orders' => $stats['pending'] ?? 0,
                    'total_customers' => $totalCustomers
                ],
                'sales_revenue' => [
                    'daily' => $salesRevenue['daily'] ?? [],
                    'weekly' => $salesRevenue['weekly'] ?? [],
                    'monthly' => $salesRevenue['monthly'] ?? []
                ],
                'order_status_distribution' => [
                    'pending' => $stats['pending'] ?? 0,
                    'preparing' => $stats['preparing'] ?? 0,
                    'completed' => $stats['completed'] ?? 0
                ],
                'recent_orders' => $recentOrders,  // Latest 10
                'all_orders' => $allOrders,        // ALL orders from database
                'orders_to_pickup' => $ordersToPickup,
                'orders_to_deliver' => $ordersToDeliver
            ]
        ];
    }
}
