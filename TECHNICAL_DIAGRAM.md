# DIAGRAMME TECHNIQUE ET ARCHITECTURE

## 1. ARCHITECTURE ACTUELLE DE LA GESTION DE BOUTIQUES

```
┌─────────────────────────────────────────────────────────────────────┐
│                         COUCHE APPLICATION MOBILE (Flutter)         │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  GET /api/v1/auth/profile         GET /api/v1/vendor/dashboard    │
│  (Voir liste boutiques)           (Voir infos boutique + stats)    │
│         │                                      │                   │
└─────────┼──────────────────────────────────────┼───────────────────┘
          │                                      │
          │ HTTP Requests                       │
          │                                      │
┌─────────▼──────────────────────────────────────▼───────────────────┐
│                    COUCHE API REST (Laravel)                       │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  POST /api/v1/vendor/apply       GET /api/v1/vendor/dashboard     │
│  ├─ ProfileController@applyVendor ├─ ProfileController@vendorDash │
│  └─ Crée Shop                     └─ Retourne Shop + Stats         │
│                                                                     │
│  GET /api/v1/auth/profile                                          │
│  ├─ AuthController@profile                                         │
│  └─ Retourne User + shops array                                    │
│                                                                     │
│  ❌ GET /api/v1/vendor/shop       (À CRÉER)                        │
│  ❌ PUT /api/v1/vendor/shop       (À CRÉER)                        │
│  ❌ GET /api/v1/vendor/shops      (À CRÉER)                        │
│  ❌ GET /api/v1/shops/{id}        (À CRÉER - Public)               │
│                                                                     │
└─────────┬──────────────────────────────────────┬───────────────────┘
          │                                      │
          │ Database Queries                    │
          │                                      │
┌─────────▼──────────────────────────────────────▼───────────────────┐
│                    COUCHE MODÈLES & BASE DE DONNÉES                │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  App\Models\Shop                                                   │
│  ├─ Fillables: name, slug, description, logo, address, status... │
│  ├─ Relations:                                                     │
│  │  ├─ user() → User (belongsTo)                                  │
│  │  ├─ products() → Product (hasMany)                             │
│  │  ├─ verifier() → User (belongsTo)                              │
│  │  └─ rejector() → User (belongsTo)                              │
│  └─ Methods:                                                       │
│     ├─ isVerified(), isPending(), isRejected()                    │
│     └─ scopePending(), scopeVerified(), scopeRejected()           │
│                                                                     │
│  Database: shops table                                             │
│  ├─ Fields: id, user_id, name, slug, description                 │
│  ├─ Fields: categories (JSON), logo, address                     │
│  ├─ Fields: latitude, longitude, status                          │
│  ├─ Fields: verified_at, verified_by, rejected_at, rejected_by   │
│  ├─ Fields: rejection_reason                                      │
│  ├─ ⚠️ MANQUANT: phone, email                                     │
│  └─ ⚠️ MANQUANT: certification_date, is_certified                 │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 2. FLUX DE DONNÉES POUR CRÉER UNE BOUTIQUE

```
USER (Mobile App)
    │
    ├─► Input: shop_name, shop_description, shop_address, etc.
    │
    ▼
POST /api/v1/vendor/apply
    │
    ├─► Authenticate with auth:sanctum token
    │
    ├─► Validation Rules
    │   ├─ shop_name: required, string, max:255
    │   ├─ shop_description: nullable
    │   ├─ categories: nullable, array
    │   └─ shop_logo: nullable, image, max:2048
    │
    ├─► Process Upload (if logo provided)
    │   └─► Store to storage/shops/ (public disk)
    │
    ├─► Create Shop Record
    │   ├─ User.shops.create(shopData)
    │   └─ Set status = 'inactive' initially
    │
    ├─► Update User Profile (if name, gender provided)
    │   └─ User.update(userUpdates)
    │
    ├─► Add Vendor Role
    │   └─ User.addRole('vendeur')
    │
    ▼
RESPONSE
    └─► {
          success: true,
          shop: { id, name, slug, logo, status },
          user: { id, first_name, last_name, roles }
        }
```

---

## 3. FLUX DE DONNÉES POUR RÉCUPÉRER INFOS BOUTIQUE (À CRÉER)

```
USER (Mobile App)
    │
    ▼
GET /api/v1/vendor/shop
    │
    ├─► Authenticate with auth:sanctum token
    │
    ├─► Get Authenticated User
    │   └─ $user = auth()->user()
    │
    ├─► Check User is Vendor
    │   └─ if (!$user->hasRole('vendeur')) return 403
    │
    ├─► Get User's First Shop
    │   └─ $shop = $user->shops()->first()
    │
    ├─► Load Relations
    │   ├─ user (shop owner)
    │   ├─ products (shop items)
    │   ├─ verifier (admin who verified)
    │   └─ rejector (admin who rejected)
    │
    ├─► Calculate Statistics
    │   ├─ Total Orders: count from order_items where seller_id
    │   ├─ Total Sales: sum from order_items (completed orders)
    │   ├─ Total Products: count from products
    │   ├─ Average Rating: avg from product_reviews
    │   └─ Total Reviews: count from product_reviews
    │
    ▼
