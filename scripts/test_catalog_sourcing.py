"""Offline regression checks for supplier price units and draft preparation."""
import unittest

from build_sourced_wholesale_manifests import classify, translate_title
from source_wholesale_catalog import original_image, price_data
from sourced_catalog_categories import native_classification


class CatalogSourcingTest(unittest.TestCase):
    def test_usd_price_is_not_confused_with_localized_display(self):
        offer = {
            'price': '18.87',
            'lowerPrice': '$18.87',
            'information': {'productTransferModel': {'moneyCurrency': 'USD'}},
            'promotionInfoVO': {'quantityPrices': [{'price': '25.0', 'quantityMin': 350, 'unit': 'Sets'}]},
            'tradePrice': {'price': 'US $25.00', 'minOrder': '350 sets', 'unit': 'set'},
        }
        self.assertEqual([{'min': 350, 'price': 25.0, 'unit': 'Sets'}], price_data(offer))

    def test_foreign_currency_is_not_assumed_to_be_usd(self):
        self.assertIsNone(price_data({
            'information': {'productTransferModel': {'moneyCurrency': 'EUR'}},
            'promotionInfoVO': {'quantityPrices': [{'price': '25', 'quantityMin': 1, 'unit': 'Piece'}]},
        }))

    def test_a_range_remains_an_upper_bound_for_the_real_selling_unit(self):
        price = price_data({'tradePrice': {'price': 'US $2.50-$4.50', 'minOrder': '12 cartons', 'unit': 'carton'}})
        self.assertEqual(4.5, price[0]['price'])
        self.assertEqual(12, price[0]['min'])
        self.assertEqual('carton', price[0]['unit'])
        self.assertTrue(price[0]['range_reference'])

    def test_thumbnail_path_resolves_to_named_original(self):
        self.assertEqual('https://sc04.alicdn.com/kf/Sample.jpg', original_image('//s.alicdn.com/@sc04/kf/Sample.jpg_300x300.jpg'))

    def test_phrase_translation_does_not_turn_a_dress_into_a_bathrobe(self):
        self.assertEqual('Robe femme coton manches longues', translate_title('Women Dress Cotton Long Sleeve'))
        self.assertEqual('T-shirt pour homme', translate_title('T-Shirt for Men'))
        self.assertEqual('Green Lion chargeur sans fil', translate_title('Green Lion Wireless Charger', ['Green Lion']))

    def test_makeup_bag_is_not_categorized_as_makeup(self):
        self.assertEqual((30, 172), classify('Travel Cosmetic Makeup Bag with Zipper', 'bags')[:2])

    def test_screw_pan_head_does_not_become_a_cooking_pan(self):
        self.assertEqual((3, 20), classify('Pan Head Stainless Steel Electronic Screws', 'electronics')[:2])
        self.assertEqual((14, 86), classify('Electric Shoe Dryer for Shoes', 'shoes')[:2])
        self.assertEqual((3, 17), classify('Home & Garden Knife Sharpener', 'kitchenware')[:2])

    def test_paper_factory_equipment_is_not_office_stationery(self):
        self.assertIsNone(classify('Four-Drinier 300TPD Paper Making Machine Office Stationery', 'stationery'))
        self.assertIsNone(classify('Plastic Bag Making Machine Production Line', 'bags'))

    def test_real_supplier_categories_override_ambiguous_marketing_words(self):
        examples = [
            ('Gold Desktop Statue Office Decor', ['Home & Garden', 'Home Decor', 'Sculptures'], (3, 16)),
            ('USB Cable for Smart Watch Computer', ['Consumer Electronics', 'Cables & Commonly Used Accessories', 'Data Cables'], (1, 5)),
            ('Women Dress with Belt', ['Apparel & Accessories', "Women's Clothing", 'Casual Dresses'], (2, 9)),
            ('Dishwasher Safe Utensils', ['Home & Garden', 'Kitchenware', 'Utensils'], (3, 17)),
            ('Plush Grow Garden Animal Doll', ['Mother, Kids & Toys', 'Stuffed Animals & Plush Toys'], (7, 47)),
            ('Golden Ring', ['Jewelry, Eyewear, Watches & Accessories', 'Fashion Jewelry', 'Fashion Rings'], (2, 13)),
            ('Car Seat Protector', ['Vehicle Accessories, Electronics & Tools', 'Interior Accessories', 'Seat Covers'], (9, 56)),
        ]
        for title, path, expected in examples:
            self.assertEqual(expected, native_classification({'title': title, 'category_path': path})[:2])


if __name__ == '__main__':
    unittest.main()
