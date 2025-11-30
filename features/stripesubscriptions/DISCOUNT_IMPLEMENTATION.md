# 🎯 Plan d'Implémentation : Système de Discounts Stripe

## ✅ **ANALYSE : CLAIRE ET FAISABLE**

---

## 📋 **Fonctionnalités Demandées**

1. **Admin** : Sélectionner un discount Stripe depuis la page d'édition du plan
2. **Frontend** : Afficher le prix barré + prix promotionnel
3. **Checkout** : Appliquer automatiquement le code promo lors du paiement Stripe

---

## 🏗️ **Architecture Existante**

### ✅ Déjà en place :
- **Post Type** : `stripe_plan`
- **Meta Box** : `stripe_plan_details`
- **Affichage** : `Feature.php` lignes 670-820
- **Checkout** : `handle_create_checkout_session()` ligne 869
- **Template Admin** : `views/admin/plan-meta-box.php`

---

## 🛠️ **Modifications Nécessaires**

### **1️⃣ API - Récupérer les Discounts Stripe**
**Fichier** : `includes/API.php`

**Ajouter méthode** :
```php
/**
 * Get all Stripe coupons/discounts
 */
public function get_discounts(int $limit = 100): array
{
    try {
        $coupons = \Stripe\Coupon::all(['limit' => $limit]);
        $promotion_codes = \Stripe\PromotionCode::all(['limit' => $limit, 'active' => true]);
        
        $discounts = [];
        
        // Add coupons
        foreach ($coupons->data as $coupon) {
            $discounts[] = [
                'id' => $coupon->id,
                'code' => $coupon->id,
                'type' => 'coupon',
                'percent_off' => $coupon->percent_off,
                'amount_off' => $coupon->amount_off,
                'currency' => $coupon->currency,
                'name' => $coupon->name ?? $coupon->id
            ];
        }
        
        // Add promotion codes
        foreach ($promotion_codes->data as $promo) {
            $coupon = $promo->coupon;
            $discounts[] = [
                'id' => $promo->id,
                'code' => $promo->code,
                'type' => 'promotion_code',
                'percent_off' => $coupon->percent_off,
                'amount_off' => $coupon->amount_off,
                'currency' => $coupon->currency,
                'name' => $promo->code
            ];
        }
        
        return $discounts;
    } catch (\Exception $e) {
        $this->feature->log('error', 'Failed to fetch Stripe discounts', [
            'error' => $e->getMessage()
        ]);
        return [];
    }
}
```

---

### **2️⃣ Admin - Meta Box avec Sélecteur de Discount**
**Fichier** : `views/admin/plan-meta-box.php`

**Ajouter dans le formulaire** :
```php
<!-- Discount Selection -->
<div class="form-group">
    <label for="discount_id">
        <?php _e('Code Promo / Discount', 'cobra-ai'); ?>
        <span class="description"><?php _e('Sélectionner un discount depuis Stripe', 'cobra-ai'); ?></span>
    </label>
    
    <?php 
    $discounts = $this->feature->get_api()->get_discounts();
    $current_discount = get_post_meta($post->ID, '_discount_id', true);
    ?>
    
    <select name="stripe_plan[discount_id]" id="discount_id" class="regular-text">
        <option value=""><?php _e('Aucun discount', 'cobra-ai'); ?></option>
        
        <?php foreach ($discounts as $discount): ?>
            <option value="<?php echo esc_attr($discount['id']); ?>" 
                    data-type="<?php echo esc_attr($discount['type']); ?>"
                    <?php selected($current_discount, $discount['id']); ?>>
                <?php 
                echo esc_html($discount['name']);
                
                // Display discount value
                if ($discount['percent_off']) {
                    echo ' (-' . $discount['percent_off'] . '%)';
                } elseif ($discount['amount_off']) {
                    echo ' (-' . number_format($discount['amount_off'] / 100, 2) . ' ' . strtoupper($discount['currency']) . ')';
                }
                ?>
            </option>
        <?php endforeach; ?>
    </select>
    
    <p class="description">
        <?php _e('Le discount sera appliqué automatiquement lors du paiement Stripe', 'cobra-ai'); ?>
    </p>
</div>
```

---

### **3️⃣ Admin - Sauvegarder le Discount**
**Fichier** : `includes/Admin.php` (méthode `save_plan_meta`, ligne ~327)

**Ajouter après les autres update_post_meta** :
```php
// Save discount
if (isset($data['discount_id'])) {
    update_post_meta($post_id, '_discount_id', sanitize_text_field($data['discount_id']));
}
```

---

### **4️⃣ Frontend - Afficher Prix Barré + Promo**
**Fichier** : `Feature.php` (méthode `filter_plan_content`, ligne ~670)