RESPONSE
    └─► {
          success: true,
          shop: {
            id, user_id, name, slug, description, logo,
            address, latitude, longitude, categories, status,
            phone, email, products_count
          },
          stats: {
            total_products, total_stock, total_orders,
            total_sales, average_rating, total_reviews
          },
          verification: {
            status, is_verified, is_pending, is_rejected,
            verified_at, rejected_at, rejection_reason
          }
        }
```

---

## 4. FLUX DE DONNÉES POUR MODIFIER UNE BOUTIQUE (À CRÉER)

```
USER (Mobile App)
    │
    ├─► Input: shop_name, shop_description, shop_phone, etc.
    │
    ▼
PUT /api/v1/vendor/shop
    │
    ├─► Authenticate with auth:sanctum token
    │
    ├─► Get Authenticated User & Shop
    │   └─ $shop = auth()->user()->shops()->first()
    │
    ├─► Validate Input
    │   ├─ shop_name: sometimes, string, max:255
    │   ├─ shop_description: sometimes, nullable, string
    │   ├─ shop_phone: sometimes, nullable, string, max:20
    │   ├─ shop_logo: sometimes, nullable, image, max:2048
    │   └─ etc.
    │
    ├─► Handle Logo Upload (if provided)
    │   ├─ Delete old logo if exists
    │   └─ Store new logo to storage/shops/
    │
    ├─► Update Shop
    │   └─ $shop.update(validated_data)
    │
    ├─► Reload Relations & Calculate Stats
    │   └─ Same as GET endpoint
    │
    ▼
RESPONSE
    └─► {
          success: true,
          message: "Boutique mise à jour avec succès",
          shop: { ... updated shop data ... },
          stats: { ... recalculated stats ... }
        }
```

---

## 5. ARCHITECTURE DE LA BASE DE DONNÉES

### Avant (Actuel)
```
shops table
├─ id (INT, PRIMARY)
├─ user_id (INT, FOREIGN KEY → users.id)
├─ name (VARCHAR 255)
├─ slug (VARCHAR 255, UNIQUE)
├─ description (TEXT)
├─ categories (JSON) ← Added in migration 1
├─ logo (VARCHAR 255)
├─ shop_link (VARCHAR 255)
├─ address (TEXT)
├─ latitude (DECIMAL 10,8)
├─ longitude (DECIMAL 11,8)
├─ status (ENUM: active, inactive)
├─ verified_at (TIMESTAMP) ← Added in migration 2
├─ verified_by (INT, FOREIGN KEY)
├─ rejection_reason (TEXT)
├─ rejected_at (TIMESTAMP)
├─ rejected_by (INT, FOREIGN KEY)
├─ created_at (TIMESTAMP)
└─ updated_at (TIMESTAMP)
```

### Après (Avec améliorations)
```
shops table (AJOUT DE CHAMPS)
├─ phone (VARCHAR 20) ← NOUVEAU
├─ email (VARCHAR 255) ← NOUVEAU
├─ certification_date (TIMESTAMP) ← OPTIONNEL
├─ is_certified (BOOLEAN) ← OPTIONNEL
└─ [tous les autres champs existants]

shop_statistics table (OPTIONNEL - pour tracking)
├─ id (INT, PRIMARY)
├─ shop_id (INT, FOREIGN KEY → shops.id)
├─ visits_count (INT)
├─ clicks_count (INT)
├─ favorites_count (INT)
├─ date (TIMESTAMP)
├─ created_at (TIMESTAMP)
└─ updated_at (TIMESTAMP)

shop_certifications table (OPTIONNEL - pour historique)
├─ id (INT, PRIMARY)
├─ shop_id (INT, FOREIGN KEY → shops.id)
├─ certified_by (INT, FOREIGN KEY → users.id)
├─ certification_type (VARCHAR)
├─ issued_date (TIMESTAMP)
├─ expiry_date (TIMESTAMP)
├─ document_path (VARCHAR)
└─ status (ENUM: active, expired, revoked)
```

---

## 6. RELATIONS ENTRE MODÈLES

```
User
├─ shops() ← hasMany(Shop)
├─ products() ← hasMany(Product)
├─ orders() ← hasMany(Order)
├─ vendorPackages() ← hasMany(VendorPackage)
└─ delivererCompany() ← hasOne(DelivererCompany)
    │
    ├─ [Vendor specific]
    └─ activeVendorPackage() ← hasOne(VendorPackage, active)

Shop
├─ user() ← belongsTo(User)
├─ products() ← hasMany(Product)
├─ verifier() ← belongsTo(User, 'verified_by')
├─ rejector() ← belongsTo(User, 'rejected_by')
└─ [À AJOUTER]
   ├─ statistics() ← hasMany(ShopStatistic)
   └─ certifications() ← hasMany(ShopCertification)

