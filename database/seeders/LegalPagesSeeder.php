<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LegalPage;

class LegalPagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $legalPages = [
            [
                'slug' => 'conditions-generales-utilisation',
                'title' => 'Conditions Générales d\'Utilisation',
                'content' => $this->getCGUContent(),
                'is_active' => true,
                'order' => 1,
            ],
            [
                'slug' => 'politique-confidentialite',
                'title' => 'Politique de Confidentialité',
                'content' => $this->getPrivacyPolicyContent(),
                'is_active' => true,
                'order' => 2,
            ],
            [
                'slug' => 'mentions-legales',
                'title' => 'Mentions Légales',
                'content' => $this->getLegalNoticeContent(),
                'is_active' => true,
                'order' => 3,
            ],
            [
                'slug' => 'conditions-generales-vente',
                'title' => 'Conditions Générales de Vente',
                'content' => $this->getCGVContent(),
                'is_active' => true,
                'order' => 4,
            ],
            [
                'slug' => 'politique-cookies',
                'title' => 'Politique de Cookies',
                'content' => $this->getCookiesPolicyContent(),
                'is_active' => true,
                'order' => 5,
            ],
            [
                'slug' => 'politique-remboursement',
                'title' => 'Politique de Remboursement et Retours',
                'content' => $this->getRefundPolicyContent(),
                'is_active' => true,
                'order' => 6,
            ],
            [
                'slug' => 'charte-vendeurs',
                'title' => 'Charte des Vendeurs',
                'content' => $this->getSellerCharterContent(),
                'is_active' => true,
                'order' => 7,
            ],
            [
                'slug' => 'regles-communaute',
                'title' => 'Règles de la Communauté',
                'content' => $this->getCommunityRulesContent(),
                'is_active' => true,
                'order' => 8,
            ],
        ];

        foreach ($legalPages as $page) {
            LegalPage::updateOrCreate(
                ['slug' => $page['slug']],
                $page
            );
        }

        $this->command->info('✅ ' . count($legalPages) . ' pages légales créées/mises à jour avec succès !');
    }

    private function getCGUContent(): string
    {
        return <<<'HTML'
<h2>1. Objet</h2>
<p>Les présentes Conditions Générales d'Utilisation (ci-après « CGU ») ont pour objet de définir les modalités et conditions d'accès et d'utilisation de la plateforme <strong>ASSO</strong> (ci-après « la Plateforme »), accessible via l'application mobile et le site web. En accédant à la Plateforme, l'utilisateur reconnaît avoir pris connaissance des présentes CGU et les accepte sans réserve.</p>

<h2>2. Définitions</h2>
<ul>
    <li><strong>Plateforme :</strong> désigne l'ensemble des services proposés par ASSO, incluant l'application mobile et le site web.</li>
    <li><strong>Utilisateur :</strong> toute personne physique ou morale qui accède à la Plateforme et utilise ses services.</li>
    <li><strong>Vendeur :</strong> utilisateur disposant d'une boutique sur la Plateforme et proposant des produits ou services à la vente.</li>
    <li><strong>Acheteur :</strong> utilisateur qui achète ou souhaite acheter des produits ou services sur la Plateforme.</li>
    <li><strong>Compte :</strong> espace personnel de l'utilisateur créé lors de son inscription sur la Plateforme.</li>
    <li><strong>Boutique :</strong> espace commercial virtuel du Vendeur sur la Plateforme.</li>
</ul>

<h2>3. Inscription et Compte Utilisateur</h2>
<h3>3.1 Création de compte</h3>
<p>L'inscription sur la Plateforme est gratuite et ouverte à toute personne physique âgée d'au moins 18 ans ou à toute personne morale légalement constituée. L'utilisateur s'engage à fournir des informations exactes, complètes et à les maintenir à jour.</p>

<h3>3.2 Sécurité du compte</h3>
<p>L'utilisateur est responsable de la confidentialité de ses identifiants de connexion. Toute activité réalisée à partir de son compte est réputée effectuée par lui. En cas de suspicion d'utilisation frauduleuse, l'utilisateur doit immédiatement en informer ASSO.</p>

<h3>3.3 Vérification par OTP</h3>
<p>L'authentification se fait par vérification OTP (One Time Password) envoyé par SMS ou WhatsApp au numéro de téléphone renseigné. L'utilisateur certifie être le titulaire légitime du numéro de téléphone utilisé.</p>

<h2>4. Services de la Plateforme</h2>
<h3>4.1 Marketplace</h3>
<p>ASSO met à disposition une place de marché permettant aux Vendeurs de proposer leurs produits et services, et aux Acheteurs de les consulter et de les acquérir.</p>

<h3>4.2 Messagerie</h3>
<p>La Plateforme dispose d'un système de messagerie intégré permettant aux utilisateurs de communiquer entre eux dans le cadre de transactions. Tout usage de la messagerie à des fins illicites, de harcèlement ou de spam est strictement interdit.</p>

<h3>4.3 Système de livraison</h3>
<p>ASSO propose un réseau de livreurs partenaires pour assurer la livraison des produits. Les frais de livraison sont calculés selon la zone géographique et le poids du colis.</p>

<h3>4.4 Portefeuille électronique</h3>
<p>Chaque utilisateur dispose d'un portefeuille intégré permettant de recevoir des paiements, effectuer des dépôts et des retraits. Les transactions sont sécurisées et traçables.</p>

<h2>5. Obligations des Utilisateurs</h2>
<p>L'utilisateur s'engage à :</p>
<ul>
    <li>Utiliser la Plateforme conformément à sa destination et aux lois en vigueur au Bénin et dans les pays de la zone UEMOA.</li>
    <li>Ne pas publier de contenus illicites, diffamatoires, discriminatoires ou contraires aux bonnes mœurs.</li>
    <li>Ne pas tenter de contourner les mécanismes de sécurité de la Plateforme.</li>
    <li>Respecter les droits de propriété intellectuelle des tiers.</li>
    <li>Ne pas utiliser la Plateforme pour des activités de blanchiment d'argent ou de financement illicite.</li>
</ul>

<h2>6. Rôle d'ASSO</h2>
<p>ASSO agit en qualité d'intermédiaire technique entre les Vendeurs et les Acheteurs. ASSO n'est pas partie aux contrats de vente conclus entre les utilisateurs. À ce titre, ASSO ne saurait être tenu responsable de la qualité, de la conformité ou de la livraison des produits vendus par les Vendeurs.</p>

<h2>7. Propriété Intellectuelle</h2>
<p>L'ensemble des éléments constituant la Plateforme (textes, images, logos, interfaces, base de données, code source, etc.) sont protégés par le droit de la propriété intellectuelle. Toute reproduction, représentation ou exploitation non autorisée est constitutive de contrefaçon.</p>

<h2>8. Suspension et Résiliation</h2>
<p>ASSO se réserve le droit de suspendre ou de résilier le compte de tout utilisateur en cas de :</p>
<ul>
    <li>Violation des présentes CGU.</li>
    <li>Comportement frauduleux ou suspect.</li>
    <li>Non-paiement ou litige financier non résolu.</li>
    <li>Atteinte à l'image ou à la réputation de la Plateforme.</li>
</ul>

<h2>9. Limitation de Responsabilité</h2>
<p>ASSO ne pourra être tenu responsable des dommages directs ou indirects résultant de l'utilisation ou de l'impossibilité d'utilisation de la Plateforme, incluant notamment les pertes de données, les pertes financières ou les interruptions de service.</p>

<h2>10. Modification des CGU</h2>
<p>ASSO se réserve le droit de modifier les présentes CGU à tout moment. Les utilisateurs seront informés de toute modification par notification dans l'application. La poursuite de l'utilisation de la Plateforme après notification vaut acceptation des nouvelles CGU.</p>

<h2>11. Droit Applicable et Juridiction</h2>
<p>Les présentes CGU sont régies par le droit de la République du Bénin et les actes uniformes de l'OHADA. Tout litige relatif à leur interprétation ou leur exécution sera soumis à la compétence exclusive des tribunaux de Cotonou, Bénin.</p>

<h2>12. Contact</h2>
<p>Pour toute question relative aux présentes CGU, vous pouvez nous contacter :</p>
<ul>
    <li><strong>Email :</strong> support@asso.com</li>
    <li><strong>Téléphone :</strong> +229 97 00 00 00</li>
</ul>
<p><em>Dernière mise à jour : Avril 2026</em></p>
HTML;
    }

    private function getPrivacyPolicyContent(): string
    {
        return <<<'HTML'
<h2>1. Introduction</h2>
<p>La société <strong>ASSO</strong> (ci-après « nous », « notre » ou « ASSO ») accorde une importance primordiale à la protection de vos données personnelles. La présente Politique de Confidentialité décrit les types de données que nous collectons, la manière dont nous les utilisons, les partageons et les protégeons lorsque vous utilisez notre Plateforme.</p>

<h2>2. Responsable du Traitement</h2>
<p>Le responsable du traitement des données personnelles est :</p>
<ul>
    <li><strong>ASSO SARL</strong></li>
    <li>Siège social : Cotonou, Bénin</li>
    <li>Email DPO : privacy@asso.com</li>
</ul>

<h2>3. Données Collectées</h2>
<h3>3.1 Données fournies directement</h3>
<ul>
    <li><strong>Identification :</strong> nom, prénom, genre, photo de profil.</li>
    <li><strong>Coordonnées :</strong> numéro de téléphone, adresse email, pays, ville.</li>
    <li><strong>Données commerciales :</strong> informations de boutique, produits, historique de transactions.</li>
    <li><strong>Données financières :</strong> solde du portefeuille, historique des dépôts/retraits (les données de paiement sensibles sont traitées par nos prestataires certifiés).</li>
</ul>

<h3>3.2 Données collectées automatiquement</h3>
<ul>
    <li><strong>Données techniques :</strong> adresse IP, type d'appareil, système d'exploitation, identifiant unique de l'appareil (FCM token).</li>
    <li><strong>Données de géolocalisation :</strong> coordonnées GPS pour la localisation des boutiques et les livraisons (avec votre consentement).</li>
    <li><strong>Données de navigation :</strong> pages visitées, durée des sessions, interactions.</li>
</ul>

<h2>4. Finalités du Traitement</h2>
<p>Vos données sont utilisées pour les finalités suivantes :</p>
<ul>
    <li>Création et gestion de votre compte utilisateur.</li>
    <li>Traitement des transactions commerciales et des paiements.</li>
    <li>Fourniture des services de livraison et géolocalisation des boutiques.</li>
    <li>Envoi de notifications push (promotions, mises à jour de commandes, alertes de sécurité).</li>
    <li>Amélioration continue de nos services et personnalisation de l'expérience utilisateur.</li>
    <li>Prévention de la fraude et sécurisation de la Plateforme.</li>
    <li>Conformité avec nos obligations légales et réglementaires.</li>
    <li>Gestion du système d'affiliation et calcul des commissions.</li>
</ul>

<h2>5. Base Légale du Traitement</h2>
<ul>
    <li><strong>Exécution du contrat :</strong> traitement nécessaire à la fourniture de nos services.</li>
    <li><strong>Consentement :</strong> pour la géolocalisation, les notifications push et les communications marketing.</li>
    <li><strong>Intérêt légitime :</strong> amélioration des services, prévention de la fraude.</li>
    <li><strong>Obligation légale :</strong> conformité fiscale et réglementaire.</li>
</ul>

<h2>6. Partage des Données</h2>
<p>Nous pouvons partager vos données avec :</p>
<ul>
    <li><strong>Vendeurs/Acheteurs :</strong> informations nécessaires à la réalisation des transactions (nom, téléphone pour la livraison).</li>
    <li><strong>Livreurs partenaires :</strong> adresse de livraison et informations de contact pour assurer la livraison.</li>
    <li><strong>Prestataires de paiement :</strong> pour le traitement sécurisé des transactions financières.</li>
    <li><strong>Prestataires techniques :</strong> hébergement, notifications push (Firebase/FCM), services d'IA (Gemini).</li>
    <li><strong>Autorités compétentes :</strong> en cas d'obligation légale ou de réquisition judiciaire.</li>
</ul>
<p><strong>Nous ne vendons jamais vos données personnelles à des tiers.</strong></p>

<h2>7. Transferts Internationaux</h2>
<p>Certaines de vos données peuvent être transférées et traitées dans des pays hors du Bénin, notamment pour l'hébergement cloud et les services de notification. Nous veillons à ce que ces transferts soient encadrés par des garanties appropriées.</p>

<h2>8. Sécurité des Données</h2>
<p>Nous mettons en œuvre des mesures de sécurité robustes :</p>
<ul>
    <li>Chiffrement des communications (HTTPS/TLS).</li>
    <li>Hachage des mots de passe (bcrypt avec 12 rounds).</li>
    <li>Authentification par token sécurisé (Sanctum).</li>
    <li>Surveillance continue des accès et détection des anomalies.</li>
    <li>Sauvegardes régulières et chiffrées.</li>
</ul>

<h2>9. Conservation des Données</h2>
<ul>
    <li><strong>Données de compte :</strong> conservées pendant toute la durée de votre inscription, puis 3 ans après la suppression du compte.</li>
    <li><strong>Données de transaction :</strong> conservées 10 ans conformément aux obligations comptables.</li>
    <li><strong>Données de connexion :</strong> conservées 1 an.</li>
    <li><strong>Données de géolocalisation :</strong> conservées 6 mois.</li>
</ul>

<h2>10. Vos Droits</h2>
<p>Conformément à la réglementation applicable, vous disposez des droits suivants :</p>
<ul>
    <li><strong>Droit d'accès :</strong> obtenir la confirmation que vos données sont traitées et en obtenir une copie.</li>
    <li><strong>Droit de rectification :</strong> corriger vos données inexactes ou incomplètes.</li>
    <li><strong>Droit à l'effacement :</strong> demander la suppression de vos données (sous réserve des obligations légales).</li>
    <li><strong>Droit à la portabilité :</strong> recevoir vos données dans un format structuré et lisible.</li>
    <li><strong>Droit d'opposition :</strong> vous opposer au traitement de vos données pour des motifs légitimes.</li>
    <li><strong>Droit de retrait du consentement :</strong> retirer votre consentement à tout moment.</li>
</ul>
<p>Pour exercer ces droits, contactez-nous à : <strong>privacy@asso.com</strong></p>

<h2>11. Modifications</h2>
<p>Nous nous réservons le droit de modifier la présente politique. Toute modification significative sera notifiée via l'application.</p>

<p><em>Dernière mise à jour : Avril 2026</em></p>
HTML;
    }

    private function getLegalNoticeContent(): string
    {
        return <<<'HTML'
<h2>1. Éditeur de la Plateforme</h2>
<p>La plateforme <strong>ASSO</strong> est éditée par :</p>
<ul>
    <li><strong>Raison sociale :</strong> ASSO SARL</li>
    <li><strong>Forme juridique :</strong> Société à Responsabilité Limitée</li>
    <li><strong>Capital social :</strong> [À compléter] FCFA</li>
    <li><strong>Siège social :</strong> Cotonou, République du Bénin</li>
    <li><strong>RCCM :</strong> [Numéro RCCM]</li>
    <li><strong>IFU :</strong> [Numéro IFU]</li>
    <li><strong>Email :</strong> contact@asso.com</li>
    <li><strong>Téléphone :</strong> +229 97 00 00 00</li>
</ul>

<h2>2. Directeur de Publication</h2>
<p>Le directeur de la publication est le gérant de la société ASSO SARL.</p>

<h2>3. Hébergement</h2>
<p>La plateforme ASSO est hébergée par :</p>
<ul>
    <li><strong>Hébergeur :</strong> [Nom de l'hébergeur]</li>
    <li><strong>Adresse :</strong> [Adresse de l'hébergeur]</li>
    <li><strong>Contact :</strong> [Contact de l'hébergeur]</li>
</ul>

<h2>4. Propriété Intellectuelle</h2>
<p>L'ensemble des éléments constituant la plateforme ASSO — incluant sans limitation les textes, images, graphismes, logos, icônes, sons, logiciels, bases de données, interfaces et code source — est la propriété exclusive d'ASSO ou fait l'objet d'une autorisation d'utilisation.</p>
<p>Toute reproduction, représentation, modification, publication, adaptation, exploitation totale ou partielle de ces éléments, par quelque procédé que ce soit, est interdite sauf autorisation écrite préalable d'ASSO. Toute exploitation non autorisée constitue un acte de contrefaçon sanctionné par les articles applicables du Code de la Propriété Intellectuelle et les actes uniformes de l'OHADA.</p>

<h2>5. Responsabilité</h2>
<p>ASSO met tout en œuvre pour assurer l'exactitude et la mise à jour des informations publiées sur la Plateforme. Toutefois, ASSO ne saurait garantir l'exhaustivité, l'exactitude ou la complétude des informations mises à disposition et décline toute responsabilité en cas d'erreur ou d'omission.</p>
<p>ASSO ne saurait être tenu responsable des contenus publiés par les utilisateurs (descriptions de produits, images, avis, messages). Les Vendeurs sont seuls responsables des informations qu'ils publient.</p>

<h2>6. Liens Hypertextes</h2>
<p>La Plateforme peut contenir des liens hypertextes vers d'autres sites web. ASSO ne dispose d'aucun contrôle sur ces sites et décline toute responsabilité quant à leur contenu ou leurs pratiques en matière de protection des données.</p>

<h2>7. Données Personnelles</h2>
<p>Pour toute information relative à la collecte et au traitement de vos données personnelles, veuillez consulter notre <strong>Politique de Confidentialité</strong>.</p>

<h2>8. Médiation</h2>
<p>En cas de litige, l'utilisateur peut recourir à un médiateur de la consommation avant toute action judiciaire. Les coordonnées du médiateur seront communiquées sur demande.</p>

<h2>9. Droit Applicable</h2>
<p>Les présentes mentions légales sont régies par le droit de la République du Bénin et les actes uniformes de l'OHADA. Tout litige sera soumis à la compétence exclusive des tribunaux de Cotonou.</p>

<p><em>Dernière mise à jour : Avril 2026</em></p>
HTML;
    }

    private function getCGVContent(): string
    {
        return <<<'HTML'
<h2>1. Objet</h2>
<p>Les présentes Conditions Générales de Vente (ci-après « CGV ») régissent les relations contractuelles entre les Vendeurs et les Acheteurs sur la plateforme <strong>ASSO</strong>. Elles s'appliquent à toute commande passée via la Plateforme.</p>

<h2>2. Produits et Services</h2>
<h3>2.1 Descriptions</h3>
<p>Les Vendeurs s'engagent à décrire leurs produits et services de manière exacte, complète et non trompeuse. Les photographies illustratives doivent refléter fidèlement le produit proposé.</p>

<h3>2.2 Disponibilité</h3>
<p>Les produits sont proposés dans la limite des stocks disponibles. En cas d'indisponibilité d'un produit après commande, l'Acheteur sera informé dans les plus brefs délais et pourra obtenir un remboursement intégral.</p>

<h3>2.3 Produits interdits</h3>
<p>Sont strictement interdits à la vente sur la Plateforme :</p>
<ul>
    <li>Les produits contrefaits ou portant atteinte aux droits de propriété intellectuelle.</li>
    <li>Les substances illicites, les armes et les produits dangereux.</li>
    <li>Les médicaments soumis à prescription médicale.</li>
    <li>Tout produit contraire aux lois en vigueur au Bénin.</li>
</ul>

<h2>3. Prix</h2>
<h3>3.1 Affichage</h3>
<p>Les prix sont affichés en <strong>Francs CFA (XOF)</strong>. Ils sont fixés librement par les Vendeurs et incluent toutes les taxes applicables. Les frais de livraison sont indiqués séparément.</p>

<h3>3.2 Prix variable</h3>
<p>Certains produits peuvent avoir un prix variable (fourchette de prix). Le prix final est déterminé en fonction des options choisies par l'Acheteur.</p>

<h2>4. Commande</h2>
<h3>4.1 Processus de commande</h3>
<ol>
    <li>L'Acheteur sélectionne le(s) produit(s) souhaité(s).</li>
    <li>Il choisit l'option de livraison et confirme l'adresse.</li>
    <li>Il procède au paiement via le portefeuille ASSO ou les moyens de paiement disponibles.</li>
    <li>La commande est confirmée par notification.</li>
</ol>

<h3>4.2 Validation</h3>
<p>La commande est considérée comme validée après confirmation du paiement. Le Vendeur reçoit une notification et dispose d'un délai pour accepter ou refuser la commande.</p>

<h2>5. Paiement</h2>
<p>Les paiements sont effectués via :</p>
<ul>
    <li><strong>Portefeuille ASSO :</strong> solde du compte utilisateur.</li>
    <li><strong>Mobile Money :</strong> MTN MoMo, Moov Money, Celtiis Cash.</li>
    <li><strong>Autres :</strong> moyens de paiement intégrés par nos partenaires financiers.</li>
</ul>
<p>Le montant de la transaction est débité du compte de l'Acheteur et crédité sur le portefeuille du Vendeur après confirmation de la livraison.</p>

<h2>6. Livraison</h2>
<h3>6.1 Zones de livraison</h3>
<p>La livraison est assurée par les livreurs partenaires d'ASSO dans les zones couvertes. Les zones et tarifs de livraison sont affichés lors de la commande.</p>

<h3>6.2 Délais</h3>
<p>Les délais de livraison sont indicatifs et dépendent de la localisation du Vendeur et de l'Acheteur. ASSO met tout en œuvre pour assurer des livraisons rapides.</p>

<h3>6.3 Réception</h3>
<p>L'Acheteur doit vérifier l'état du colis à la réception. Toute réserve doit être signalée immédiatement via l'application.</p>

<h2>7. Commissions</h2>
<p>ASSO prélève une commission sur chaque transaction réalisée. Le taux de commission est communiqué aux Vendeurs lors de leur inscription et peut varier selon les catégories de produits et les packages souscrits.</p>

<h2>8. Droit de Rétractation</h2>
<p>Conformément aux dispositions légales applicables, l'Acheteur dispose d'un droit de rétractation dans les conditions définies par notre <strong>Politique de Remboursement et Retours</strong>.</p>

<h2>9. Garanties</h2>
<p>Les Vendeurs professionnels sont tenus de respecter les garanties légales de conformité et de vices cachés. Les modalités de mise en œuvre de ces garanties sont précisées dans les fiches produits.</p>

<h2>10. Litiges</h2>
<p>En cas de litige entre un Vendeur et un Acheteur, les parties sont invitées à rechercher une solution amiable via le système de support d'ASSO. À défaut de résolution amiable, les tribunaux compétents de Cotonou seront saisis.</p>

<p><em>Dernière mise à jour : Avril 2026</em></p>
HTML;
    }

    private function getCookiesPolicyContent(): string
    {
        return <<<'HTML'
<h2>1. Qu'est-ce qu'un Cookie ?</h2>
<p>Un cookie est un petit fichier texte déposé sur votre terminal (ordinateur, smartphone, tablette) lors de votre visite sur notre site web. Il permet de stocker des informations relatives à votre navigation afin d'améliorer votre expérience utilisateur.</p>

<h2>2. Types de Cookies Utilisés</h2>

<h3>2.1 Cookies strictement nécessaires</h3>
<p>Ces cookies sont indispensables au fonctionnement de la Plateforme. Ils permettent notamment :</p>
<ul>
    <li>La gestion de votre session de connexion.</li>
    <li>La sécurisation de votre navigation (protection CSRF).</li>
    <li>La mémorisation de vos préférences de confidentialité.</li>
</ul>
<p><em>Ces cookies ne peuvent pas être désactivés car ils sont essentiels au fonctionnement du service.</em></p>

<h3>2.2 Cookies de performance et d'analyse</h3>
<p>Ces cookies nous permettent de mesurer l'audience de notre Plateforme et d'analyser les comportements de navigation afin d'améliorer nos services :</p>
<ul>
    <li>Nombre de visiteurs et pages les plus consultées.</li>
    <li>Durée moyenne des sessions.</li>
    <li>Taux de rebond et parcours utilisateur.</li>
</ul>

<h3>2.3 Cookies de fonctionnalité</h3>
<p>Ces cookies permettent de personnaliser votre expérience :</p>
<ul>
    <li>Mémorisation de vos préférences de langue et de devise.</li>
    <li>Sauvegarde de vos recherches récentes.</li>
    <li>Adaptation de l'interface à votre appareil.</li>
</ul>

<h2>3. Cookies Tiers</h2>
<p>Certains cookies proviennent de services tiers intégrés à notre Plateforme :</p>
<ul>
    <li><strong>Firebase :</strong> pour les notifications push et l'analyse de performance.</li>
    <li><strong>Google Maps :</strong> pour la géolocalisation des boutiques et des livraisons.</li>
</ul>

<h2>4. Durée de Conservation</h2>
<table>
    <thead>
        <tr><th>Type de cookie</th><th>Durée</th></tr>
    </thead>
    <tbody>
        <tr><td>Cookie de session</td><td>Jusqu'à fermeture du navigateur</td></tr>
        <tr><td>Cookie d'authentification</td><td>120 minutes (configurable)</td></tr>
        <tr><td>Cookie de préférences</td><td>1 an</td></tr>
        <tr><td>Cookie d'analyse</td><td>13 mois maximum</td></tr>
    </tbody>
</table>

<h2>5. Gestion des Cookies</h2>
<p>Vous pouvez à tout moment modifier vos préférences en matière de cookies :</p>
<ul>
    <li><strong>Via votre navigateur :</strong> la plupart des navigateurs permettent de bloquer ou supprimer les cookies dans leurs paramètres.</li>
    <li><strong>Via notre application :</strong> les paramètres de notification et de géolocalisation sont gérés dans les réglages de votre appareil.</li>
</ul>
<p><strong>Attention :</strong> la désactivation de certains cookies peut affecter le fonctionnement de la Plateforme.</p>

<h2>6. Contact</h2>
<p>Pour toute question relative aux cookies, contactez-nous à : <strong>privacy@asso.com</strong></p>

<p><em>Dernière mise à jour : Avril 2026</em></p>
HTML;
    }

    private function getRefundPolicyContent(): string
    {
        return <<<'HTML'
<h2>1. Champ d'Application</h2>
<p>La présente politique de remboursement et de retours s'applique à toutes les transactions effectuées sur la plateforme <strong>ASSO</strong>. Elle définit les conditions dans lesquelles un Acheteur peut obtenir un remboursement ou retourner un produit.</p>

<h2>2. Droit de Rétractation</h2>
<h3>2.1 Délai</h3>
<p>L'Acheteur dispose d'un délai de <strong>48 heures</strong> à compter de la réception du produit pour exercer son droit de rétractation, sans avoir à justifier de motif.</p>

<h3>2.2 Exceptions</h3>
<p>Le droit de rétractation ne s'applique pas aux :</p>
<ul>
    <li>Produits périssables ou alimentaires.</li>
    <li>Produits personnalisés ou fabriqués sur mesure.</li>
    <li>Services déjà exécutés (réparations, installations).</li>
    <li>Produits descellés pour des raisons d'hygiène (cosmétiques, sous-vêtements).</li>
    <li>Contenus numériques téléchargés.</li>
</ul>

<h2>3. Conditions de Retour</h2>
<p>Pour être accepté, le retour doit respecter les conditions suivantes :</p>
<ul>
    <li>Le produit doit être dans son état d'origine, non utilisé et dans son emballage d'origine.</li>
    <li>Tous les accessoires, étiquettes et documents doivent être inclus.</li>
    <li>La demande de retour doit être initiée via l'application ASSO.</li>
    <li>Des photos du produit à retourner doivent être fournies.</li>
</ul>

<h2>4. Procédure de Retour</h2>
<ol>
    <li><strong>Signalement :</strong> L'Acheteur signale le problème via l'application dans les 48h suivant la réception.</li>
    <li><strong>Évaluation :</strong> Le Vendeur et/ou l'équipe ASSO évaluent la demande.</li>
    <li><strong>Validation :</strong> Si la demande est acceptée, les instructions de retour sont communiquées.</li>
    <li><strong>Retour :</strong> L'Acheteur renvoie le produit selon les instructions.</li>
    <li><strong>Vérification :</strong> Le Vendeur vérifie l'état du produit retourné.</li>
    <li><strong>Remboursement :</strong> Le remboursement est effectué après validation.</li>
</ol>

<h2>5. Motifs de Remboursement</h2>
<p>Un remboursement est accordé dans les cas suivants :</p>
<ul>
    <li><strong>Produit non conforme :</strong> le produit reçu ne correspond pas à la description.</li>
    <li><strong>Produit endommagé :</strong> le produit est arrivé endommagé ou défectueux.</li>
    <li><strong>Produit non reçu :</strong> le produit n'a pas été livré dans le délai annoncé.</li>
    <li><strong>Erreur de commande :</strong> le Vendeur a envoyé un produit différent.</li>
    <li><strong>Rétractation :</strong> dans le délai légal et les conditions prévues.</li>
</ul>

<h2>6. Modalités de Remboursement</h2>
<ul>
    <li>Le remboursement est effectué sur le <strong>portefeuille ASSO</strong> de l'Acheteur.</li>
    <li>Le délai de traitement est de <strong>3 à 7 jours ouvrés</strong> après validation.</li>
    <li>Les frais de livraison sont remboursés uniquement si le retour est dû à une faute du Vendeur.</li>
</ul>

<h2>7. Frais de Retour</h2>
<ul>
    <li>Si le retour est dû à une faute du Vendeur (produit non conforme, défectueux) : frais de retour à la charge du <strong>Vendeur</strong>.</li>
    <li>Si le retour est dû à un changement d'avis de l'Acheteur : frais de retour à la charge de l'<strong>Acheteur</strong>.</li>
</ul>

<h2>8. Litiges</h2>
<p>En cas de désaccord entre le Vendeur et l'Acheteur, l'équipe support d'ASSO intervient en tant que médiateur. La décision d'ASSO est définitive et s'impose aux deux parties.</p>

<h2>9. Contact</h2>
<p>Pour toute question ou réclamation : <strong>support@asso.com</strong></p>

<p><em>Dernière mise à jour : Avril 2026</em></p>
HTML;
    }

    private function getSellerCharterContent(): string
    {
        return <<<'HTML'
<h2>1. Engagement du Vendeur</h2>
<p>En devenant Vendeur sur la plateforme <strong>ASSO</strong>, vous vous engagez à respecter la présente Charte. Elle a pour objectif de garantir une expérience d'achat de qualité pour tous les utilisateurs et de maintenir la confiance au sein de la communauté.</p>

<h2>2. Création et Gestion de Boutique</h2>
<h3>2.1 Vérification</h3>
<p>Chaque boutique est soumise à un processus de vérification par l'équipe ASSO avant activation. Le Vendeur doit fournir :</p>
<ul>
    <li>Une identité vérifiable (pièce d'identité valide).</li>
    <li>Un numéro de téléphone actif et vérifié.</li>
    <li>Une description complète de son activité commerciale.</li>
    <li>Un logo ou une photo représentative de sa boutique.</li>
</ul>

<h3>2.2 Certification</h3>
<p>Les Vendeurs peuvent obtenir une certification (Bronze, Silver, Gold) leur conférant des avantages supplémentaires : meilleure visibilité, badge de confiance, limites de produits étendues, et support prioritaire.</p>

<h2>3. Qualité des Produits</h2>
<p>Le Vendeur s'engage à :</p>
<ul>
    <li>Proposer uniquement des produits légaux, authentiques et conformes aux normes en vigueur.</li>
    <li>Fournir des descriptions précises, détaillées et non trompeuses.</li>
    <li>Utiliser des photographies réelles et représentatives du produit.</li>
    <li>Maintenir ses stocks à jour pour éviter les annulations de commandes.</li>
    <li>Indiquer clairement le prix, les conditions de vente et les délais de livraison.</li>
</ul>

<h2>4. Service Client</h2>
<p>Le Vendeur doit :</p>
<ul>
    <li>Répondre aux messages des Acheteurs dans un délai de <strong>24 heures</strong>.</li>
    <li>Traiter les commandes dans un délai de <strong>48 heures</strong> après réception.</li>
    <li>Gérer les retours et réclamations de manière professionnelle et courtoise.</li>
    <li>Maintenir un taux de satisfaction client acceptable.</li>
</ul>

<h2>5. Tarification</h2>
<ul>
    <li>Les prix doivent être justes, compétitifs et refléter la valeur réelle du produit.</li>
    <li>Les prix gonflés artificiellement pour compenser les commissions sont interdits.</li>
    <li>Toute pratique de prix trompeur (fausse promotion, prix barré fictif) est interdite.</li>
</ul>

<h2>6. Commissions et Packages</h2>
<p>Le Vendeur s'acquitte des commissions ASSO sur chaque vente réalisée. Des packages (Stockage, Boost, Certification) sont disponibles pour optimiser la visibilité et les performances de sa boutique.</p>

<h2>7. Comportements Interdits</h2>
<p>Sont strictement interdits :</p>
<ul>
    <li>La vente de produits contrefaits, volés ou illicites.</li>
    <li>Les pratiques commerciales trompeuses ou déloyales.</li>
    <li>Le harcèlement ou l'intimidation des Acheteurs.</li>
    <li>La manipulation des avis et évaluations.</li>
    <li>La tentative de réaliser des transactions en dehors de la Plateforme pour éviter les commissions.</li>
    <li>La création de plusieurs boutiques pour contourner les restrictions.</li>
</ul>

<h2>8. Sanctions</h2>
<p>En cas de non-respect de la présente Charte, ASSO se réserve le droit de :</p>
<ul>
    <li>Émettre un avertissement formel.</li>
    <li>Suspendre temporairement la boutique.</li>
    <li>Révoquer la certification du Vendeur.</li>
    <li>Fermer définitivement la boutique et le compte du Vendeur.</li>
    <li>Retenir les fonds en cas de litige avéré.</li>
</ul>

<h2>9. Contact</h2>
<p>Pour toute question : <strong>vendeurs@asso.com</strong></p>

<p><em>Dernière mise à jour : Avril 2026</em></p>
HTML;
    }

    private function getCommunityRulesContent(): string
    {
        return <<<'HTML'
<h2>1. Notre Vision</h2>
<p><strong>ASSO</strong> est une communauté commerciale bâtie sur la confiance, le respect mutuel et l'entraide. Ces règles ont pour objectif de créer un environnement sûr et agréable pour tous les membres de notre communauté.</p>

<h2>2. Respect et Courtoisie</h2>
<ul>
    <li>Traitez tous les membres de la communauté avec respect et dignité.</li>
    <li>Adoptez un langage courtois et professionnel dans toutes vos interactions.</li>
    <li>Respectez les différences culturelles, religieuses et d'opinion.</li>
    <li>Évitez les propos offensants, discriminatoires, sexistes ou racistes.</li>
</ul>

<h2>3. Communication</h2>
<h3>3.1 Messagerie</h3>
<ul>
    <li>Utilisez la messagerie uniquement dans le cadre de transactions commerciales ou de questions liées aux produits.</li>
    <li>Ne partagez pas d'informations personnelles sensibles (mots de passe, données bancaires) via la messagerie.</li>
    <li>Le spam, la publicité non sollicitée et les chaînes de messages sont interdits.</li>
</ul>

<h3>3.2 Avis et Évaluations</h3>
<ul>
    <li>Rédigez des avis honnêtes et constructifs basés sur votre expérience réelle.</li>
    <li>Ne publiez pas de faux avis positifs sur vos propres produits.</li>
    <li>Ne publiez pas de faux avis négatifs sur les produits de concurrents.</li>
    <li>Les avis contenant des propos injurieux ou diffamatoires seront supprimés.</li>
</ul>

<h2>4. Contenu</h2>
<h3>4.1 Contenu autorisé</h3>
<ul>
    <li>Photos et descriptions de produits authentiques.</li>
    <li>Informations commerciales pertinentes.</li>
    <li>Questions et réponses relatives aux produits et services.</li>
</ul>

<h3>4.2 Contenu interdit</h3>
<ul>
    <li>Contenu à caractère pornographique ou sexuellement explicite.</li>
    <li>Contenu incitant à la violence, à la haine ou au terrorisme.</li>
    <li>Contenu portant atteinte aux droits de propriété intellectuelle.</li>
    <li>Informations personnelles de tiers sans leur consentement.</li>
    <li>Publicité pour des activités illégales.</li>
    <li>Désinformation ou fausses informations.</li>
</ul>

<h2>5. Transactions</h2>
<ul>
    <li>Effectuez toutes vos transactions via la Plateforme pour bénéficier de la protection ASSO.</li>
    <li>Ne demandez pas aux autres membres de payer en dehors de la Plateforme.</li>
    <li>Signalez immédiatement toute tentative de fraude ou d'arnaque.</li>
    <li>Respectez vos engagements : si vous acceptez une commande, honorez-la.</li>
</ul>

<h2>6. Signalement</h2>
<p>Si vous êtes témoin d'un comportement contraire à ces règles, signalez-le immédiatement via :</p>
<ul>
    <li>Le bouton « Signaler » disponible dans l'application.</li>
    <li>Le système de tickets de support.</li>
    <li>L'email : <strong>abuse@asso.com</strong></li>
</ul>
<p>Tous les signalements sont traités de manière confidentielle.</p>

<h2>7. Sanctions</h2>
<p>Le non-respect de ces règles peut entraîner :</p>
<ul>
    <li><strong>1er manquement :</strong> avertissement par notification.</li>
    <li><strong>2e manquement :</strong> suspension temporaire du compte (7 jours).</li>
    <li><strong>3e manquement :</strong> suspension prolongée (30 jours).</li>
    <li><strong>Manquement grave :</strong> suppression définitive du compte sans préavis.</li>
</ul>
<p>ASSO se réserve le droit d'adapter les sanctions en fonction de la gravité des faits.</p>

<h2>8. Évolution des Règles</h2>
<p>Ces règles peuvent évoluer pour s'adapter aux besoins de la communauté. Les modifications seront communiquées via l'application.</p>

<p><em>Ensemble, construisons une communauté commerciale de confiance !</em></p>
<p><em>Dernière mise à jour : Avril 2026</em></p>
HTML;
    }
}
