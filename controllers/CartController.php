<?php
class CartController
{
    private $cartModel;

    public function __construct(CartModel $cartModel)
    {
        $this->cartModel = $cartModel;
    }

    public function getUserCart($request)
    {
        $userId = $request['user_id'] ?? null;

        if (!$userId) {
            return [
                'success' => false,
                'message' => 'User not authenticated',
                'cart_items' => []
            ];
        }

        $cartItems = $this->cartModel->getCartItems($userId);

        return [
            'success' => true,
            'cart_items' => $cartItems
        ];
    }

    // Add or update cart item
    public function addToCart($request)
    {
        $userId = $request['user_id'] ?? null;
        $gallonId = $request['gallon_id'] ?? null;
        $quantity = $request['quantity'] ?? 1;

        if (!$userId) throw new AuthException("Unauthorized: missing userId");
        if (!$gallonId) throw new ValidationException("Missing gallonId");

        $cartItems = $this->cartModel->addToCart($userId, $gallonId, $quantity);

        return [
            'success' => true,
            'message' => "Item added/updated in cart",
            'cart_items' => $cartItems
        ];
    }

    // DELETE CART ITEM - completely remove from cart (for delete button)
    public function removeFromCart($request)
    {
        $userId = $request['user_id'] ?? null;
        $cartItemIds = $request['cart_item_ids'] ?? [];

        if (!$userId) throw new AuthException("Unauthorized: missing userId");
        if (empty($cartItemIds)) throw new ValidationException("No cart_item_ids provided");

        $this->cartModel->removeItems($userId, $cartItemIds);

        $cartItems = $this->cartModel->getCartItems($userId);

        return [
            'success' => true,
            'message' => "Selected items removed from cart",
            'cart_items' => $cartItems
        ];
    }

    public function decreaseQuantity($request)
    {
        $userId = $request['user_id'] ?? null;
        $cartItemId = $request['cart_item_id'] ?? null;

        if (!$userId) throw new AuthException("Unauthorized: missing userId");
        if (!$cartItemId) throw new ValidationException("Missing cart_item_id");

        $result = $this->cartModel->decreaseQuantity($userId, $cartItemId);

        $cartItems = $this->cartModel->getCartItems($userId);

        return [
            'success' => true,
            'message' => $result ? "Quantity decreased" : "Item removed from cart",
            'cart_items' => $cartItems
        ];
    }

    public function increaseQuantity($request)
    {
        $userId = $request['user_id'] ?? null;
        $cartItemId = $request['cart_item_id'] ?? null;

        if (!$userId) throw new AuthException("Unauthorized: missing userId");
        if (!$cartItemId) throw new ValidationException("Missing cart_item_id");

        // Get current quantity first
        $cartItems = $this->cartModel->getCartItems($userId);
        $currentItem = null;

        foreach ($cartItems as $item) {
            if ($item['cart_item_id'] == $cartItemId) {
                $currentItem = $item;
                break;
            }
        }

        if (!$currentItem) {
            throw new ValidationException("Cart item not found");
        }

        $newQuantity = $currentItem['quantity'] + 1;

        // Update to new quantity
        $this->cartModel->updateQuantity($userId, $cartItemId, $newQuantity);

        $updatedCartItems = $this->cartModel->getCartItems($userId);

        return [
            'success' => true,
            'message' => 'Quantity increased',
            'cart_items' => $updatedCartItems
        ];
    }
}