Product
├─ user() ← belongsTo(User)
├─ shop() ← belongsTo(Shop)
├─ category() ← belongsTo(Category)
├─ images() ← hasMany(ProductImage)
├─ primaryImage() ← hasOne(ProductImage, is_primary = true)
└─ reviews() ← hasMany(ProductReview)

Order
├─ user() ← belongsTo(User)
├─ items() ← hasMany(OrderItem)
└─ deliveryPerson() ← belongsTo(User, 'delivery_person_id')

OrderItem
├─ order() ← belongsTo(Order)
├─ product() ← belongsTo(Product)
├─ seller() ← belongsTo(User, 'seller_id')
└─ shop() ← belongsTo(Shop)
```

---

## 7. CONTRÔLEURS - STRUCTURES

### Contrôleurs Existants
```
Api\ProfileController
├─ applyVendor(Request) → POST /vendor/apply
├─ applyDelivery(Request) → POST /delivery/apply
├─ vendorDashboard(Request) → GET /vendor/dashboard
└─ deliveryDashboard(Request) → GET /delivery/dashboard

Api\AuthController
├─ sendOtp(Request) → POST /auth/send-otp
├─ verifyOtp(Request) → POST /auth/verify-otp
├─ login(Request) → POST /auth/login
├─ profile(Request) → GET /auth/profile
├─ updateProfile(Request) → PUT /auth/profile
├─ getPreferences(Request) → GET /auth/preferences
├─ updatePreferences(Request) → PUT /auth/preferences
└─ logout(Request) → POST /auth/logout

Api\VendorProductController
├─ index(Request) → GET /vendor/products
├─ update(Request, $id) → PUT /vendor/products/{id}
└─ destroy(Request, $id) → DELETE /vendor/products/{id}
```

### Contrôleur À Créer
```
Api\ShopController
├─ show(Request) → GET /vendor/shop ← Récupérer infos
├─ update(Request) → PUT /vendor/shop ← Modifier
├─ index(Request) → GET /vendor/shops ← Lister toutes
├─ showPublic($shopId) → GET /shops/{id} ← Public
├─ getStats(Request) → GET /vendor/shop/stats ← Stats (optionnel)
│
├─ Private Methods:
├─ formatShop(Shop) → Format pour private use
├─ formatShopPublic(Shop) → Format pour public use
├─ formatShopBasic(Shop) → Format basique
└─ calculateShopStats(Shop, User) → Calculs
```

---

## 8. VALIDATION DES DONNÉES

### Validation pour PUT /vendor/shop
```
Règles de validation:
├─ shop_name
│  └─ sometimes, string, max:255
│
├─ shop_description
│  └─ sometimes, nullable, string
│
├─ shop_address
│  └─ sometimes, nullable, string
│
├─ shop_phone (NOUVEAU)
│  └─ sometimes, nullable, string, max:20
│
├─ shop_logo
│  └─ sometimes, nullable, image, max:2048
│
├─ categories
│  ├─ sometimes, nullable, array
│  └─ categories.*: string
│
├─ shop_latitude
│  └─ sometimes, nullable, numeric, between:-90,90
│
└─ shop_longitude
   └─ sometimes, nullable, numeric, between:-180,180
```

---

## 9. SÉCURITÉ & AUTHENTIFICATION

```
Middleware appliqué:
├─ auth:sanctum → Vérifie le token Bearer
├─ Pour tous les endpoints /vendor/* → requiert auth
└─ Pour tous les endpoints /auth/* → requiert auth (sauf login)

Vérifications supplémentaires:
├─ User doit avoir rôle 'vendeur' ou 'vendor'
├─ User doit être propriétaire de la boutique (user_id == auth()->id())
└─ Réponse 403 si permissions insuffisantes

Réponses HTTP:
├─ 200 OK → Opération réussie
├─ 404 Not Found → Boutique/ressource non trouvée
├─ 403 Forbidden → Permission refusée (pas vendeur ou pas propriétaire)
└─ 422 Unprocessable → Validation failure
```

---

## 10. TIMELINE D'IMPLÉMENTATION RECOMMANDÉE

```
JOUR 1:
├─ 0h-1h   : Créer ShopController.php
├─ 1h-2h   : Implémenter show() et showPublic()
├─ 2h-3h   : Implémenter update()
└─ 3h-4h   : Ajouter routes dans api.php

JOUR 2:
├─ 0h-1h   : Créer migration pour phone/email
├─ 1h-2h   : Exécuter migrations
├─ 2h-3h   : Mettre à jour Shop model $fillable
├─ 3h-4h   : Tests manuels avec curl/Postman
└─ 4h-5h   : Documentation Postman

JOUR 3:
├─ 0h-2h   : Tests unitaires
├─ 2h-3h   : Fix any issues found
└─ 3h-4h   : Code review & cleanup
```

