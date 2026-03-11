<p align="center">
  <img src="./public/logo/Asso.png" width="200" alt="ASSO Logo">
</p>

<h1 align="center">ASSO - Backend API</h1>

<p align="center">
  <strong>API Backend pour la plateforme d'association communautaire</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12.0-FF2D20?style=flat&logo=laravel" alt="Laravel">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat&logo=php" alt="PHP">
  <img src="https://img.shields.io/badge/License-MIT-green.svg" alt="License">
</p>

## À propos

ASSO est une plateforme complète de gestion d'association offrant un ensemble de fonctionnalités pour faciliter la vie associative. Cette API backend alimentée par Laravel fournit tous les services nécessaires pour une gestion moderne et efficace.

## Fonctionnalités principales

### Commerce & Échanges
- **Boutique en ligne** - Gestion complète de produits avec catégories et sous-catégories
- **Système d'échange** - Plateforme d'échange entre membres
- **Transactions** - Suivi et gestion des transactions
- **Avis produits** - Système de notation et commentaires

### Communication
- **Messagerie** - Conversations privées entre membres
- **Confessions** - Système de confessions anonymes avec likes et favoris
- **Annonces** - Diffusion d'annonces à la communauté
- **Bannières** - Gestion des bannières promotionnelles

### Gestion des membres
- **Authentification** - Système sécurisé avec Sanctum
- **Profils utilisateurs** - Gestion complète des profils
- **Programme d'affiliation** - Système de parrainage avec commissions
- **Packages** - Gestion des abonnements et packages

### Administration
- **Dashboard** - Tableau de bord administrateur complet
- **Support** - Système de tickets de support
- **Documents** - Gestionnaire de documents avec catégories
- **Coffre-fort** - Stockage sécurisé des credentials
- **Pages légales** - Gestion des CGU, politique de confidentialité, etc.
- **Mode maintenance** - Activation/désactivation du mode maintenance
- **Paramètres** - Configuration centralisée par groupes

### Outils
- **Base de données** - Gestion et sauvegarde
- **Carte interactive** - Visualisation géographique
- **Clics contacts** - Tracking des interactions

## Prérequis

- PHP >= 8.2
- Composer
- Node.js & NPM
- MySQL ou PostgreSQL
- Extension PHP requises: PDO, Mbstring, OpenSSL, Tokenizer, XML, Ctype, JSON

## Installation

### 1. Cloner le repository

```bash
git clone <repository-url>
cd ASSO
```

### 2. Installation automatique

```bash
composer run setup
```

Cette commande exécute automatiquement:
- Installation des dépendances Composer
- Copie du fichier `.env.example` vers `.env`
- Génération de la clé d'application
- Exécution des migrations
- Installation des dépendances NPM
- Build des assets

### 3. Configuration

Éditez le fichier `.env` avec vos paramètres:

```env
APP_NAME=ASSO
APP_ENV=local
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=asso_db
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Lancement du serveur de développement

```bash
composer run dev
```

Cette commande lance simultanément:
- Serveur Laravel (http://localhost:8000)
- Worker de queue
- Logs en temps réel (Pail)
- Vite dev server pour les assets

## Utilisation

### API Endpoints

#### Settings (Public)
```
GET /api/settings - Liste tous les paramètres
GET /api/settings/group/{group} - Paramètres par groupe
GET /api/settings/{key} - Paramètre spécifique
```

#### Confessions (Authentifié)
```
GET    /api/v1/confessions - Liste des confessions
POST   /api/v1/confessions - Créer une confession
GET    /api/v1/confessions/{id} - Détails d'une confession
PUT    /api/v1/confessions/{id} - Modifier une confession
DELETE /api/v1/confessions/{id} - Supprimer une confession
POST   /api/v1/confessions/{id}/favorite - Ajouter/retirer des favoris
POST   /api/v1/confessions/{id}/like - Liker une confession
POST   /api/v1/confessions/{id}/reveal-identity - Révéler son identité
```

### Scripts disponibles

```bash
# Installation complète
composer run setup

# Développement (tous les services)
composer run dev

# Tests
composer run test

# Formattage du code
./vendor/bin/pint
```

## Structure du projet

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/          # Contrôleurs admin
│   │   └── Api/            # Contrôleurs API
│   └── Middleware/         # Middlewares personnalisés
├── Models/                 # Modèles Eloquent
└── Helpers/               # Fonctions helper

database/
├── migrations/            # Migrations de base de données
└── seeders/              # Seeders

routes/
├── api.php               # Routes API
├── web.php               # Routes web
└── console.php           # Commandes Artisan

public/
└── logo/                 # Assets logo
```

## Sécurité

- Authentification via Laravel Sanctum
- Middleware de vérification du mode maintenance
- Validation des entrées utilisateur
- Protection CSRF
- Hashage sécurisé des mots de passe
- Stockage sécurisé des credentials

## Tests

Exécuter les tests:

```bash
composer run test
```

## Contribution

Les contributions sont les bienvenues! Veuillez suivre ces étapes:

1. Fork le projet
2. Créer une branche pour votre fonctionnalité (`git checkout -b feature/AmazingFeature`)
3. Commit vos changements (`git commit -m 'Add some AmazingFeature'`)
4. Push vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrir une Pull Request

## Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.
