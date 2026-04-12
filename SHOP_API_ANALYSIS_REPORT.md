# RAPPORT COMPLET: ANALYSE DES APIs DE GESTION DE BOUTIQUES

## 1. RÉSUMÉ EXÉCUTIF

Le backend ASSO est un projet Laravel avec une architecture API REST. Les boutiques sont partiellement intégrées au système, avec des données stockées en base de données mais avec une couverture API incomplète.

### Champs disponibles dans la base de données (modèle Shop)
```
- id (INT)
- user_id (INT) - Clé étrangère vers users
- name (VARCHAR)
- slug (VARCHAR, UNIQUE)
- description (TEXT)
- categories (JSON)
- logo (VARCHAR)
- shop_link (VARCHAR)
- address (TEXT)
- latitude (DECIMAL)
- longitude (DECIMAL)
- status (ENUM: active, inactive)
- verified_at (TIMESTAMP, nullable)
- verified_by (INT, nullable) - Admin who verified
- rejection_reason (TEXT, nullable)
- rejected_at (TIMESTAMP, nullable)
- rejected_by (INT, nullable) - Admin who rejected
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

---

## 2. APIs EXISTANTES

### 2.1 APIs Publiques (Sans authentification)

#### GET /api/v1/products
- **Description**: Récupère tous les produits publiques
- **Auth**: Aucune
- **Paramètres**: Aucun
- **Retourne**: Liste des produits avec infos de la boutique

#### GET /api/v1/products/{id}
- **Description**: Récupère les détails d'un produit
- **Auth**: Aucune
- **Retourne**: Détails produit + info boutique

### 2.2 APIs Protégées (Authentification requise)

#### POST /api/v1/vendor/apply
**Endpoint Principal pour la création de boutique**
- **URL**: `/api/v1/vendor/apply`
- **Méthode**: POST
- **Auth**: auth:sanctum (Authentification requise)
- **Contrôleur**: `App\Http\Controllers\Api\ProfileController@applyVendor`

**Paramètres acceptés**:
```
- shop_name* (string, max:255) - Nom obligatoire
- shop_description (nullable|string)
- shop_address (nullable|string)
- shop_logo (nullable|image, max:2048)
- shop_latitude (nullable|numeric)
- shop_longitude (nullable|numeric)
- categories (nullable|array)
- company_name (nullable|string)
- first_name (nullable|string)
- last_name (nullable|string)
- gender (nullable|string)
- account_type (nullable|string)
- avatar (nullable|image)
```

**Réponse (200 - Succès)**:
```json
{
  "success": true,
  "message": "Vous êtes maintenant vendeur !",
  "shop": {
    "id": 1,
    "name": "Ma Boutique",
    "slug": "ma-boutique-abc123",
    "logo": "http://localhost/storage/shops/...",
    "description": "...",
    "address": "...",
    "latitude": 12.3456,
    "longitude": 1.2345,
    "categories": [],
    "status": "inactive"
  },
  "user": {
    "id": 1,
    "first_name": "...",
    "last_name": "...",
    "name": "...",
    "role": "vendeur",
    "roles": ["vendeur", "client"],
    "company_name": "...",
    "gender": "...",
    "avatar": "..."
  }
}
```

#### GET /api/v1/vendor/dashboard
**Récupère toutes les infos du dashboard vendeur (Y COMPRIS la boutique)**
- **URL**: `/api/v1/vendor/dashboard`
- **Méthode**: GET
- **Auth**: auth:sanctum
- **Contrôleur**: `App\Http\Controllers\Api\ProfileController@vendorDashboard`

**Paramètres**: Aucun

**Réponse (200 - Succès)**:
```json
{
  "success": true,
  "data": {
    "shop": {
      "id": 1,
      "name": "Ma Boutique",
      "slug": "ma-boutique",
      "logo": "http://.../storage/shops/...",
      "description": "Description de la boutique",
      "address": "Adresse complète",
      "latitude": 12.3456,
      "longitude": 1.2345,
      "categories": ["Électronique", "Vêtements"],
      "status": "active"
    },
    "stats": {
      "total_orders": 45,
      "total_sales": 125000.00,
      "total_products": 12,
      "total_reviews": 8,
      "rating": 4.5
    },
    "package": {
      "has_package": true,
      "vendor_package": {
        "id": 1,
        "storage_total_mb": 1000.0,
        "storage_used_mb": 250.5,
        "storage_remaining_mb": 749.5,
        "storage_percentage_used": 25.05,
        "purchased_at": "2026-04-01T10:00:00Z",
        "expires_at": "2026-05-01T10:00:00Z",
        "days_remaining": 19,
        "status": "active",
        "payment_reference": "PAY_123456",
        "package": {
          "id": 1,
          "name": "Package Pro",
          "description": "...",
          "price": 9999.00,
          "formatted_price": "9 999 FCFA",
          "duration_days": 30,
          "formatted_duration": "30 jours",
          "storage_size_mb": 1000,
          "formatted_storage_size": "1 GB"
        }
      }
    },
    "verification": {
      "status": "active",
      "message": "Votre boutique est vérifiée et active"
    },
    "products": [
      {
        "id": 1,
        "name": "Produit 1",
        "price": 5000.00,
        "stock": 10,
        "status": "active",
        "primary_image": "http://.../storage/..."
      }
    ]
  }
}
```

#### GET /api/v1/auth/profile
**Récupère le profil utilisateur (inclut les boutiques)**
- **URL**: `/api/v1/auth/profile`
- **Méthode**: GET
- **Auth**: auth:sanctum
- **Contrôleur**: `App\Http\Controllers\Api\AuthController@profile`

**Réponse**:
```json
{
  "success": true,
  "user": {
    "id": 1,
    "first_name": "Jean",
    "last_name": "Dupont",
    "name": "Jean Dupont",
    "email": "jean@example.com",
    "phone": "+237123456789",
    "role": "vendeur",
    "roles": ["vendeur", "client"],
    "gender": "male",
    "birth_date": "1990-01-15",
    "avatar": "http://...",
    "country": "Cameroun",
    "address": "...",
    "latitude": 3.8667,
    "longitude": 11.5167,
    "is_profile_complete": true,
    "preferences": {...},
    "referral_code": "JEA4567",
    "company_name": "Ma Société",
    "company_logo": "http://...",
    "total_earnings": 45000.00,
    "pending_earnings": 5000.00,
    "shops": [
      {
        "id": 1,
        "name": "Ma Boutique",
        "slug": "ma-boutique",
        "logo": "http://...",
        "status": "active"
      }
    ],
    "created_at": "2026-03-01T10:00:00Z"
  }
}
```

#### PUT /api/v1/auth/profile
**Met à jour le profil utilisateur**
- **URL**: `/api/v1/auth/profile`
- **Méthode**: PUT
- **Auth**: auth:sanctum
- **Paramètres**: first_name, last_name, email, gender, birth_date, address, latitude, longitude, avatar

---

## 3. APIs MANQUANTES (À CRÉER)

### 3.1 API pour Récupérer les Infos Complètes de la Boutique

**GET /api/v1/vendor/shop** ❌ MANQUANT
```
Description: Récupère TOUS les détails de la boutique du vendeur connecté
Auth: auth:sanctum
Données retournées:
  - Infos de base (nom, adresse, téléphone, logo, description)
  - Statut de vérification
  - Certification/Vérification
  - Statistiques d'audience (nombre de visites/clics)
  - Statistiques d'inventaire (total produits, stock total)
  - Stats de vente (commandes, revenus, ratings)
  - Package actif (stockage utilisé, limite, jours restants)
