"""Map the supplier's actual category hierarchy to the existing ASSO taxonomy."""
import re


def native_classification(record):
    path = record.get('category_path') or []
    if not path:
        return None
    top, full, leaf = path[0].lower(), ' '.join(path[1:]).lower(), path[-1].lower()
    title = record['title'].lower()
    def has(pattern):
        return bool(re.search(pattern, full))
    if top == 'apparel & accessories':
        if has('processing accessories'):
            return 29, 164, 'Accessoires de couture'
        if has('clothing accessories|headwear|scarves|hats|caps|belts'):
            return 2, 14, 'Accessoire de mode'
        if has('women') or re.search('abaya|kaftan|women|ladies', title):
            return 2, 9, 'Vêtement pour femme'
        return (2, 10, 'Vêtement pour enfant') if has('children|kids|boys|girls') else (2, 8, 'Vêtement pour homme ou mixte')
    if top == 'home & garden':
        if has('home textiles'):
            return (3, 16, 'Tapis et décoration textile') if has('carpet|rugs') else (3, 18, 'Linge de maison')
        if has('kitchenware|dinnerware|drinkware|coffee|wine set'):
            return 3, 17, 'Cuisine et arts de la table'
        return (3, 19, 'Jardinage') if has('garden supplies') else (3, 16, 'Maison, rangement et décoration')
    if top == 'consumer electronics':
        for pattern, sub, label in [('video games', 40, 'Jeux vidéo'), ('chargers|power supplies|phone case|cables & commonly|cable|stylus|power bank', 5, 'Accessoire électronique'), ('smart watches|smartwatches|smart watch', 6, 'Montre connectée'), ('earphone|earbud|headphone|headset', 7, 'Écouteurs et casque audio'), ('camera|photographic|gimbal', 4, 'Photo et vidéo'), ('projector|television|speaker|audio', 3, 'Télévision et audio'), ('mobile phones|smartphones|tablet', 2, 'Téléphone ou tablette'), ('computer|laptop|monitor', 1, 'Informatique')]:
            if has(pattern):
                return (6 if sub == 40 else 1), sub, label
        return 1, 5, 'Électronique et accessoires'
    if top == 'packaging & printing':
        return (3, 17, 'Gobelets et vaisselle') if 'cups' in leaf else (11, 69, 'Emballages et fournitures professionnelles')
    if top == 'mother, kids & toys':
        if has('shoes'):
            return 2, 11, 'Chaussures pour enfant'
        if has('clothing') and not has('doll'):
            return 12, 73, 'Vêtement bébé'
        for pattern, sub, label in [('stroller|car seat', 75, 'Poussette et siège bébé'), ('diaper|nappies', 76, 'Hygiène bébé'), ('feeding|baby bottle', 74, 'Alimentation bébé')]:
            if has(pattern):
                return 12, sub, label
        for pattern, sub, label in [('educational|learning|drawing toys', 48, 'Jouet éducatif ou créatif'), ('puzzle', 46, 'Puzzle'), ('doll|plush|stuffed|figurine|blind boxes', 47, 'Poupée, peluche et figurine')]:
            if has(pattern):
                return 7, sub, label
        return 7, None, 'Jeux et jouets'
    if top == 'jewelry, eyewear, watches & accessories':
        return (2, 14, 'Lunettes et accessoires') if has('eyewear') else (2, 13, 'Bijoux, montres et accessoires')
    if top == 'luggage, bags & cases':
        for pattern, sub, label in [('cosmetic|toiletry', 172, 'Trousse et rangement de voyage'), ('backpack', 169, 'Sac à dos'), ('suitcase|carry-ons|luggage sets', 168, 'Valise et bagage'), ('travel bag|duffle|duffel', 170, 'Sac de voyage')]:
            if has(pattern):
                return 30, sub, label
        return 2, 12, 'Sac et maroquinerie'
    if top == 'furniture':
        return (3, 20, 'Quincaillerie pour meubles') if has('hardware') else (3, 15, 'Mobilier')
    if top == 'shoes & accessories':
        return (14, 86, 'Sèche-chaussures') if has('dryers') else (2, 11, 'Chaussures et accessoires')
    if top == 'lights & lighting':
        return 3, 21, 'Éclairage'
    if top == 'beauty':
        for pattern, sub, label in [('perfume|fragrance', 31, 'Parfumerie'), ('hair|wig', 32, 'Coiffure et accessoires'), ('makeup|eyelash|nail', 30, 'Maquillage et accessoires')]:
            if has(pattern):
                return 5, sub, label
        return 5, 29, 'Soins et accessoires de beauté'
    if top == 'home appliances':
        if has('hair care|hair removal|trimmer|clipper|shaver'):
            return 5, 32, 'Appareil de coiffure et soin personnel'
        for pattern, sub, label in [('coffee', 89, 'Cafetière et accessoires'), ('vacuum|floor care|carpet cleaner', 87, 'Aspirateur et nettoyage'), ('fan|cooling|heater|air conditioner', 88, 'Ventilation et chauffage'), ('washing machines|refrigerator|freezer|dishwasher|large kitchen', 85, 'Gros électroménager')]:
            if has(pattern):
                return 14, sub, label
        return 14, 86, 'Petit électroménager'
    if top == 'tools & hardware':
        return (3, 19, 'Outils de jardin') if has('garden') else (3, 20, 'Outils et quincaillerie')
    if top == 'sports & entertainment':
        for pattern, sub, label in [('water sport', 24, 'Sports nautiques'), ('fitness|yoga|gym', 22, 'Fitness et musculation'), ('camp|hiking', 26, 'Camping et randonnée'), ('cycling', 25, 'Vélo et cyclisme')]:
            if has(pattern):
                return 4, sub, label
        return 4, 28, 'Équipement de sport'
    if top == 'sportswear & outdoor apparel':
        return (30, 170, 'Sac de sport') if has('bags') else (2, 9 if has('women') or 'women' in title else 8, 'Vêtement de sport')
    if top == 'construction & real estate':
        return (3, 16, 'Accessoire de salle de bain') if has('bathroom') else (3, 20, 'Bricolage et aménagement')
    if top == 'personal care & household cleaning':
        if has('laundry|household cleaning|cleaning supplies'):
            return 3, 16, 'Entretien de la maison'
        return (5, 32, 'Soin des cheveux') if has('hair') else (5, 29, 'Soin de la peau') if has('skin') else (5, 34, 'Hygiène et soin personnel')
    if top in ['vehicle parts & accessories', 'vehicle accessories, electronics & tools']:
        return (9, 60, 'Électronique automobile') if has('electronics|lighting|navigation|audio') else (9, 57, 'Moto et mobilité') if has('scooter|motorcycle|motorbike') else (9, 56, 'Accessoire automobile')
    if top == 'school & office supplies':
        return (11, 69, 'Matériel de bureau') if has('office equipment|calculator|binding') else (11, 67, 'Papeterie et fournitures')
    if top == 'fabric & textile raw material':
        return 29, 164, 'Tissu et fournitures de couture'
    if top in ['electronic components, accessories & telecommunications', 'renewable energy', 'electrical equipment & supplies']:
        return 1, 5, 'Équipement et accessoires électroniques'
    if top == 'pet supplies':
        return 10, 62 if has('cat') else 61, 'Accessoire pour animaux'
    if top == 'agriculture':
        return 8, 49, 'Produits alimentaires et agricoles'
    if top in ['medical devices & supplies', 'health care']:
        return 5, 35, 'Équipement et fournitures de santé'
    if top == 'industrial machinery' and has('paper'):
        return 11, 69, 'Matériel professionnel de papeterie'
    if top in ['testing instrument & equipment', 'industrial machinery', 'commercial equipment & machinery', 'material handling', 'rubber & plastics', 'chemicals']:
        return 3, 20, 'Matériel et fournitures professionnels'
    if top == 'security':
        return 1, 4, 'Caméra et sécurité'
    if top == 'gifts & crafts':
        return (11, 67, 'Papeterie et cadeaux de bureau') if has('notebook|office') else (3, 16, 'Cadeau et décoration')
    return None
