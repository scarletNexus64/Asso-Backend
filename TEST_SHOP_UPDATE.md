# Guide de Test - Mise à jour des boutiques vendeur

## Informations de test

**Vendeur 1:**
- Nom: Bouloulou Lou
- User ID: 20
- Boutique: Boutique Kzz (ID: 9)
- Position actuelle: 3.77793753, 11.50716746

**Vendeur 2:**
- Nom: Stevo Bou
- User ID: 21
- Boutique: Buzzx Shop (ID: 10)
- Position actuelle: 3.86842205, 11.52820377

---

## Test 1 : Authentification et obtention du token

### Méthode 1 : Via l'API de login

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "+237688851949",
    "password": "votre_mot_de_passe"
  }'
```

### Méthode 2 : Créer un token manuellement (pour test rapide)

```bash
php artisan tinker
```

Puis dans tinker:
```php
$user = App\Models\User::find(21); // ID du vendeur
$token = $user->createToken('test-token')->plainTextToken;
echo "Token: " . $token . PHP_EOL;
```

**Note:** Copiez le token généré, vous en aurez besoin pour les tests suivants.

---

## Test 2 : Voir les informations actuelles de la boutique

```bash
curl -X GET http://localhost:8000/api/v1/vendor/shop \
  -H "Authorization: Bearer VOTRE_TOKEN_ICI" \
  -H "Accept: application/json"
```

**Résultat attendu:**
```json
{
  "success": true,
  "shop": {
    "id": 10,
    "name": "Buzzx Shop",
    "latitude": 3.86842205,
    "longitude": 11.52820377,
    ...
  },
  "stats": { ... }
}
```

---

## Test 3 : Mise à jour simple (sans changement d'emplacement)

```bash
curl -X PUT http://localhost:8000/api/v1/vendor/shop \
  -H "Authorization: Bearer VOTRE_TOKEN_ICI" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "shop_name": "Ma Boutique Modifiée",
    "shop_description": "Nouvelle description de test",
    "shop_phone": "+237600111222"
  }'
```

**Résultat attendu:**
```json
{
  "success": true,
  "message": "Boutique mise à jour avec succès",
  "shop": {
    "id": 10,
    "name": "Ma Boutique Modifiée",
    "description": "Nouvelle description de test",
    "phone": "+237600111222",
    "latitude": 3.86842205,  // Inchangé
    "longitude": 11.52820377  // Inchangé
  }
}
```

**Vérification dans les logs:**
```bash
tail -f storage/logs/laravel.log
```

Vous devriez voir:
```
[VENDOR-SHOP-UPDATE] Starting vendor shop update
[VENDOR-SHOP-UPDATE] Updating shop name
[VENDOR-SHOP-UPDATE] Updating shop with data
[VENDOR-SHOP-UPDATE] Shop updated successfully
```

---

## Test 4 : Demande de changement d'emplacement seul

```bash
curl -X PUT http://localhost:8000/api/v1/vendor/shop \
  -H "Authorization: Bearer VOTRE_TOKEN_ICI" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "shop_latitude": 3.8480,
    "shop_longitude": 11.5021,
    "location_change_reason": "Déménagement de la boutique vers un nouveau quartier"
  }'
```

**Résultat attendu:**
```json
{
  "success": true,
  "message": "Boutique mise à jour avec succès. Votre demande de changement d'emplacement a été soumise et sera validée par un administrateur.",
  "shop": {
    "latitude": 3.86842205,  // Ancienne position (pas encore changée)
    "longitude": 11.52820377
  },
  "location_request": {
    "id": 1,
    "latitude": 3.8480,      // Nouvelle position demandée
    "longitude": 11.5021,
    "status": "pending",
    "created_at": "2026-04-14T..."
  }
}
```

**Vérification dans la base de données:**
```bash
php artisan tinker --execute="
\$request = App\Models\ShopLocationRequest::latest()->first();
echo 'ID: ' . \$request->id . PHP_EOL;
echo 'Shop ID: ' . \$request->shop_id . PHP_EOL;
echo 'Latitude demandée: ' . \$request->latitude . PHP_EOL;
echo 'Longitude demandée: ' . \$request->longitude . PHP_EOL;
echo 'Status: ' . \$request->status . PHP_EOL;
echo 'Raison: ' . \$request->reason . PHP_EOL;
"
```

---

## Test 5 : Mise à jour combinée (infos + emplacement) - LE TEST PRINCIPAL

```bash
curl -X PUT http://localhost:8000/api/v1/vendor/shop \
  -H "Authorization: Bearer VOTRE_TOKEN_ICI" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "shop_name": "Boutique Relocalisée",
    "shop_description": "Nous avons déménagé!",
    "shop_phone": "+237655443322",
    "shop_latitude": 3.8700,
    "shop_longitude": 11.5100,
    "location_change_reason": "Nouveau local plus grand"
  }'
