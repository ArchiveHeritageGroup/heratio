# ahgCartPlugin - Technical Documentation

## Overview

The ahgCartPlugin provides shopping cart and e-commerce functionality for AtoM, enabling both free reproduction requests and paid digital sales with PayFast payment integration.

**Version:** 2.0.0  
**Author:** The Archive and Heritage Group  
**Dependencies:** atom-framework (Laravel Query Builder)

---

## Architecture
```
┌─────────────────────────────────────────────────────────────────────────┐
│                        ahgCartPlugin Architecture                        │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │                         PRESENTATION LAYER                       │    │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │    │
│  │  │ browseAction│  │checkoutAction│ │ paymentAction│              │    │
│  │  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘              │    │
│  │         │                │                │                      │    │
│  │  ┌──────▼──────┐  ┌──────▼──────┐  ┌──────▼──────┐              │    │
│  │  │ browseSuccess│ │checkoutSuccess│ │paymentSuccess│             │    │
│  │  └─────────────┘  └─────────────┘  └─────────────┘              │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                    │                                     │
│                                    ▼                                     │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │                         SERVICE LAYER                            │    │
│  │  ┌──────────────────────┐  ┌──────────────────────┐             │    │
│  │  │    CartService       │  │  EcommerceService    │             │    │
│  │  │  - addToCart()       │  │  - getCartWithPricing│             │    │
│  │  │  - getCart()         │  │  - calculateTotals() │             │    │
│  │  │  - removeFromCart()  │  │  - createOrder()     │             │    │
│  │  │  - clearAll()        │  │  - initiatePayment() │             │    │
│  │  │  - mergeGuestCart()  │  │  - processNotify()   │             │    │
│  │  └──────────────────────┘  └──────────────────────┘             │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                    │                                     │
│                                    ▼                                     │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │                       REPOSITORY LAYER                           │    │
│  │  ┌──────────────────────┐  ┌──────────────────────┐             │    │
│  │  │  EcommerceRepository │  │  (Laravel Query      │             │    │
│  │  │  - getSettings()     │  │   Builder Direct)    │             │    │
│  │  │  - saveSettings()    │  │                      │             │    │
│  │  │  - getProductTypes() │  │                      │             │    │
│  │  │  - getPricing()      │  │                      │             │    │
│  │  │  - createOrder()     │  │                      │             │    │
│  │  └──────────────────────┘  └──────────────────────┘             │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                    │                                     │
│                                    ▼                                     │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │                        DATABASE LAYER                            │    │
│  │  ┌────────────┐ ┌────────────────────┐ ┌─────────────────┐      │    │
│  │  │   cart     │ │ahg_ecommerce_settings│ │   ahg_order    │      │    │
│  │  ├────────────┤ ├────────────────────┤ ├─────────────────┤      │    │
│  │  │id          │ │id                  │ │id               │      │    │
│  │  │user_id     │ │repository_id       │ │order_number     │      │    │
│  │  │session_id  │ │is_enabled          │ │user_id          │      │    │
│  │  │archival_   │ │currency            │ │session_id       │      │    │
│  │  │ description│ │vat_rate            │ │status           │      │    │
│  │  │product_type│ │payfast_*           │ │total            │      │    │
│  │  │quantity    │ │stripe_*            │ │customer_*       │      │    │
│  │  └────────────┘ └────────────────────┘ └─────────────────┘      │    │
│  │                                                                  │    │
│  │  ┌────────────────┐ ┌────────────────┐ ┌─────────────────┐      │    │
│  │  │ahg_product_type│ │ahg_product_    │ │ ahg_order_item │      │    │
│  │  │                │ │    pricing     │ │                 │      │    │
│  │  ├────────────────┤ ├────────────────┤ ├─────────────────┤      │    │
│  │  │id              │ │id              │ │id               │      │    │
│  │  │name            │ │product_type_id │ │order_id         │      │    │
│  │  │is_digital      │ │repository_id   │ │archival_desc_id │      │    │
│  │  │description     │ │price           │ │product_type_id  │      │    │
│  │  └────────────────┘ └────────────────┘ └─────────────────┘      │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Database Schema (ERD)
```
┌─────────────────────────────────────────────────────────────────────────┐
│                         E-COMMERCE DATABASE SCHEMA                       │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌──────────────────┐         ┌──────────────────────────┐              │
│  │      cart        │         │   ahg_ecommerce_settings │              │
│  ├──────────────────┤         ├──────────────────────────┤              │
│  │ id           PK  │         │ id                    PK │              │
│  │ user_id      FK ─┼────┐    │ repository_id         FK │──┐           │
│  │ session_id      │    │    │ is_enabled               │  │           │
│  │ archival_desc_id│    │    │ currency                 │  │           │
│  │ archival_desc   │    │    │ vat_rate                 │  │           │
│  │ slug            │    │    │ vat_number               │  │           │
│  │ product_type_id │─┐  │    │ payment_gateway          │  │           │
│  │ unit_price      │ │  │    │ payfast_merchant_id      │  │           │
│  │ quantity        │ │  │    │ payfast_merchant_key     │  │           │
│  │ completed_at    │ │  │    │ payfast_passphrase       │  │           │
│  │ created_at      │ │  │    │ payfast_sandbox          │  │           │
│  │ updated_at      │ │  │    │ admin_notification_email │  │           │
│  └──────────────────┘ │  │    └──────────────────────────┘  │           │
│                       │  │                                   │           │
│  ┌────────────────────┼──┼───────────────────────────────────┼───────┐  │
│  │                    │  │                                   │       │  │
│  │                    ▼  │                                   ▼       │  │
│  │  ┌──────────────────┐ │  ┌──────────────────┐  ┌────────────────┐│  │
│  │  │ahg_product_type  │ │  │      user        │  │   repository   ││  │
│  │  ├──────────────────┤ │  ├──────────────────┤  ├────────────────┤│  │
│  │  │ id            PK │ │  │ id            PK │  │ id          PK ││  │
│  │  │ name             │ │  │ username         │  │ ...            ││  │
│  │  │ description      │ │  │ email            │  └────────────────┘│  │
│  │  │ is_digital       │ │  └──────────────────┘                    │  │
│  │  │ is_active        │ │           ▲                              │  │
│  │  │ sort_order       │ │           │                              │  │
│  │  └────────┬─────────┘ │           │                              │  │
│  │           │           │           │                              │  │
│  │           ▼           │           │                              │  │
│  │  ┌──────────────────┐ │           │                              │  │
│  │  │ahg_product_pricing│ │           │                              │  │
│  │  ├──────────────────┤ │           │                              │  │
│  │  │ id            PK │ │           │                              │  │
│  │  │ product_type_id FK│◀┘           │                              │  │
│  │  │ repository_id  FK│─────────────┼──────────────────────────────┘  │
│  │  │ name             │             │                                 │
│  │  │ price            │             │                                 │
│  │  │ is_active        │             │                                 │
│  │  └──────────────────┘             │                                 │
│  │                                   │                                 │
│  │  ┌──────────────────┐             │                                 │
│  │  │    ahg_order     │             │                                 │
│  │  ├──────────────────┤             │                                 │
│  │  │ id            PK │             │                                 │
│  │  │ order_number  UK │             │                                 │
│  │  │ user_id       FK │─────────────┘                                 │
│  │  │ session_id       │  (for guest orders)                           │
│  │  │ repository_id FK │                                               │
│  │  │ status           │  (pending/paid/processing/completed/...)      │
│  │  │ subtotal         │                                               │
│  │  │ vat_amount       │                                               │
│  │  │ total            │                                               │
│  │  │ currency         │                                               │
│  │  │ customer_name    │                                               │
│  │  │ customer_email   │                                               │
│  │  │ customer_phone   │                                               │
│  │  │ billing_address  │                                               │
│  │  │ shipping_address │                                               │
│  │  │ paid_at          │                                               │
│  │  │ completed_at     │                                               │
│  │  │ created_at       │                                               │
│  │  └────────┬─────────┘                                               │
│  │           │                                                         │
│  │           │ 1:N                                                     │
│  │           ▼                                                         │
│  │  ┌──────────────────┐                                               │
│  │  │  ahg_order_item  │                                               │
│  │  ├──────────────────┤                                               │
│  │  │ id            PK │                                               │
│  │  │ order_id      FK │                                               │
│  │  │ archival_desc_id │                                               │
│  │  │ description      │                                               │
│  │  │ product_type_id  │                                               │
│  │  │ product_name     │                                               │
│  │  │ quantity         │                                               │
│  │  │ unit_price       │                                               │
│  │  │ total            │                                               │
│  │  └──────────────────┘                                               │
│  │                                                                     │
│  │  ┌──────────────────┐                                               │
│  │  │   ahg_payment    │                                               │
│  │  ├──────────────────┤                                               │
│  │  │ id            PK │                                               │
│  │  │ order_id      FK │                                               │
│  │  │ payment_gateway  │  (payfast/stripe)                             │
│  │  │ transaction_id   │                                               │
│  │  │ amount           │                                               │
│  │  │ currency         │                                               │
│  │  │ status           │  (pending/completed/failed/refunded)          │
│  │  │ gateway_response │  (JSON)                                       │
│  │  │ paid_at          │                                               │
│  │  └──────────────────┘                                               │
│  │                                                                     │
│  └─────────────────────────────────────────────────────────────────────┘
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## File Structure
```
ahgCartPlugin/
├── config/
│   └── ahgCartPluginConfiguration.class.php    # Routes and initialization
├── lib/
│   ├── Repositories/
│   │   └── EcommerceRepository.php             # Database operations
│   └── Services/
│       ├── CartService.php                     # Cart operations
│       └── EcommerceService.php                # E-commerce & payment logic
├── modules/
│   └── ahgCart/
│       ├── actions/
│       │   ├── addAction.class.php             # Add item to cart
│       │   ├── browseAction.class.php          # View cart
│       │   ├── checkoutAction.class.php        # Checkout process
│       │   ├── paymentAction.class.php         # Payment initiation
│       │   ├── paymentReturnAction.class.php   # Return from payment
│       │   ├── paymentCancelAction.class.php   # Payment cancelled
│       │   ├── paymentNotifyAction.class.php   # ITN webhook
│       │   ├── orderConfirmationAction.class.php
│       │   ├── adminSettingsAction.class.php   # E-commerce admin
│       │   ├── adminOrdersAction.class.php     # Order management
│       │   └── thankYouAction.class.php        # Guest thank you page
│       ├── config/
│       │   └── security.yml                    # Allow guest access
│       └── templates/
│           ├── browseSuccess.php
│           ├── checkoutSuccess.php
│           ├── paymentSuccess.php
│           ├── orderConfirmationSuccess.php
│           ├── adminSettingsSuccess.php
│           ├── adminOrdersSuccess.php
│           └── thankYouSuccess.php
└── data/
    └── install.sql                             # Database schema
```

