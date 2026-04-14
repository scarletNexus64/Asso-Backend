#!/bin/bash

# Script de test automatique pour la mise à jour des boutiques
# Assurez-vous que votre serveur Laravel est en cours d'exécution

# Couleurs pour l'affichage
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

BASE_URL="http://localhost:8000/api/v1"
TOKEN="13|PAjp5tUtaCcAHKTY4COYoY312HBoBCljDATZiRi32ed0be64"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}TEST DE MISE À JOUR DES BOUTIQUES${NC}"
echo -e "${BLUE}========================================${NC}\n"

# Test 1: Voir les informations actuelles
echo -e "${YELLOW}Test 1: Récupération des informations de la boutique${NC}"
curl -s -X GET "${BASE_URL}/vendor/shop" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Accept: application/json" | jq '.'
echo -e "\n"

# Test 2: Mise à jour simple (sans emplacement)
echo -e "${YELLOW}Test 2: Mise à jour simple (nom + description)${NC}"
curl -s -X PUT "${BASE_URL}/vendor/shop" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "shop_name": "Buzzx Shop - Test Modifié",
    "shop_description": "Description de test mise à jour"
  }' | jq '.success, .message, .shop.name, .shop.description'
echo -e "\n"

# Test 3: MISE À JOUR COMBINÉE (le test principal!)
echo -e "${YELLOW}Test 3: MISE À JOUR COMBINÉE - Infos + Demande d'emplacement${NC}"
echo -e "${GREEN}(C'est le test principal que vous vouliez!)${NC}"
curl -s -X PUT "${BASE_URL}/vendor/shop" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "shop_name": "Buzzx Shop Relocalisée",
    "shop_description": "Nous avons déménagé dans un nouveau local!",
    "shop_phone": "+237655443322",
    "shop_latitude": 3.8700,
    "shop_longitude": 11.5100,
    "location_change_reason": "Nouveau local plus spacieux"
  }' | jq '.'
echo -e "\n"

# Test 4: Vérifier les demandes créées
echo -e "${YELLOW}Test 4: Consultation de l'historique des demandes${NC}"
curl -s -X GET "${BASE_URL}/vendor/shop/location-requests" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Accept: application/json" | jq '.success, .pending_count, .requests'
echo -e "\n"

echo -e "${BLUE}========================================${NC}"
echo -e "${GREEN}Tests terminés!${NC}"
echo -e "${BLUE}========================================${NC}"
echo -e "\n${YELLOW}Notes:${NC}"
echo -e "1. Vérifiez que dans le Test 3, les champs nom/description/phone ont été mis à jour immédiatement"
echo -e "2. Vérifiez qu'une demande d'emplacement (location_request) a été créée avec status='pending'"
echo -e "3. Vérifiez que latitude/longitude dans 'shop' ont gardé leur ancienne valeur"
echo -e "4. Consultez les logs: ${BLUE}tail -f storage/logs/laravel.log${NC}"