```

**GET /api/v1/vendor/shop/{shopId}** ❌ MANQUANT
```
Description: Récupère les infos publiques d'une boutique
Auth: Aucune
Données retournées: Infos publiques de la boutique
```

### 3.2 API pour Modifier la Boutique

**PUT /api/v1/vendor/shop** ❌ MANQUANT
```
Description: Met à jour les infos de la boutique du vendeur
Auth: auth:sanctum
Paramètres:
  - shop_name (string)
  - shop_description (string)
  - shop_address (string)
  - shop_phone (string) - NOUVEAU
  - shop_logo (image)
  - categories (array)
  - shop_latitude (numeric)
  - shop_longitude (numeric)
Retourne: Les infos complètes mises à jour
```

**PUT /api/v1/vendor/shop/{shopId}** ❌ MANQUANT
```
Description: Met à jour les infos d'une boutique (par ID)
Auth: auth:sanctum
Permissions: L'utilisateur doit être propriétaire de la boutique
```

### 3.3 API pour Récupérer les Stats de la Boutique

**GET /api/v1/vendor/shop/stats/dashboard** ❌ MANQUANT (Partiellement dans vendorDashboard)
```
Description: Récupère les statistiques détaillées de la boutique
Auth: auth:sanctum
Données:
  - Statistiques d'audience (clics, visites)
  - Statistiques de vente (montant total, nombre de commandes)
  - Statistiques d'inventaire (produits actifs, stock disponible)
  - Ratings moyens
  - Nombre de reviews