---

## Routes

| Route | Action | Description |
|-------|--------|-------------|
| `/cart` | browse | View cart contents |
| `/cart/add/:slug` | add | Add item to cart |
| `/cart/remove/:id` | remove | Remove item from cart |
| `/cart/clear` | clear | Clear entire cart |
| `/cart/checkout` | checkout | Checkout page |
| `/cart/payment/:order` | payment | Payment page |
| `/cart/payment-return/:order` | paymentReturn | Return from gateway |
| `/cart/payment-cancel/:order` | paymentCancel | Payment cancelled |
| `/cart/payment/notify` | paymentNotify | ITN webhook (POST) |
| `/cart/order/:order` | orderConfirmation | Order details |
| `/cart/thank-you` | thankYou | Guest confirmation |
| `/admin/ecommerce` | adminSettings | E-commerce settings |
| `/admin/orders` | adminOrders | Order management |

---

## Key Services

### CartService
```php
class CartService
{
    // Add item to cart (supports user_id OR session_id)
    public function addToCart($userId, $objectId, $title, $slug, $sessionId = null): array
    
    // Get cart items for user or session
    public function getCart($userId = null, $sessionId = null): array
    
    // Remove item from cart
    public function removeFromCart($cartId, $userId = null, $sessionId = null): bool
    
    // Clear all cart items
    public function clearAll($userId): int
    public function clearAllBySession($sessionId): int
    
    // Merge guest cart into user cart on login
    public function mergeGuestCart($sessionId, $userId): int
}
```