```

**Résultat attendu:**
```json
{
  "success": true,
  "message": "Boutique mise à jour avec succès. Votre demande de changement d'emplacement a été soumise et sera validée par un administrateur.",
  "shop": {
    "name": "Boutique Relocalisée",        // ✅ Mis à jour immédiatement
    "description": "Nous avons déménagé!",  // ✅ Mis à jour immédiatement
    "phone": "+237655443322",               // ✅ Mis à jour immédiatement
    "latitude": 3.86842205,                 // ❌ Ancienne valeur
    "longitude": 11.52820377                // ❌ Ancienne valeur
  },
  "location_request": {
    "id": 2,
    "latitude": 3.8700,                     // ⏳ En attente
    "longitude": 11.5100,                   // ⏳ En attente
    "status": "pending"
  }
}
```

---

## Test 6 : Consulter l'historique des demandes

```bash
curl -X GET http://localhost:8000/api/v1/vendor/shop/location-requests \
  -H "Authorization: Bearer VOTRE_TOKEN_ICI" \
  -H "Accept: application/json"
```

**Résultat attendu:**
```json
{
  "success": true,
  "requests": [
    {
      "id": 2,
      "latitude": 3.8700,
      "longitude": 11.5100,
      "status": "pending",
      "reason": "Nouveau local plus grand",
      "created_at": "..."
    },
    {
      "id": 1,
      "latitude": 3.8480,
      "longitude": 11.5021,
      "status": "pending",
      "reason": "Déménagement...",
      "created_at": "..."
    }
  ],
  "pending_count": 2
}
```

---

## Test 7 : Tenter une nouvelle demande alors qu'une est en attente

```bash
curl -X PUT http://localhost:8000/api/v1/vendor/shop \
  -H "Authorization: Bearer VOTRE_TOKEN_ICI" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "shop_latitude": 3.9000,
    "shop_longitude": 11.6000,
    "location_change_reason": "Encore un autre déménagement"
  }'
```

**Résultat attendu:**
```json
{
  "success": false,
  "message": "Vous avez déjà une demande de changement d'emplacement en attente",
  "pending_request": {
    "id": 2,
    "latitude": 3.8700,
    "longitude": 11.5100,
    "status": "pending",
    "created_at": "..."
  }
}
```

---

## Test 8 : ADMIN - Voir toutes les demandes

D'abord, créer un token admin:
```bash
php artisan tinker
```
```php
$admin = App\Models\User::where('role', 'admin')->first();
$adminToken = $admin->createToken('admin-test-token')->plainTextToken;
echo "Admin Token: " . $adminToken . PHP_EOL;
```

Puis:
```bash
curl -X GET http://localhost:8000/api/v1/admin/shop-location-requests \
  -H "Authorization: Bearer ADMIN_TOKEN_ICI" \
  -H "Accept: application/json"