```

### 3.4 API pour Récupérer Toutes les Boutiques du Vendeur

**GET /api/v1/vendor/shops** ❌ MANQUANT
```
Description: Liste toutes les boutiques du vendeur (pour ceux qui en ont plusieurs)
Auth: auth:sanctum
Retourne: Array de boutiques avec stats basiques
```

---

## 4. ANALYSE DES DONNÉES MANQUANTES

### Données présentes dans Shop Model mais non exposées via API:
- ✓ Nom, slug, description, logo
- ✓ Adresse, latitude, longitude
- ✓ Catégories
- ✓ Status (active/inactive)
- ✓ Verification fields (verified_at, verified_by)
- ✗ Téléphone (MANQUANT en base de données!)
- ✗ Email (MANQUANT en base de données!)
- ✗ Statistiques d'audience (MANQUANT en base de données!)
- ✗ Certificat/certification (MANQUANT en base de données!)

### Données manquantes en base de données:
1. **phone** - Téléphone de la boutique (déjà dans User, peut être repris)
2. **email** - Email de contact de la boutique
3. **certification_date** - Date de certification
4. **is_certified** - Booléen pour certification
5. **monthly_visitors** - Statistiques d'audience
6. **click_through_rate** - CTR
7. **contact_clicks_count** - Nombre de clics de contact

---

## 5. CONTRÔLEURS ET ROUTES ACTUELS

### Routes API Existantes:
```
POST   /api/v1/auth/send-otp              -> AuthController@sendOtp
POST   /api/v1/auth/verify-otp            -> AuthController@verifyOtp
POST   /api/v1/auth/login                 -> AuthController@login

GET    /api/v1/auth/profile               -> AuthController@profile
PUT    /api/v1/auth/profile               -> AuthController@updateProfile
GET    /api/v1/auth/preferences           -> AuthController@getPreferences
PUT    /api/v1/auth/preferences           -> AuthController@updatePreferences
POST   /api/v1/auth/logout                -> AuthController@logout

POST   /api/v1/vendor/apply               -> ProfileController@applyVendor
GET    /api/v1/vendor/dashboard           -> ProfileController@vendorDashboard
GET    /api/v1/vendor/package/current     -> PackageController@currentPackage

POST   /api/v1/products                   -> ProductController@store
GET    /api/v1/products                   -> ProductController@index
GET    /api/v1/products/{id}              -> ProductController@show
GET    /api/v1/products/nearby            -> ProductController@nearby
GET    /api/v1/products/recent            -> ProductController@recent
GET    /api/v1/favorites                  -> ProductController@favorites
POST   /api/v1/products/{id}/favorite     -> ProductController@toggleFavorite

GET    /api/v1/vendor/products            -> VendorProductController@index
PUT    /api/v1/vendor/products/{id}       -> VendorProductController@update
DELETE /api/v1/vendor/products/{id}       -> VendorProductController@destroy