### EcommerceService
```php
class EcommerceService
{
    // Check if e-commerce is enabled
    public function isEcommerceEnabled(): bool
    
    // Get cart with pricing information
    public function getCartWithPricing($userId, $repositoryId = null, $sessionId = null): array
    
    // Calculate cart totals (subtotal, VAT, total)
    public function calculateCartTotals($items, $repositoryId = null): array
    
    // Create order from cart
    public function createOrderFromCart(?int $userId, array $customerData, ?string $sessionId = null): array
    
    // Initiate PayFast payment
    public function initiatePayFastPayment(int $orderId): array
    
    // Process PayFast ITN notification
    public function processPayFastNotification(array $data): array
    
    // Generate download tokens after payment
    public function generateDownloadTokens(int $orderId): void
}
```

---

## Guest Checkout Flow

The cart supports guest checkout (no account required):

1. **Session Tracking**: Guest carts use PHP session ID
2. **Add to Cart**: Items stored with `session_id` instead of `user_id`
3. **Browse Cart**: Items retrieved by session ID
4. **Checkout**: Guest provides email for order confirmation
5. **Order Creation**: Order created with `session_id`, `user_id = NULL`
6. **Payment**: PayFast processes payment, returns to confirmation
7. **Merge on Login**: If guest later logs in, cart items can merge
```php
// Guest cart lookup
$sessionId = session_id();
$cartId = DB::table('cart')
    ->where('session_id', $sessionId)
    ->where('archival_description_id', $objectId)
    ->whereNull('completed_at')
    ->value('id');
```

---

## PayFast Integration

### Configuration

