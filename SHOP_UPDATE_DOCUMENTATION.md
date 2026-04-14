# Documentation - Mise à jour des boutiques vendeur

## Résumé des changements

Le système de mise à jour des boutiques a été modifié pour permettre aux vendeurs de mettre à jour leurs informations de boutique tout en contrôlant les changements d'emplacement via l'administration.

## Fonctionnement

### 1. Mise à jour normale (sans changement d'emplacement)

Les vendeurs peuvent mettre à jour directement ces champs :
- `shop_name` - Nom de la boutique
- `shop_description` - Description
- `shop_address` - Adresse textuelle (sans coordonnées GPS)
- `shop_phone` - Numéro de téléphone
- `shop_logo` - Logo (image)
- `categories` - Catégories

**Endpoint**: `PUT /api/v1/vendor/shop`

**Exemple de requête**:
```json
{
  "shop_name": "Ma Super Boutique",
  "shop_description": "Description de ma boutique",
  "shop_address": "123 Rue Exemple, Yaoundé",
  "shop_phone": "+237600000000",
  "categories": ["électronique", "mode"]
}
```

**Réponse de succès**:
```json
{
  "success": true,
  "message": "Boutique mise à jour avec succès",
  "shop": { ... },
  "stats": { ... }
}
```

### 2. Demande de changement d'emplacement GPS

Lorsqu'un vendeur souhaite modifier la position GPS de sa boutique (latitude/longitude), une demande est créée et soumise à l'administrateur pour approbation.

**Endpoint**: `PUT /api/v1/vendor/shop`

**Exemple de requête (seulement emplacement)**:
```json
{
  "shop_latitude": 3.8480,
  "shop_longitude": 11.5021,
  "shop_address": "Nouvelle adresse, Yaoundé",
  "location_change_reason": "Déménagement de la boutique"
}
```

**Exemple de requête (emplacement + autres informations)**:
```json
{
  "shop_name": "Ma Boutique Déménagée",
  "shop_description": "Nouvelle description",
  "shop_phone": "+237600000001",
  "shop_latitude": 3.8480,
  "shop_longitude": 11.5021,
  "shop_address": "Nouvelle adresse, Yaoundé",
  "location_change_reason": "Déménagement de la boutique"
}
```

**Réponse de succès (avec demande d'emplacement)**:
```json
{
  "success": true,
  "message": "Boutique mise à jour avec succès. Votre demande de changement d'emplacement a été soumise et sera validée par un administrateur.",
  "shop": { ... },
  "stats": { ... },
  "location_request": {
    "id": 1,
    "latitude": 3.8480,
    "longitude": 11.5021,
    "status": "pending",
    "created_at": "2026-04-13T23:45:19.000000Z"
  }
}
```

**Comportement important**:
- Si le vendeur envoie à la fois des informations normales ET un changement d'emplacement :
  1. Les informations normales (nom, description, téléphone, etc.) sont mises à jour immédiatement
  2. Une demande de changement d'emplacement est créée pour validation admin
  3. La réponse contient à la fois les données mises à jour et les informations de la demande

**Note**: Si une demande d'emplacement est déjà en attente et qu'il n'y a pas d'autres champs à mettre à jour, le système retournera une erreur 422. Si d'autres champs sont présents, ils seront mis à jour et la demande d'emplacement en attente sera ignorée avec un avertissement dans les logs.

### 3. Consulter ses demandes de changement d'emplacement

Le vendeur peut consulter l'historique de toutes ses demandes.

**Endpoint**: `GET /api/v1/vendor/shop/location-requests`

**Réponse**:
```json
{
  "success": true,
  "requests": [
    {
      "id": 1,
      "latitude": 3.8480,
      "longitude": 11.5021,
      "address": "Nouvelle adresse",
      "reason": "Déménagement",
      "status": "pending",
      "rejection_reason": null,
      "reviewed_by": null,
      "reviewed_at": null,
      "created_at": "2026-04-13T23:45:19.000000Z"
    }
  ],
  "pending_count": 1
}
```

Les statuts possibles sont :
- `pending` - En attente de validation
- `approved` - Approuvée (l'emplacement a été mis à jour)
- `rejected` - Rejetée (avec raison du rejet)

## Routes Admin

### 1. Lister toutes les demandes

**Endpoint**: `GET /api/v1/admin/shop-location-requests`

**Filtres disponibles** (query params):
- `status` - Filtrer par statut (pending, approved, rejected)
- `shop_id` - Filtrer par boutique
- `vendor_id` - Filtrer par vendeur

**Réponse**:
```json
{
  "success": true,
  "requests": [ ... ],
  "pagination": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 5
  },
  "counts": {
    "pending": 2,
    "approved": 2,
    "rejected": 1
  }
}
```

### 2. Voir les détails d'une demande

**Endpoint**: `GET /api/v1/admin/shop-location-requests/{id}`

### 3. Approuver une demande

**Endpoint**: `POST /api/v1/admin/shop-location-requests/{id}/approve`

Cette action :
1. Met à jour la position GPS de la boutique
2. Marque la demande comme approuvée
3. Enregistre l'administrateur qui a approuvé

### 4. Rejeter une demande

**Endpoint**: `POST /api/v1/admin/shop-location-requests/{id}/reject`

**Body**:
```json
{
  "rejection_reason": "L'emplacement demandé est hors de la zone de service"
}
```

## Base de données

### Table: `shop_location_requests`

| Colonne | Type | Description |
|---------|------|-------------|
| id | bigint | ID de la demande |
| shop_id | bigint | ID de la boutique |
| vendor_id | bigint | ID du vendeur |
| latitude | decimal(10,8) | Latitude demandée |
| longitude | decimal(11,8) | Longitude demandée |
| address | string | Adresse demandée |
| reason | text | Raison de la demande |
| status | enum | pending, approved, rejected |
| reviewed_by | bigint | ID de l'admin qui a traité |
| rejection_reason | text | Raison du rejet |
| reviewed_at | timestamp | Date de traitement |
| created_at | timestamp | Date de création |
| updated_at | timestamp | Date de modification |

## Logs

Tous les événements sont loggés avec les préfixes suivants :
- `[VENDOR-SHOP-UPDATE]` - Mise à jour de boutique par vendeur
- `[ADMIN-LOCATION-REQUEST]` - Traitement de demande par admin

## Sécurité

1. Les vendeurs ne peuvent modifier que leur propre boutique
2. Seuls les administrateurs peuvent approuver/rejeter les demandes
3. Une seule demande en attente par boutique à la fois
4. Les coordonnées GPS sont validées (latitude: -90 à 90, longitude: -180 à 180)