```

**Résultat attendu:**
```json
{
  "success": true,
  "requests": [
    {
      "id": 2,
      "shop": {
        "id": 10,
        "name": "Boutique Relocalisée",
        "current_latitude": 3.86842205,
        "current_longitude": 11.52820377
      },
      "vendor": {
        "id": 21,
        "name": "Stevo Bou",
        "phone": "+237..."
      },
      "requested_latitude": 3.8700,
      "requested_longitude": 11.5100,
      "status": "pending",
      "reason": "Nouveau local plus grand"
    }
  ],
  "counts": {
    "pending": 2,
    "approved": 0,
    "rejected": 0
  }
}
```

---

## Test 9 : ADMIN - Approuver une demande

```bash
curl -X POST http://localhost:8000/api/v1/admin/shop-location-requests/1/approve \
  -H "Authorization: Bearer ADMIN_TOKEN_ICI" \
  -H "Accept: application/json"
```

**Résultat attendu:**
```json
{
  "success": true,
  "message": "Demande de changement d'emplacement approuvée avec succès",
  "request": {
    "id": 1,
    "status": "approved",
    "reviewed_at": "2026-04-14T..."
  },
  "shop": {
    "id": 10,
    "latitude": 3.8480,      // ✅ Position mise à jour!
    "longitude": 11.5021     // ✅ Position mise à jour!
  }
}
```

**Vérification:**
```bash
php artisan tinker --execute="
\$shop = App\Models\Shop::find(10);
echo 'Position actuelle: ' . \$shop->latitude . ', ' . \$shop->longitude . PHP_EOL;
"
```

---

## Test 10 : ADMIN - Rejeter une demande

```bash
curl -X POST http://localhost:8000/api/v1/admin/shop-location-requests/2/reject \
  -H "Authorization: Bearer ADMIN_TOKEN_ICI" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "rejection_reason": "L'\''emplacement demandé est hors de la zone de service"
  }'
```

**Résultat attendu:**
```json
{
  "success": true,
  "message": "Demande de changement d'emplacement rejetée",
  "request": {
    "id": 2,
    "status": "rejected",
    "rejection_reason": "L'emplacement demandé est hors de la zone de service",
    "reviewed_at": "2026-04-14T..."
  }
}
```

---

## Test 11 : Vérifier que le vendeur peut maintenant faire une nouvelle demande

Après qu'une demande ait été approuvée ou rejetée:

```bash
curl -X PUT http://localhost:8000/api/v1/vendor/shop \
  -H "Authorization: Bearer VOTRE_TOKEN_ICI" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "shop_latitude": 3.9000,
    "shop_longitude": 11.6000,
    "location_change_reason": "Nouvelle demande"
  }'
```

**Résultat attendu:** Succès (car il n'y a plus de demande "pending")

---

## Vérification des logs en temps réel

Dans un terminal séparé:
```bash
tail -f storage/logs/laravel.log | grep VENDOR-SHOP-UPDATE
```

Ou pour les demandes admin:
```bash
tail -f storage/logs/laravel.log | grep ADMIN-LOCATION-REQUEST
```

---

## Commandes utiles pour réinitialiser les tests

```bash
# Supprimer toutes les demandes de changement d'emplacement
php artisan tinker --execute="App\Models\ShopLocationRequest::truncate();"

# Réinitialiser la position d'une boutique
php artisan tinker --execute="
\$shop = App\Models\Shop::find(10);
\$shop->update(['latitude' => 3.86842205, 'longitude' => 11.52820377]);
echo 'Position réinitialisée' . PHP_EOL;
"
```

---

## Checklist de test

- [ ] Test 1: Obtenir un token vendeur
- [ ] Test 2: Voir les infos de la boutique
- [ ] Test 3: Mise à jour simple (nom, description, téléphone)
- [ ] Test 4: Demande de changement d'emplacement seul
- [ ] Test 5: **Mise à jour combinée** (le test principal)
- [ ] Test 6: Voir l'historique des demandes
- [ ] Test 7: Vérifier le blocage de demande en double
- [ ] Test 8: Admin voir toutes les demandes
- [ ] Test 9: Admin approuver une demande
- [ ] Test 10: Admin rejeter une demande
- [ ] Test 11: Nouvelle demande après approbation/rejet

**Le Test 5 est le plus important car il valide votre demande principale : mettre à jour les infos normales ET soumettre une demande d'emplacement en même temps!**