Settings stored in `ahg_ecommerce_settings`:
- `payfast_merchant_id` - Merchant ID from PayFast
- `payfast_merchant_key` - Merchant Key from PayFast
- `payfast_passphrase` - Security passphrase (optional but recommended)
- `payfast_sandbox` - 1 for testing, 0 for live

### Signature Generation

PayFast requires MD5 signature of all parameters:
```php
$pfData = [
    'merchant_id' => $settings->payfast_merchant_id,
    'merchant_key' => $settings->payfast_merchant_key,
    'return_url' => $siteUrl . '/cart/order/' . $order->order_number,
    'cancel_url' => $siteUrl . '/cart',
    'notify_url' => $siteUrl . '/cart/payment/notify',
    'name_first' => $firstName,
    'name_last' => $lastName,
    'email_address' => $order->customer_email,
    'm_payment_id' => $order->order_number,
    'amount' => number_format($order->total, 2, '.', ''),
    'item_name' => 'Order-' . $order->order_number,
];

// Generate signature
$signatureString = '';
foreach ($pfData as $key => $val) {
    if ($val !== null && $val !== '') {
        $signatureString .= $key . '=' . urlencode(trim($val)) . '&';
    }
}
$signatureString = rtrim($signatureString, '&');

if (!empty($passphrase)) {
    $signatureString .= '&passphrase=' . urlencode($passphrase);
}

$pfData['signature'] = md5($signatureString);
```

### ITN (Instant Transaction Notification)

PayFast sends POST to `/cart/payment/notify`:
```php
public function processPayFastNotification(array $data): array
{
    $orderNumber = $data['m_payment_id'];
    $paymentStatus = $data['payment_status'];
    
    if ($paymentStatus === 'COMPLETE') {
        // Update order status to 'paid'
        // Generate download tokens
        // Send confirmation email
    }
}
```

---

## Template Integration

Add cart button to item templates:
```php
<?php
$userId = $sf_user->getAttribute('user_id');
$sessionId = session_id();
if (empty($sessionId) && !$userId) { @session_start(); $sessionId = session_id(); }

$cartId = null;
if ($userId) {
    $cartId = DB::table('cart')
        ->where('user_id', $userId)
        ->where('archival_description_id', $resource->id)
        ->whereNull('completed_at')
        ->value('id');
} elseif ($sessionId) {
    $cartId = DB::table('cart')
        ->where('session_id', $sessionId)
        ->where('archival_description_id', $resource->id)
        ->whereNull('completed_at')
        ->value('id');
}

$hasDigitalObject = DB::table('digital_object')
    ->where('object_id', $resource->id)
    ->exists();
?>

<?php if (class_exists('ahgCartPluginConfiguration') && $hasDigitalObject): ?>
  <?php if ($cartId): ?>
    <a href="<?php echo url_for(['module' => 'ahgCart', 'action' => 'browse']); ?>" 
       class="btn btn-xs btn-outline-success" title="Go to Cart">
      <i class="fas fa-shopping-cart"></i>
    </a>
  <?php else: ?>
    <a href="<?php echo url_for(['module' => 'ahgCart', 'action' => 'add', 'slug' => $resource->slug]); ?>" 
       class="btn btn-xs btn-outline-success" title="Add to Cart">
      <i class="fas fa-cart-plus"></i>
    </a>
  <?php endif; ?>
<?php endif; ?>
```

---

## Testing

### Sandbox Credentials
```
Merchant ID: 10000100
Merchant Key: 46f0cd694581a
Passphrase: (leave empty for sandbox)

Test Buyer Email: sbtu01@payfast.co.za
Test Card: 5200000000000015
Expiry: Any future date
CVV: 123
```

### Database Verification
```sql
-- Check cart items
SELECT * FROM cart WHERE completed_at IS NULL ORDER BY id DESC LIMIT 10;

-- Check orders
SELECT order_number, status, total, customer_email, created_at 
FROM ahg_order ORDER BY id DESC LIMIT 10;

-- Check order items
SELECT oi.*, o.order_number 
FROM ahg_order_item oi 
JOIN ahg_order o ON oi.order_id = o.id 
ORDER BY oi.id DESC LIMIT 10;

-- Check payments
SELECT * FROM ahg_payment ORDER BY id DESC LIMIT 10;
```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 2.0.0 | 2026-01-13 | Guest checkout support, PayFast integration |
| 1.0.0 | 2026-01-13 | Initial release with e-commerce features |

---

## Related Documentation

- [E-Commerce User Guide](../cart-ecommerce-user-guide.md)
- [Request to Publish](ahgRequestToPublishPlugin.md)
- [AHG Framework Architecture](../AtoM_AHG_Framework_Library_Architecture_Diagrams.md)