GET    /api/v1/vendor/orders              -> VendorOrderController@index
POST   /api/v1/vendor/orders/{id}/validate -> VendorOrderController@validate
POST   /api/v1/vendor/orders/{id}/reject  -> VendorOrderController@reject
POST   /api/v1/vendor/orders/{id}/assign-delivery -> VendorOrderController@assignDelivery
```

### Routes Web (Admin):
```
GET    /admin/shops                       -> ShopController@index
GET    /admin/shops/create                -> ShopController@create
POST   /admin/shops                       -> ShopController@store
GET    /admin/shops/{id}                  -> ShopController@show
GET    /admin/shops/{id}/edit             -> ShopController@edit
PUT    /admin/shops/{id}                  -> ShopController@update
DELETE /admin/shops/{id}                  -> ShopController@destroy
POST   /admin/shops/{id}/verify           -> ShopVerificationController@verify
POST   /admin/shops/{id}/reject           -> ShopVerificationController@reject
POST   /admin/shops/{id}/toggle-status    -> ShopVerificationController@toggleStatus
```

---

## 6. STRUCTURE DES DONNÉES (Shop Model)

### Fichier: `/app/Models/Shop.php`
```php
protected $fillable = [
    'user_id',
    'name',
    'slug',
    'description',
    'categories',
    'logo',
    'shop_link',
    'address',
    'latitude',
    'longitude',
    'status',
    'verified_at',
    'verified_by',
    'rejection_reason',
    'rejected_at',
    'rejected_by',
];

protected $casts = [
    'categories' => 'array',
    'verified_at' => 'datetime',
    'rejected_at' => 'datetime',
];

// Relations
public function user(): BelongsTo { ... }
public function products(): HasMany { ... }
public function verifier(): BelongsTo { ... }
public function rejector(): BelongsTo { ... }
```

---

## 7. RECOMMANDATIONS D'IMPLÉMENTATION

### Priority 1 (Haute priorité):
1. Créer `GET /api/v1/vendor/shop` - Récupère les infos actuelles de la boutique
2. Créer `PUT /api/v1/vendor/shop` - Permet de modifier la boutique
3. Ajouter champs manquants à la base de données:
   - `phone` (VARCHAR) dans shops table
   - `email` (VARCHAR) dans shops table (ou reprendre de user.email)

### Priority 2 (Moyenne priorité):
1. Créer `GET /api/v1/vendor/shop/stats` - Statistiques détaillées
2. Créer `GET /api/v1/vendor/shops` - Lister plusieurs boutiques
3. Ajouter tables pour les statistiques:
   - `shop_statistics` table pour audience, clics
   - `shop_certifications` table pour les certificats

### Priority 3 (Basse priorité):
1. Créer `GET /api/v1/shops/{shopId}` - Infos publiques
2. Implémenter le système de certification complet
3. Ajouter système de ratings/reviews par boutique

---

## 8. FICHIERS CLÉS À MODIFIER/CRÉER

### À créer:
- `app/Http/Controllers/Api/ShopController.php` - API publique
- Migrations pour ajouter phone, email, certification à shops table
- Migrations pour shop_statistics table
- Routes dans `routes/api.php`

### À modifier:
- `app/Models/Shop.php` - Ajouter relations et méthodes
- `database/migrations/*_create_shops_table` - Ajouter nouveaux champs

---

## 9. RÉSUMÉ: ÉTAT ACTUEL VS ATTENDU

| Fonctionnalité | État | API | Admin | DB |
|---|---|---|---|---|
| Créer boutique | ✓ Existe | ✓ | ✓ | ✓ |
| Récupérer infos boutique | ⚠ Partiel | ✗ | ✓ | ✓ |
| Modifier boutique | ✗ | ✗ | ✓ | ✓ |
| Récupérer stats boutique | ⚠ Partiel | ✗ | ⚠ | ✗ |
| Téléphone boutique | ✗ | ✗ | ✗ | ✗ |
| Certification | ⚠ Basique | ✗ | ✓ | ⚠ |
| Stats d'audience | ✗ | ✗ | ✗ | ✗ |

Legend:
- ✓ = Complètement implémenté
- ⚠ = Partiellement implémenté
- ✗ = Manquant
