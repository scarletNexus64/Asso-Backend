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
        ];

        foreach ($legalPages as $page) {
            LegalPage::updateOrCreate(
                ['slug' => $page['slug']],
                $page
            );
        }

        $this->command->info('Legal pages seeded successfully!');
    }

    /**
     * Contenu des Conditions Générales d'Utilisation.
     */
    private function getCGUContent(): string
    {
        return <<<HTML
<h2>1. Objet</h2>
<p>Les présentes Conditions Générales d'Utilisation (CGU) ont pour objet de définir les modalités et conditions d'utilisation de la plateforme ASSO, ainsi que les droits et obligations des utilisateurs.</p>

<h2>2. Acceptation des CGU</h2>
<p>L'accès et l'utilisation de la plateforme ASSO impliquent l'acceptation pleine et entière des présentes CGU. Si vous n'acceptez pas ces conditions, veuillez ne pas utiliser nos services.</p>

<h2>3. Inscription et Compte Utilisateur</h2>
<p>Pour accéder à certaines fonctionnalités de la plateforme, vous devez créer un compte utilisateur. Vous vous engagez à fournir des informations exactes et à les maintenir à jour.</p>

<h2>4. Utilisation de la Plateforme</h2>
<p>Vous vous engagez à utiliser la plateforme ASSO de manière responsable et conforme aux lois en vigueur. Toute utilisation frauduleuse ou abusive est strictement interdite.</p>

<h2>5. Transactions et Paiements</h2>
<p>Les transactions effectuées sur la plateforme sont sécurisées. ASSO agit en tant qu'intermédiaire et ne peut être tenu responsable des litiges entre utilisateurs.</p>

<h2>6. Propriété Intellectuelle</h2>
<p>Tous les contenus présents sur la plateforme ASSO (textes, images, logos, etc.) sont protégés par les droits de propriété intellectuelle et sont la propriété exclusive d'ASSO ou de ses partenaires.</p>

<h2>7. Responsabilité</h2>
<p>ASSO s'efforce de maintenir la plateforme accessible 24h/24 et 7j/7, mais ne peut garantir une disponibilité continue. ASSO ne pourra être tenu responsable des dommages directs ou indirects résultant de l'utilisation de la plateforme.</p>

<h2>8. Modification des CGU</h2>
<p>ASSO se réserve le droit de modifier les présentes CGU à tout moment. Les utilisateurs seront informés de toute modification substantielle.</p>

<h2>9. Droit Applicable</h2>
<p>Les présentes CGU sont régies par le droit béninois. Tout litige sera soumis aux tribunaux compétents du Bénin.</p>

<h2>10. Contact</h2>
<p>Pour toute question concernant ces CGU, vous pouvez nous contacter à l'adresse : contact@asso.com</p>
HTML;
    }

    /**
     * Contenu de la Politique de Confidentialité.
     */
    private function getPrivacyPolicyContent(): string
    {
        return <<<HTML
<h2>1. Introduction</h2>
<p>ASSO accorde une grande importance à la protection de vos données personnelles. Cette politique de confidentialité explique comment nous collectons, utilisons et protégeons vos informations.</p>

<h2>2. Données Collectées</h2>
<p>Nous collectons les données suivantes :</p>
<ul>
    <li>Informations d'identification (nom, prénom, email, téléphone)</li>
    <li>Informations de paiement (traitées de manière sécurisée par nos partenaires)</li>
    <li>Données de navigation (cookies, adresse IP, historique de navigation)</li>
    <li>Informations relatives à vos transactions et échanges</li>
</ul>

<h2>3. Utilisation des Données</h2>
<p>Vos données sont utilisées pour :</p>
<ul>
    <li>Gérer votre compte et vos transactions</li>
    <li>Améliorer nos services</li>
    <li>Vous envoyer des communications importantes</li>
    <li>Assurer la sécurité de la plateforme</li>
    <li>Respecter nos obligations légales</li>
</ul>

<h2>4. Partage des Données</h2>
<p>Nous ne partageons vos données qu'avec :</p>
<ul>
    <li>Nos prestataires de services (paiement, hébergement, etc.)</li>
    <li>Les autorités légales si requis par la loi</li>
</ul>
<p>Nous ne vendons jamais vos données personnelles à des tiers.</p>

<h2>5. Sécurité des Données</h2>
<p>Nous mettons en œuvre des mesures de sécurité techniques et organisationnelles appropriées pour protéger vos données contre tout accès non autorisé, perte ou destruction.</p>

<h2>6. Vos Droits</h2>
<p>Conformément à la réglementation en vigueur, vous disposez des droits suivants :</p>
<ul>
    <li>Droit d'accès à vos données</li>
    <li>Droit de rectification</li>
    <li>Droit à l'effacement</li>
    <li>Droit à la portabilité</li>
    <li>Droit d'opposition</li>
</ul>

<h2>7. Cookies</h2>
<p>Nous utilisons des cookies pour améliorer votre expérience sur notre plateforme. Vous pouvez configurer votre navigateur pour refuser les cookies.</p>

<h2>8. Conservation des Données</h2>
<p>Vos données sont conservées pendant la durée nécessaire aux finalités pour lesquelles elles ont été collectées, et conformément aux obligations légales.</p>

<h2>9. Modifications</h2>
<p>Nous nous réservons le droit de modifier cette politique de confidentialité à tout moment. Toute modification sera publiée sur cette page.</p>

<h2>10. Contact</h2>
<p>Pour toute question concernant cette politique de confidentialité ou pour exercer vos droits, contactez-nous à : privacy@asso.com</p>
HTML;
    }

    /**
     * Contenu des Mentions Légales.
     */
    private function getLegalNoticeContent(): string
    {
        return <<<HTML
<h2>1. Informations Légales</h2>
<p>La plateforme ASSO est éditée par :</p>
<ul>
    <li><strong>Raison sociale :</strong> ASSO SARL</li>
    <li><strong>Adresse :</strong> Cotonou, Bénin</li>
    <li><strong>Email :</strong> contact@asso.com</li>
    <li><strong>Téléphone :</strong> +229 XX XX XX XX</li>
</ul>

<h2>2. Directeur de Publication</h2>
<p>Le directeur de la publication est [Nom du Directeur], en sa qualité de [Fonction].</p>

<h2>3. Hébergement</h2>
<p>La plateforme ASSO est hébergée par :</p>
<ul>
    <li><strong>Raison sociale :</strong> [Nom de l'hébergeur]</li>
    <li><strong>Adresse :</strong> [Adresse de l'hébergeur]</li>
    <li><strong>Téléphone :</strong> [Téléphone de l'hébergeur]</li>
</ul>

<h2>4. Propriété Intellectuelle</h2>
<p>L'ensemble des contenus présents sur la plateforme ASSO (textes, images, graphismes, logo, icônes, etc.) est la propriété exclusive d'ASSO, à l'exception des marques, logos ou contenus appartenant à d'autres sociétés partenaires ou auteurs.</p>
<p>Toute reproduction, distribution, modification, adaptation, retransmission ou publication de ces différents éléments est strictement interdite sans l'accord exprès par écrit d'ASSO.</p>

<h2>5. Responsabilité</h2>
<p>ASSO s'efforce d'assurer au mieux de ses possibilités l'exactitude et la mise à jour des informations diffusées sur la plateforme.</p>
<p>Toutefois, ASSO ne peut garantir l'exactitude, la précision ou l'exhaustivité des informations mises à disposition sur cette plateforme.</p>

<h2>6. Données Personnelles</h2>
<p>Pour plus d'informations sur la gestion de vos données personnelles, veuillez consulter notre <a href="/legal/politique-confidentialite">Politique de Confidentialité</a>.</p>

<h2>7. Cookies</h2>
<p>La plateforme ASSO utilise des cookies pour améliorer l'expérience utilisateur. En poursuivant votre navigation, vous acceptez l'utilisation de cookies.</p>

<h2>8. Droit Applicable</h2>
<p>Les présentes mentions légales sont régies par le droit béninois. En cas de litige, les tribunaux du Bénin seront seuls compétents.</p>

<h2>9. Contact</h2>
<p>Pour toute question ou réclamation, vous pouvez nous contacter :</p>
<ul>
    <li><strong>Par email :</strong> contact@asso.com</li>
    <li><strong>Par téléphone :</strong> +229 XX XX XX XX</li>
    <li><strong>Par courrier :</strong> ASSO SARL, Cotonou, Bénin</li>
</ul>
HTML;
    }
}
