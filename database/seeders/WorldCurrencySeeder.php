<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

/**
 * Catalogue mondial des devises et de leurs pays, en français.
 *
 * Remplace l'ancien CurrencySeeder, qui ne couvrait que 16 devises et
 * 37 pays : l'écran de choix du pays paraissait incomplet aux nouveaux
 * utilisateurs. Les noms de pays restent en français, comme l'ancien
 * seeder, afin que les pays déjà enregistrés côté application (stockés
 * sous forme de texte) continuent de correspondre.
 *
 * Les taux de change ne sont volontairement pas touchés ici :
 * ExchangeRateService interroge une API live et ne retombe sur la table
 * qu'en secours.
 */
class WorldCurrencySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->currencies() as $data) {
            Currency::updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'symbol' => $data['symbol'],
                    'countries' => $data['countries'],
                    'is_active' => true,
                ]
            );
        }

        $countries = collect($this->currencies())->pluck('countries')->flatten()->unique();

        $this->command?->info(
            '   🌍 ' . count($this->currencies()) . ' devises et '
            . $countries->count() . ' pays insérés.'
        );
    }

    /**
     * @return array<int, array{code: string, name: string, symbol: string, countries: array<int, string>}>
     */
    private function currencies(): array
    {
        return [
            // ---------------------------------------------------------------
            // Afrique
            // ---------------------------------------------------------------
            ['code' => 'XOF', 'name' => 'Franc CFA (BCEAO)', 'symbol' => 'FCFA', 'countries' => ['Bénin', 'Burkina Faso', "Côte d'Ivoire", 'Guinée-Bissau', 'Mali', 'Niger', 'Sénégal', 'Togo']],
            ['code' => 'XAF', 'name' => 'Franc CFA (BEAC)', 'symbol' => 'FCFA', 'countries' => ['Cameroun', 'Centrafrique', 'Congo', 'Gabon', 'Guinée équatoriale', 'Tchad']],
            ['code' => 'DZD', 'name' => 'Dinar algérien', 'symbol' => 'DA', 'countries' => ['Algérie']],
            ['code' => 'AOA', 'name' => 'Kwanza angolais', 'symbol' => 'Kz', 'countries' => ['Angola']],
            ['code' => 'BWP', 'name' => 'Pula botswanais', 'symbol' => 'P', 'countries' => ['Botswana']],
            ['code' => 'BIF', 'name' => 'Franc burundais', 'symbol' => 'FBu', 'countries' => ['Burundi']],
            ['code' => 'CVE', 'name' => 'Escudo cap-verdien', 'symbol' => 'CVE', 'countries' => ['Cap-Vert']],
            ['code' => 'KMF', 'name' => 'Franc comorien', 'symbol' => 'CF', 'countries' => ['Comores']],
            ['code' => 'CDF', 'name' => 'Franc congolais', 'symbol' => 'FC', 'countries' => ['République démocratique du Congo']],
            ['code' => 'DJF', 'name' => 'Franc djiboutien', 'symbol' => 'Fdj', 'countries' => ['Djibouti']],
            ['code' => 'EGP', 'name' => 'Livre égyptienne', 'symbol' => 'E£', 'countries' => ['Égypte']],
            ['code' => 'ERN', 'name' => 'Nakfa érythréen', 'symbol' => 'Nfk', 'countries' => ['Érythrée']],
            ['code' => 'SZL', 'name' => 'Lilangeni swazi', 'symbol' => 'L', 'countries' => ['Eswatini']],
            ['code' => 'ETB', 'name' => 'Birr éthiopien', 'symbol' => 'Br', 'countries' => ['Éthiopie']],
            ['code' => 'GMD', 'name' => 'Dalasi gambien', 'symbol' => 'D', 'countries' => ['Gambie']],
            ['code' => 'GHS', 'name' => 'Cedi ghanéen', 'symbol' => 'GH₵', 'countries' => ['Ghana']],
            ['code' => 'GNF', 'name' => 'Franc guinéen', 'symbol' => 'FG', 'countries' => ['Guinée']],
            ['code' => 'KES', 'name' => 'Shilling kényan', 'symbol' => 'KSh', 'countries' => ['Kenya']],
            ['code' => 'LSL', 'name' => 'Loti lesothan', 'symbol' => 'L', 'countries' => ['Lesotho']],
            ['code' => 'LRD', 'name' => 'Dollar libérien', 'symbol' => 'L$', 'countries' => ['Liberia']],
            ['code' => 'LYD', 'name' => 'Dinar libyen', 'symbol' => 'LD', 'countries' => ['Libye']],
            ['code' => 'MGA', 'name' => 'Ariary malgache', 'symbol' => 'Ar', 'countries' => ['Madagascar']],
            ['code' => 'MWK', 'name' => 'Kwacha malawien', 'symbol' => 'MK', 'countries' => ['Malawi']],
            ['code' => 'MRU', 'name' => 'Ouguiya mauritanien', 'symbol' => 'UM', 'countries' => ['Mauritanie']],
            ['code' => 'MUR', 'name' => 'Roupie mauricienne', 'symbol' => '₨', 'countries' => ['Maurice']],
            ['code' => 'MAD', 'name' => 'Dirham marocain', 'symbol' => 'DH', 'countries' => ['Maroc']],
            ['code' => 'MZN', 'name' => 'Metical mozambicain', 'symbol' => 'MT', 'countries' => ['Mozambique']],
            ['code' => 'NAD', 'name' => 'Dollar namibien', 'symbol' => 'N$', 'countries' => ['Namibie']],
            ['code' => 'NGN', 'name' => 'Naira nigérian', 'symbol' => '₦', 'countries' => ['Nigeria']],
            ['code' => 'RWF', 'name' => 'Franc rwandais', 'symbol' => 'FRw', 'countries' => ['Rwanda']],
            ['code' => 'STN', 'name' => 'Dobra santoméen', 'symbol' => 'Db', 'countries' => ['Sao Tomé-et-Principe']],
            ['code' => 'SCR', 'name' => 'Roupie seychelloise', 'symbol' => 'SR', 'countries' => ['Seychelles']],
            ['code' => 'SLE', 'name' => 'Leone sierra-léonais', 'symbol' => 'Le', 'countries' => ['Sierra Leone']],
            ['code' => 'SOS', 'name' => 'Shilling somalien', 'symbol' => 'Sh', 'countries' => ['Somalie']],
            ['code' => 'ZAR', 'name' => 'Rand sud-africain', 'symbol' => 'R', 'countries' => ['Afrique du Sud']],
            ['code' => 'SSP', 'name' => 'Livre sud-soudanaise', 'symbol' => 'SS£', 'countries' => ['Soudan du Sud']],
            ['code' => 'SDG', 'name' => 'Livre soudanaise', 'symbol' => 'SDG', 'countries' => ['Soudan']],
            ['code' => 'TZS', 'name' => 'Shilling tanzanien', 'symbol' => 'TSh', 'countries' => ['Tanzanie']],
            ['code' => 'TND', 'name' => 'Dinar tunisien', 'symbol' => 'DT', 'countries' => ['Tunisie']],
            ['code' => 'UGX', 'name' => 'Shilling ougandais', 'symbol' => 'USh', 'countries' => ['Ouganda']],
            ['code' => 'ZMW', 'name' => 'Kwacha zambien', 'symbol' => 'ZK', 'countries' => ['Zambie']],
            ['code' => 'ZWG', 'name' => 'Zimbabwe Gold', 'symbol' => 'ZiG', 'countries' => ['Zimbabwe']],

            // ---------------------------------------------------------------
            // Europe
            // ---------------------------------------------------------------
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'countries' => ['Allemagne', 'Andorre', 'Autriche', 'Belgique', 'Chypre', 'Croatie', 'Espagne', 'Estonie', 'Finlande', 'France', 'Grèce', 'Irlande', 'Italie', 'Lettonie', 'Lituanie', 'Luxembourg', 'Malte', 'Monaco', 'Monténégro', 'Pays-Bas', 'Portugal', 'Saint-Marin', 'Slovaquie', 'Slovénie', 'Vatican']],
            ['code' => 'GBP', 'name' => 'Livre sterling', 'symbol' => '£', 'countries' => ['Royaume-Uni']],
            ['code' => 'CHF', 'name' => 'Franc suisse', 'symbol' => 'CHF', 'countries' => ['Suisse', 'Liechtenstein']],
            ['code' => 'NOK', 'name' => 'Couronne norvégienne', 'symbol' => 'kr', 'countries' => ['Norvège']],
            ['code' => 'SEK', 'name' => 'Couronne suédoise', 'symbol' => 'kr', 'countries' => ['Suède']],
            ['code' => 'DKK', 'name' => 'Couronne danoise', 'symbol' => 'kr', 'countries' => ['Danemark']],
            ['code' => 'ISK', 'name' => 'Couronne islandaise', 'symbol' => 'kr', 'countries' => ['Islande']],
            ['code' => 'PLN', 'name' => 'Zloty polonais', 'symbol' => 'zł', 'countries' => ['Pologne']],
            ['code' => 'CZK', 'name' => 'Couronne tchèque', 'symbol' => 'Kč', 'countries' => ['Tchéquie']],
            ['code' => 'HUF', 'name' => 'Forint hongrois', 'symbol' => 'Ft', 'countries' => ['Hongrie']],
            ['code' => 'RON', 'name' => 'Leu roumain', 'symbol' => 'lei', 'countries' => ['Roumanie']],
            ['code' => 'BGN', 'name' => 'Lev bulgare', 'symbol' => 'лв', 'countries' => ['Bulgarie']],
            ['code' => 'RSD', 'name' => 'Dinar serbe', 'symbol' => 'дин', 'countries' => ['Serbie']],
            ['code' => 'BAM', 'name' => 'Mark convertible', 'symbol' => 'KM', 'countries' => ['Bosnie-Herzégovine']],
            ['code' => 'MKD', 'name' => 'Denar macédonien', 'symbol' => 'ден', 'countries' => ['Macédoine du Nord']],
            ['code' => 'ALL', 'name' => 'Lek albanais', 'symbol' => 'L', 'countries' => ['Albanie']],
            ['code' => 'MDL', 'name' => 'Leu moldave', 'symbol' => 'L', 'countries' => ['Moldavie']],
            ['code' => 'UAH', 'name' => 'Hryvnia ukrainienne', 'symbol' => '₴', 'countries' => ['Ukraine']],
            ['code' => 'BYN', 'name' => 'Rouble biélorusse', 'symbol' => 'Br', 'countries' => ['Biélorussie']],
            ['code' => 'RUB', 'name' => 'Rouble russe', 'symbol' => '₽', 'countries' => ['Russie']],

            // ---------------------------------------------------------------
            // Amérique du Nord et Caraïbes
            // ---------------------------------------------------------------
            ['code' => 'USD', 'name' => 'Dollar américain', 'symbol' => '$', 'countries' => ['États-Unis', 'Équateur', 'Salvador', 'Panama', 'Porto Rico', 'Timor oriental']],
            ['code' => 'CAD', 'name' => 'Dollar canadien', 'symbol' => 'C$', 'countries' => ['Canada']],
            ['code' => 'MXN', 'name' => 'Peso mexicain', 'symbol' => 'MX$', 'countries' => ['Mexique']],
            ['code' => 'GTQ', 'name' => 'Quetzal guatémaltèque', 'symbol' => 'Q', 'countries' => ['Guatemala']],
            ['code' => 'HNL', 'name' => 'Lempira hondurien', 'symbol' => 'L', 'countries' => ['Honduras']],
            ['code' => 'NIO', 'name' => 'Córdoba nicaraguayen', 'symbol' => 'C$', 'countries' => ['Nicaragua']],
            ['code' => 'CRC', 'name' => 'Colón costaricien', 'symbol' => '₡', 'countries' => ['Costa Rica']],
            ['code' => 'BZD', 'name' => 'Dollar bélizien', 'symbol' => 'BZ$', 'countries' => ['Belize']],
            ['code' => 'CUP', 'name' => 'Peso cubain', 'symbol' => '$MN', 'countries' => ['Cuba']],
            ['code' => 'DOP', 'name' => 'Peso dominicain', 'symbol' => 'RD$', 'countries' => ['République dominicaine']],
            ['code' => 'HTG', 'name' => 'Gourde haïtienne', 'symbol' => 'G', 'countries' => ['Haïti']],
            ['code' => 'JMD', 'name' => 'Dollar jamaïcain', 'symbol' => 'J$', 'countries' => ['Jamaïque']],
            ['code' => 'TTD', 'name' => 'Dollar de Trinité-et-Tobago', 'symbol' => 'TT$', 'countries' => ['Trinité-et-Tobago']],
            ['code' => 'BBD', 'name' => 'Dollar barbadien', 'symbol' => 'Bds$', 'countries' => ['Barbade']],
            ['code' => 'BSD', 'name' => 'Dollar bahaméen', 'symbol' => 'B$', 'countries' => ['Bahamas']],
            ['code' => 'XCD', 'name' => 'Dollar des Caraïbes orientales', 'symbol' => 'EC$', 'countries' => ['Antigua-et-Barbuda', 'Dominique', 'Grenade', 'Saint-Christophe-et-Niévès', 'Sainte-Lucie', 'Saint-Vincent-et-les-Grenadines']],

            // ---------------------------------------------------------------
            // Amérique du Sud
            // ---------------------------------------------------------------
            ['code' => 'BRL', 'name' => 'Real brésilien', 'symbol' => 'R$', 'countries' => ['Brésil']],
            ['code' => 'ARS', 'name' => 'Peso argentin', 'symbol' => 'AR$', 'countries' => ['Argentine']],
            ['code' => 'CLP', 'name' => 'Peso chilien', 'symbol' => 'CLP$', 'countries' => ['Chili']],
            ['code' => 'COP', 'name' => 'Peso colombien', 'symbol' => 'COL$', 'countries' => ['Colombie']],
            ['code' => 'PEN', 'name' => 'Sol péruvien', 'symbol' => 'S/', 'countries' => ['Pérou']],
            ['code' => 'BOB', 'name' => 'Boliviano bolivien', 'symbol' => 'Bs', 'countries' => ['Bolivie']],
            ['code' => 'PYG', 'name' => 'Guarani paraguayen', 'symbol' => '₲', 'countries' => ['Paraguay']],
            ['code' => 'UYU', 'name' => 'Peso uruguayen', 'symbol' => '$U', 'countries' => ['Uruguay']],
            ['code' => 'VES', 'name' => 'Bolívar vénézuélien', 'symbol' => 'Bs.', 'countries' => ['Venezuela']],
            ['code' => 'GYD', 'name' => 'Dollar guyanien', 'symbol' => 'G$', 'countries' => ['Guyana']],
            ['code' => 'SRD', 'name' => 'Dollar surinamais', 'symbol' => 'SR$', 'countries' => ['Suriname']],

            // ---------------------------------------------------------------
            // Moyen-Orient
            // ---------------------------------------------------------------
            ['code' => 'AED', 'name' => 'Dirham des Émirats', 'symbol' => 'AED', 'countries' => ['Émirats arabes unis']],
            ['code' => 'SAR', 'name' => 'Riyal saoudien', 'symbol' => 'SAR', 'countries' => ['Arabie saoudite']],
            ['code' => 'QAR', 'name' => 'Riyal qatari', 'symbol' => 'QR', 'countries' => ['Qatar']],
            ['code' => 'KWD', 'name' => 'Dinar koweïtien', 'symbol' => 'KD', 'countries' => ['Koweït']],
            ['code' => 'BHD', 'name' => 'Dinar bahreïni', 'symbol' => 'BD', 'countries' => ['Bahreïn']],
            ['code' => 'OMR', 'name' => 'Rial omanais', 'symbol' => 'OMR', 'countries' => ['Oman']],
            ['code' => 'JOD', 'name' => 'Dinar jordanien', 'symbol' => 'JD', 'countries' => ['Jordanie']],
            ['code' => 'LBP', 'name' => 'Livre libanaise', 'symbol' => 'LL', 'countries' => ['Liban']],
            ['code' => 'SYP', 'name' => 'Livre syrienne', 'symbol' => 'S£', 'countries' => ['Syrie']],
            ['code' => 'IQD', 'name' => 'Dinar irakien', 'symbol' => 'IQD', 'countries' => ['Irak']],
            ['code' => 'IRR', 'name' => 'Rial iranien', 'symbol' => 'IRR', 'countries' => ['Iran']],
            ['code' => 'ILS', 'name' => 'Nouveau shekel', 'symbol' => '₪', 'countries' => ['Israël', 'Palestine']],
            ['code' => 'TRY', 'name' => 'Livre turque', 'symbol' => '₺', 'countries' => ['Turquie']],
            ['code' => 'YER', 'name' => 'Rial yéménite', 'symbol' => 'YER', 'countries' => ['Yémen']],

            // ---------------------------------------------------------------
            // Asie
            // ---------------------------------------------------------------
            ['code' => 'CNY', 'name' => 'Yuan chinois', 'symbol' => '¥', 'countries' => ['Chine']],
            ['code' => 'JPY', 'name' => 'Yen japonais', 'symbol' => '¥', 'countries' => ['Japon']],
            ['code' => 'KRW', 'name' => 'Won sud-coréen', 'symbol' => '₩', 'countries' => ['Corée du Sud']],
            ['code' => 'KPW', 'name' => 'Won nord-coréen', 'symbol' => '₩', 'countries' => ['Corée du Nord']],
            ['code' => 'HKD', 'name' => 'Dollar de Hong Kong', 'symbol' => 'HK$', 'countries' => ['Hong Kong']],
            ['code' => 'MOP', 'name' => 'Pataca macanaise', 'symbol' => 'MOP$', 'countries' => ['Macao']],
            ['code' => 'TWD', 'name' => 'Dollar taïwanais', 'symbol' => 'NT$', 'countries' => ['Taïwan']],
            ['code' => 'SGD', 'name' => 'Dollar de Singapour', 'symbol' => 'S$', 'countries' => ['Singapour']],
            ['code' => 'MYR', 'name' => 'Ringgit malaisien', 'symbol' => 'RM', 'countries' => ['Malaisie']],
            ['code' => 'IDR', 'name' => 'Roupie indonésienne', 'symbol' => 'Rp', 'countries' => ['Indonésie']],
            ['code' => 'THB', 'name' => 'Baht thaïlandais', 'symbol' => '฿', 'countries' => ['Thaïlande']],
            ['code' => 'VND', 'name' => 'Dong vietnamien', 'symbol' => '₫', 'countries' => ['Viêt Nam']],
            ['code' => 'PHP', 'name' => 'Peso philippin', 'symbol' => '₱', 'countries' => ['Philippines']],
            ['code' => 'KHR', 'name' => 'Riel cambodgien', 'symbol' => '៛', 'countries' => ['Cambodge']],
            ['code' => 'LAK', 'name' => 'Kip laotien', 'symbol' => '₭', 'countries' => ['Laos']],
            ['code' => 'MMK', 'name' => 'Kyat birman', 'symbol' => 'K', 'countries' => ['Birmanie']],
            ['code' => 'BND', 'name' => 'Dollar brunéien', 'symbol' => 'B$', 'countries' => ['Brunei']],
            ['code' => 'INR', 'name' => 'Roupie indienne', 'symbol' => '₹', 'countries' => ['Inde']],
            ['code' => 'PKR', 'name' => 'Roupie pakistanaise', 'symbol' => 'Rs', 'countries' => ['Pakistan']],
            ['code' => 'BDT', 'name' => 'Taka bangladais', 'symbol' => '৳', 'countries' => ['Bangladesh']],
            ['code' => 'LKR', 'name' => 'Roupie srilankaise', 'symbol' => 'Rs', 'countries' => ['Sri Lanka']],
            ['code' => 'NPR', 'name' => 'Roupie népalaise', 'symbol' => 'Rs', 'countries' => ['Népal']],
            ['code' => 'BTN', 'name' => 'Ngultrum bhoutanais', 'symbol' => 'Nu.', 'countries' => ['Bhoutan']],
            ['code' => 'MVR', 'name' => 'Rufiyaa maldivienne', 'symbol' => 'Rf', 'countries' => ['Maldives']],
            ['code' => 'AFN', 'name' => 'Afghani afghan', 'symbol' => '؋', 'countries' => ['Afghanistan']],
            ['code' => 'MNT', 'name' => 'Tugrik mongol', 'symbol' => '₮', 'countries' => ['Mongolie']],
            ['code' => 'KZT', 'name' => 'Tenge kazakh', 'symbol' => '₸', 'countries' => ['Kazakhstan']],
            ['code' => 'UZS', 'name' => 'Sum ouzbek', 'symbol' => 'UZS', 'countries' => ['Ouzbékistan']],
            ['code' => 'TJS', 'name' => 'Somoni tadjik', 'symbol' => 'SM', 'countries' => ['Tadjikistan']],
            ['code' => 'TMT', 'name' => 'Manat turkmène', 'symbol' => 'TMT', 'countries' => ['Turkménistan']],
            ['code' => 'KGS', 'name' => 'Som kirghize', 'symbol' => 'KGS', 'countries' => ['Kirghizistan']],
            ['code' => 'AZN', 'name' => 'Manat azerbaïdjanais', 'symbol' => '₼', 'countries' => ['Azerbaïdjan']],
            ['code' => 'GEL', 'name' => 'Lari géorgien', 'symbol' => '₾', 'countries' => ['Géorgie']],
            ['code' => 'AMD', 'name' => 'Dram arménien', 'symbol' => '֏', 'countries' => ['Arménie']],

            // ---------------------------------------------------------------
            // Océanie
            // ---------------------------------------------------------------
            ['code' => 'AUD', 'name' => 'Dollar australien', 'symbol' => 'A$', 'countries' => ['Australie', 'Kiribati', 'Nauru', 'Tuvalu']],
            ['code' => 'NZD', 'name' => 'Dollar néo-zélandais', 'symbol' => 'NZ$', 'countries' => ['Nouvelle-Zélande']],
            ['code' => 'FJD', 'name' => 'Dollar fidjien', 'symbol' => 'FJ$', 'countries' => ['Fidji']],
            ['code' => 'PGK', 'name' => 'Kina papouan', 'symbol' => 'K', 'countries' => ['Papouasie-Nouvelle-Guinée']],
            ['code' => 'SBD', 'name' => 'Dollar des Salomon', 'symbol' => 'SI$', 'countries' => ['Îles Salomon']],
            ['code' => 'VUV', 'name' => 'Vatu vanuatuan', 'symbol' => 'VT', 'countries' => ['Vanuatu']],
            ['code' => 'WST', 'name' => 'Tala samoan', 'symbol' => 'WS$', 'countries' => ['Samoa']],
            ['code' => 'TOP', 'name' => 'Paʻanga tongien', 'symbol' => 'T$', 'countries' => ['Tonga']],
            ['code' => 'XPF', 'name' => 'Franc Pacifique', 'symbol' => 'F', 'countries' => ['Nouvelle-Calédonie', 'Polynésie française', 'Wallis-et-Futuna']],
        ];
    }
}