**Remplacer l'affichage du prix par** :
```php
// Get discount if available
$discount_id = get_post_meta($post_id, '_discount_id', true);
$original_price = $price_amount;
$discounted_price = null;
$discount_label = '';

if ($discount_id) {
    try {
        // Fetch discount from Stripe
        try {
            $promo = \Stripe\PromotionCode::retrieve($discount_id);
            $coupon = $promo->coupon;
            $discount_label = $promo->code;
        } catch (\Exception $e) {
            $coupon = \Stripe\Coupon::retrieve($discount_id);
            $discount_label = $coupon->id;
        }
        
        // Calculate discounted price
        if ($coupon->percent_off) {
            $discounted_price = $original_price * (1 - $coupon->percent_off / 100);
            $discount_label .= ' (-' . $coupon->percent_off . '%)';
        } elseif ($coupon->amount_off && $coupon->currency === strtolower($currency)) {
            $discounted_price = $original_price - ($coupon->amount_off / 100);
            $discount_label .= ' (-' . $this->format_price($coupon->amount_off / 100, $currency) . ')';
        }
    } catch (\Exception $e) {
        $this->log('error', 'Failed to retrieve discount', [
            'discount_id' => $discount_id,
            'error' => $e->getMessage()
        ]);
    }
}
?>

<div class="plan-pricing">
    <?php if ($discounted_price !== null): ?>
        <!-- Prix avec promotion -->
        <div class="price-container">
            <span class="original-price strikethrough">
                <?php echo esc_html($this->format_price($original_price, $currency)); ?>
            </span>
            <span class="discounted-price">
                <?php echo esc_html($this->format_price($discounted_price, $currency)); ?>
            </span>
            <span class="billing-cycle">
                / <?php echo esc_html($billing_interval); ?>
            </span>
        </div>
        <div class="discount-badge">
            <span class="promo-icon">🎉</span>
            <?php echo esc_html($discount_label); ?>
        </div>
    <?php else: ?>
        <!-- Prix normal -->
        <span class="price">
            <?php echo esc_html($this->format_price($original_price, $currency)); ?>
        </span>
        <span class="billing-cycle">
            / <?php echo esc_html($billing_interval); ?>
        </span>
    <?php endif; ?>
</div>
```

---

### **5️⃣ Checkout - Appliquer le Discount Automatiquement**
**Fichier** : `Feature.php` (méthode `handle_create_checkout_session`, ligne ~869)

**Modifier le `$session_params`** :
```php
// Get discount if available
$discount_id = get_post_meta($plan_id, '_discount_id', true);

// Create Stripe Checkout Session
$session_params = [
    'customer' => $stripe_customer_id,
    'line_items' => [[
        'price' => $price_id,
        'quantity' => 1,
    ]],
    'mode' => 'subscription',
    'success_url' => $success_url,
    'cancel_url' => $cancel_url,
    'customer_update' => [
        'address' => 'auto',
        'name' => 'auto'
    ],
    'client_reference_id' => $user->ID . '_' . $plan_id,
    'subscription_data' => [
        'metadata' => [
            'user_id' => $user->ID,
            'plan_id' => $plan_id,
            'plan_name' => $plan->post_title
        ]
    ]
];

// Add discount if available
if ($discount_id) {
    try {
        // Check if it's a promotion code or coupon
        try {
            $promo = \Stripe\PromotionCode::retrieve($discount_id);
            $session_params['discounts'] = [[
                'promotion_code' => $discount_id
            ]];
        } catch (\Exception $e) {
            // It's a coupon ID
            $session_params['discounts'] = [[
                'coupon' => $discount_id
            ]];
        }
        
        $this->log('info', 'Discount applied to checkout', [
            'discount_id' => $discount_id,
            'plan_id' => $plan_id
        ]);
    } catch (\Exception $e) {
        $this->log('error', 'Failed to apply discount', [
            'discount_id' => $discount_id,
            'error' => $e->getMessage()
        ]);
    }
}

// Add trial period if enabled
if ($trial_enabled && $trial_days > 0) {
    $session_params['subscription_data']['trial_period_days'] = $trial_days;
}
```

---

### **6️⃣ CSS - Styling pour le Prix Barré**
**Fichier** : `assets/css/public.css`

**Ajouter** :
```css
/* Discount Styling */
.plan-pricing {
    margin: 20px 0;
}

.price-container {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.original-price.strikethrough {
    text-decoration: line-through;
    color: #999;
    font-size: 1.2em;
}

.discounted-price {
    font-size: 2em;
    font-weight: bold;
    color: #46b450; /* Green for discount */
}

.discount-badge {
    display: inline-block;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.9em;
    font-weight: 600;
    margin-top: 8px;
}

.discount-badge .promo-icon {
    margin-right: 5px;
}

.billing-cycle {
    color: #666;
    font-size: 1em;
}
```

---

## 📝 **Ordre d'Implémentation**

### Étape 1 : API
✅ Ajouter `get_discounts()` dans `includes/API.php`

### Étape 2 : Admin
✅ Modifier `views/admin/plan-meta-box.php` (ajouter sélecteur)
✅ Modifier `includes/Admin.php` (sauvegarder `_discount_id`)

### Étape 3 : Frontend
✅ Modifier affichage du prix dans `Feature.php` (ligne ~700)
✅ Ajouter CSS pour prix barré

### Étape 4 : Checkout
✅ Modifier `handle_create_checkout_session()` (ligne ~950)

### Étape 5 : Tests
✅ Créer un coupon dans Stripe Dashboard
✅ L'assigner à un plan
✅ Vérifier affichage frontend
✅ Tester le checkout complet

---

## 🎯 **Résumé**

### ✅ **Faisabilité : 100%**
- Utilisation de l'API Stripe existante
- Structure WordPress déjà en place
- Modifications légères et ciblées

### ⏱️ **Estimation : 2-3 heures**
- 30 min : API + Admin
- 1h : Frontend (affichage)
- 30 min : Checkout (application)
- 30 min : CSS + Tests

### 🔧 **Fichiers à Modifier**
1. `includes/API.php` (nouvelle méthode)
2. `views/admin/plan-meta-box.php` (sélecteur)
3. `includes/Admin.php` (save)
4. `Feature.php` (affichage + checkout)
5. `assets/css/public.css` (styling)

---

## 🚀 **Prêt à Implémenter ?**

Dites-moi si vous voulez que je commence l'implémentation ! 
Je peux procéder fichier par fichier avec vous. 😊
