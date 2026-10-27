#!/usr/bin/env python3
"""Collect public supplier listings into a resumable, private review queue.

No database writes to ASSO. No CAPTCHA solving or account access. Supplier country,
original prices/units, source pages and image checksums remain attached to records.
"""
import argparse
import concurrent.futures
import gzip
import hashlib
import html
import io
import json
import re
import sqlite3
import threading
import time
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urlparse

import requests
from PIL import Image, ImageOps

ROOT = Path(__file__).resolve().parents[1]
ARCHIVE = ROOT / 'storage/app/private/catalog-imports/sourcing-3000-2026-10-27'
ARCHIVE.mkdir(parents=True, exist_ok=True, mode=0o700)
ARCHIVE.chmod(0o700)
for folder in ['pages', 'details', 'images', 'records', 'manifests']:
    (ARCHIVE / folder).mkdir(exist_ok=True)
STATE = ARCHIVE / 'queue.sqlite'
SQL_LOCK = threading.Lock()
HTTP_LOCAL = threading.local()
DETAIL_BLOCKED = threading.Event()
IMAGE_EXECUTOR = concurrent.futures.ThreadPoolExecutor(max_workers=12)
EXCLUDE = re.compile(r'\b(replica|counterfeit|1:1|aaa quality|vape|vaping|cigarette|tobacco|hookah|shisha|weapon|gun|ammunition|pesticide|fertilizer|pharmaceutical|capsules|tablets medicine|steroid|sex toy|adult toy|rolex|louis vuitton|gucci|hermes|hermès)\b', re.I)
QUERIES = {
    'CN': ['kitchenware', 'home-decor', 'phone-accessories', 'bags', 'sports', 'toys', 'beauty-tools', 'stationery', 'clothing', 'shoes', 'lighting', 'travel-bags', 'household-products', 'garden-tools', 'bathroom-accessories'],
    'TR': ['clothing', 'home-textile', 'towels', 'women-clothing', 'men-clothing', 'shoes', 'bags', 'furniture', 'cookware', 'kitchenware', 'baby-clothing', 'sportswear', 'bedsheets', 'bathrobe', 't-shirts', 'dress', 'jeans', 'socks'],
    'AE': ['perfume', 'cosmetics', 'clothing', 'electronics', 'mobile-phone', 'kitchenware', 'household', 'bags', 'shoes', 'dates', 'coffee', 'tea', 'stationery', 'toys', 'sports', 'furniture', 'home-textile', 'watch', 'abaya', 'hair-care', 'skin-care', 'fashion-accessories', 'luggage'],
}
QUERIES['AE'].extend(['phone-accessories', 'power-bank', 'usb-cable', 'wireless-charger', 'abaya-dress', 'women-dress', 'cotton-t-shirt', 'polo-shirt', 'handbags', 'backpacks', 'travel-accessories', 'kitchen-utensils', 'cookware-set', 'tableware', 'water-bottle', 'home-decor', 'bath-towels', 'notebooks', 'pens', 'children-toys', 'led-lights', 'bluetooth-speaker', 'headphones', 'sunglasses', 'jewelry', 'shampoo', 'body-lotion', 'makeup-brush'])


def now():
    return datetime.now(timezone.utc).isoformat()


def db():
    c = sqlite3.connect(STATE, timeout=60)
    c.execute('PRAGMA journal_mode=WAL')
    c.execute('CREATE TABLE IF NOT EXISTS pages (url TEXT PRIMARY KEY,country TEXT,query TEXT,page INTEGER,status TEXT,count INTEGER,error TEXT)')
    c.execute('CREATE TABLE IF NOT EXISTS candidates (id TEXT PRIMARY KEY,country TEXT,title TEXT,url TEXT,query TEXT,listing TEXT,status TEXT DEFAULT "queued",error TEXT,record TEXT,primary_hash TEXT)')
    c.commit()
    return c


def get(url):
    if not hasattr(HTTP_LOCAL, 'session'):
        HTTP_LOCAL.session = requests.Session()
        HTTP_LOCAL.session.headers.update({'User-Agent': 'Mozilla/5.0', 'Accept-Language': 'en'})
    r = HTTP_LOCAL.session.get(url, timeout=(12, 35))
    r.raise_for_status()
    return r


def original_image(url):
    if url.startswith('//'):
        url = 'https:' + url
    url = url.replace('https://s.alicdn.com/@sc04/', 'https://sc04.alicdn.com/')
    # The listing links thumbnails of a named original file. Fetch that original,
    # never upscale pixels or fabricate additional views.
    return re.sub(r'(?<=\.(?:jpg))_\d+x\d+\.(?:jpg|png|webp).*$', '', url, flags=re.I) if '.jpg_' in url.lower() else re.sub(r'_(?:\d+x\d+)\.(?:jpg|png|webp).*$', '', url, flags=re.I)


def offers_data(text):
    m = re.search(r'window\._PAGE_DATA_\s*=\s*', text)
    if not m:
        return None
    return json.JSONDecoder().raw_decode(text[m.end():])[0]


def price_data(offer):
    # These unlocalized values explicitly use USD; the displayed local price may
    # use GBP despite a dollar symbol and must never be treated as USD.
    transfer = offer.get('information', {}).get('productTransferModel', {})
    currency = transfer.get('moneyCurrency')
    q = offer.get('promotionInfoVO', {}).get('quantityPrices', [])
    if currency and currency != 'USD':
        return None
    if q:
        tiers = []
        for t in q:
            try:
                tiers.append({'min': int(t['quantityMin']), 'price': float(t['price']), 'unit': t.get('unit', offer.get('tradePrice', {}).get('unit', ''))})
            except (KeyError, ValueError, TypeError):
                return None
        if all(t['min'] > 0 and 0 < t['price'] < 100000 for t in tiers):
            return tiers
    trade = offer.get('tradePrice', {})
    if not str(trade.get('price', '')).startswith('US $'):
        return None
    value = str(trade['price']).replace('US $', '').replace(',', '')
    amounts = re.findall(r'\d+(?:\.\d+)?', value)
    moq = re.search(r'[\d,]+', str(trade.get('minOrder', '')))
    if not amounts or not moq:
        return None
    return [{'min': int(moq[0].replace(',', '')), 'price': max(map(float, amounts)), 'unit': trade.get('unit', ''), 'range_reference': len(amounts) > 1}]


def fetch_listing(task):
    country, query, page = task
    suffix = '' if page == 1 else '_' + str(page)
    url = f'https://www.alibaba.com/countrysearch/{country}/{query}{suffix}.html'
    with SQL_LOCK:
        c = db()
        done = c.execute('SELECT status FROM pages WHERE url=?', (url,)).fetchone()
        c.close()
    if done:
        return country, query, page, 0, 'cached'
    try:
        time.sleep(.45)
        r = get(url)
        data = offers_data(r.text)
        if not data:
            raise ValueError('No public listing data; page unavailable or challenge')
        if int(data.get('pagination', {}).get('current', page)) != page:
            raise ValueError('Pagination mismatch')
        gzip.open(ARCHIVE / 'pages' / f'{country}-{query}-{page}.html.gz', 'wt').write(r.text)
        items = data.get('offerResultData', {}).get('itemInfoList', [])
        valid = []
        for item in items:
            o = item.get('offer', {})
            supplier_country = o.get('supplier', {}).get('supplierCountry', {}).get('id')
            title = html.unescape(o.get('information', {}).get('enPureTitle', '')).strip()
            source_url = o.get('information', {}).get('productUrl', '')
            images = list(dict.fromkeys(original_image(u) for u in o.get('image', {}).get('multiImage', [])))
            if supplier_country != country or not title or EXCLUDE.search(title) or len(images) < 2 or not price_data(o):
                continue
            if not source_url.startswith(('https://www.alibaba.com/product-detail/', 'http://www.alibaba.com/product-detail/')):
                continue
            payload = {'offer': o, 'source_listing': url, 'consulted_at': now(), 'image_urls': images}
            valid.append((str(o['id']), country, title, source_url.replace('http:', 'https:'), query, json.dumps(payload, ensure_ascii=False)))
        with SQL_LOCK:
            c = db()
            before = c.total_changes
            c.executemany('INSERT OR IGNORE INTO candidates(id,country,title,url,query,listing) VALUES(?,?,?,?,?,?)', valid)
            added = c.total_changes - before
            c.execute('INSERT OR REPLACE INTO pages VALUES(?,?,?,?,?,?,?)', (url, country, query, page, 'done', len(items), None))
            c.commit()
            c.close()
        return country, query, page, added, 'ok'
    except Exception as exc:
        with SQL_LOCK:
            c = db()
            c.execute('INSERT OR REPLACE INTO pages VALUES(?,?,?,?,?,?,?)', (url, country, query, page, 'unavailable', 0, str(exc)[:300]))
            c.commit()
            c.close()
        return country, query, page, 0, str(exc)[:90]


def discover(pages, countries):
    tasks = []
    for page in range(1, pages + 1):
        for i in range(max(map(len, QUERIES.values()))):
            for country, queries in QUERIES.items():
                if country in countries and i < len(queries):
                    tasks.append((country, queries[i], page))
    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as executor:
        for i, result in enumerate(executor.map(fetch_listing, tasks), 1):
            if i % 9 == 0 or result[-1] not in ['ok', 'cached']:
                print('DISCOVERY', i, '/', len(tasks), result, summary(), flush=True)
    print('DISCOVERY COMPLETE', summary(), flush=True)


def summary():
    with SQL_LOCK:
        c = db()
        rows = c.execute('SELECT country,status,count(*) FROM candidates GROUP BY country,status').fetchall()
        c.close()
    return rows


def fetch_detail(candidate):
    pid, country, title, source_url, query, listing = candidate
    listed = json.loads(listing)
    detail = None
    cached_detail = ARCHIVE / 'details' / f'{pid}.json.gz'
    if cached_detail.exists():
        try:
            with gzip.open(cached_detail, 'rt') as cached:
                detail = json.load(cached)
        except (OSError, EOFError, ValueError):
            detail = None
    if not detail and not DETAIL_BLOCKED.is_set():
        # This is the marketplace's normal public localized product page, without
        # proxies, CAPTCHA solving, credentials or alternate retry hosts.
        detail_url = source_url.replace('https://www.alibaba.com/', 'https://turkiye.alibaba.com/')
        try:
            time.sleep(.4)
            r = get(detail_url)
            marker = 'window.detailData = '
            if marker in r.text:
                parsed = json.JSONDecoder().raw_decode(r.text.split(marker, 1)[1])[0]
                detail = parsed.get('globalData')
                if detail:
                    temporary = ARCHIVE / 'details' / f'{pid}.json.gz.part'
                    with gzip.open(temporary, 'wt') as output:
                        json.dump(detail, output, ensure_ascii=False)
                    temporary.replace(cached_detail)
            if '"action": "captcha"' in r.text:
                DETAIL_BLOCKED.set()
        except requests.HTTPError as exc:
            if exc.response.status_code in (403, 429):
                DETAIL_BLOCKED.set()
        except (requests.RequestException, ValueError, KeyError):
            pass
    try:
        offer = listed['offer']
        tiers = price_data(offer)
        images = listed['image_urls']
        supplier = offer['supplier']['supplierName']
        attributes = offer.get('features', {}).get('productAttribute', [])
        unit = tiers[0]['unit']
        weight = None
        declared_origin = None
        model = None
        if detail:
            if detail['seller'].get('companyRegisterCountry') != country:
                raise ValueError('Detailed supplier country contradicts listing')
            p = detail['product']
            supplier = detail['seller'].get('companyName', supplier)
            title = p.get('subject', title)
            attributes = p.get('productBasicProperties', attributes)
            gallery = [v['imageUrl']['big'] for v in p.get('mediaItems', []) if v.get('type') == 'image' and v.get('bizType') == 'Product']
            if len(gallery) >= 2:
                images = list(dict.fromkeys(gallery + images))
            pricing = p.get('price', {})
            if pricing.get('productLadderPrices'):
                unit = pricing.get('unit', unit)
                tiers = [{'min': int(t['min']), 'price': float(t['dollarPrice']), 'unit': unit} for t in pricing['productLadderPrices']]
            elif pricing.get('productRangePrices'):
                values = pricing['productRangePrices']
                unit = pricing.get('unit', unit)
                tiers = [{'min': int(p.get('customsMoq', tiers[0]['min'])), 'price': float(values['dollarPriceRangeHigh']), 'unit': unit, 'range_reference': True}]
            # Only a real unit shipping weight is used. Never invent 3,000 weights.
            shipping = detail.get('trade', {}).get('logisticInfo', {})
            raw_weight = shipping.get('unitWeight')
            if raw_weight:
                try:
                    weight = float(raw_weight) / max(1, int(pricing.get('batchNum', 1)))
                    if not 0 < weight < 10000:
                        weight = None
                except (ValueError, TypeError):
                    weight = None
            for attr in attributes:
                name = attr.get('attrName', attr.get('name', '')).lower()
                if name == 'place of origin':
                    declared_origin = attr.get('attrValue', attr.get('value'))
                if name == 'model number':
                    model = attr.get('attrValue', attr.get('value'))
        if EXCLUDE.search(title):
            raise ValueError('Excluded category/title')
        tiers = sorted({t['min']: t for t in tiers}.values(), key=lambda t: t['min'])
        if not tiers or any(t['min'] < 1 or t['price'] <= 0 for t in tiers):
            raise ValueError('Invalid source price/quantity')
        if any(tiers[i]['price'] > tiers[i - 1]['price'] for i in range(1, len(tiers))):
            raise ValueError('Increasing source tiers require manual review')
        record = {'id': pid, 'country': country, 'title': html.unescape(title), 'url': source_url, 'query': query, 'listing_url': listed['source_listing'], 'consulted_at': listed['consulted_at'], 'supplier': html.unescape(supplier), 'declared_origin': declared_origin, 'model': model, 'attributes': attributes, 'weight_kg': weight, 'tiers': tiers, 'detail_available': bool(detail), 'images': []}
        hashes = set()
        image_urls = list(dict.fromkeys(images))[:7]
        for offset in range(0, len(image_urls), 3):
            futures = [IMAGE_EXECUTOR.submit(fetch_image, url) for url in image_urls[offset:offset + 3]]
            for future in futures:
                result = future.result()
                if result is None:
                    continue
                url, im, pixels_hash, content = result
                if pixels_hash in hashes:
                    continue
                hashes.add(pixels_hash)
                filename = f'alibaba-{pid}-{len(record["images"]) + 1}.jpg'
                (ARCHIVE / 'images' / filename).write_bytes(content)
                record['images'].append({'file': filename, 'source_url': url, 'width': im.width, 'height': im.height, 'sha256': hashlib.sha256(content).hexdigest(), 'pixel_sha256': pixels_hash})
                if len(record['images']) == 3:
                    break
            if len(record['images']) == 3:
                break
        if len(record['images']) < 2:
            raise ValueError('Fewer than two distinct large original photos')
        first_hash = record['images'][0]['pixel_sha256']
        with SQL_LOCK:
            c = db()
            duplicate = c.execute('SELECT id FROM candidates WHERE status="ready" AND primary_hash=?', (first_hash,)).fetchone()
            if duplicate:
                c.close()
                raise ValueError('Duplicate primary photo of source ' + duplicate[0])
            c.execute('UPDATE candidates SET status="ready",record=?,primary_hash=?,error=NULL WHERE id=?', (json.dumps(record, ensure_ascii=False), first_hash, pid))
            c.commit()
            c.close()
        (ARCHIVE / 'records' / f'{pid}.json').write_text(json.dumps(record, ensure_ascii=False, indent=2))
        return country, pid, 'ready'
    except Exception as exc:
        with SQL_LOCK:
            c = db()
            c.execute('UPDATE candidates SET status="rejected",error=? WHERE id=?', (str(exc)[:400], pid))
            c.commit()
            c.close()
        return country, pid, 'rejected'


def fetch_image(url):
    try:
        if urlparse(url).hostname not in ['sc04.alicdn.com', 'sc01.alicdn.com', 'sc02.alicdn.com', 'sc03.alicdn.com']:
            return None
        r = get(url)
        if len(r.content) > 12 * 1024 * 1024:
            return None
        im = Image.open(io.BytesIO(r.content))
        im.load()
        im = ImageOps.exif_transpose(im).convert('RGB')
        if min(im.size) < 400 or max(im.size) < 700:
            return None
        pixels_hash = hashlib.sha256(im.tobytes()).hexdigest()
        buf = io.BytesIO()
        im.save(buf, 'JPEG', quality=92, optimize=True)
        return url, im, pixels_hash, buf.getvalue()
    except (requests.RequestException, OSError, ValueError):
        return None


def prepare(target, workers):
    initial = json.loads((ROOT / 'database/data/wholesale-catalog-2026-10-27.json').read_text())
    initial_counts = Counter(p['product']['origin_country'] for p in initial['products'])
    initial_ids = {p['source']['id'] for p in initial['products'] if p['source']['platform'] == 'Alibaba'}
    with SQL_LOCK:
        c = db()
        for pid in initial_ids:
            c.execute('UPDATE candidates SET status="existing" WHERE id=?', (pid,))
        c.commit()
        c.close()
    # A previous interrupted run may leave only local queue claims behind.
    with SQL_LOCK:
        c = db()
        c.execute('UPDATE candidates SET status="queued" WHERE status="processing"')
        c.commit()
        c.close()
    processed = 0
    progress_lock = threading.Lock()

    def consume():
        nonlocal processed
        while True:
            with SQL_LOCK:
                c = db()
                counts = dict(c.execute('SELECT country,count(*) FROM candidates WHERE status IN ("ready","processing") GROUP BY country').fetchall())
                targets = dict.fromkeys(['CN', 'TR', 'AE'], target)
                targets_file = ARCHIVE / 'work-targets.json'
                if targets_file.exists():
                    try:
                        targets.update(json.loads(targets_file.read_text()))
                    except (ValueError, OSError):
                        pass
                task = None
                for country in sorted(['CN', 'TR', 'AE'], key=lambda k: counts.get(k, 0) + initial_counts[k]):
                    if counts.get(country, 0) + initial_counts[country] >= targets[country]:
                        continue
                    task = c.execute('SELECT id,country,title,url,query,listing FROM candidates WHERE country=? AND status="queued" ORDER BY rowid LIMIT 1', (country,)).fetchone()
                    if task:
                        c.execute('UPDATE candidates SET status="processing" WHERE id=?', (task[0],))
                        c.commit()
                        break
                c.close()
            if not task:
                break
            try:
                fetch_detail(task)
            except Exception as exc:
                with SQL_LOCK:
                    c = db()
                    c.execute('UPDATE candidates SET status="rejected",error=? WHERE id=?', ('Collection error: ' + str(exc)[:300], task[0]))
                    c.commit()
                    c.close()
            with progress_lock:
                processed += 1
                show = processed % 30 == 0
            if show:
                print('PREPARED', processed, summary(), 'detail_host_unavailable', DETAIL_BLOCKED.is_set(), flush=True)
    with concurrent.futures.ThreadPoolExecutor(max_workers=workers) as executor:
        list(executor.map(lambda _: consume(), range(workers)))
    print('PREPARATION COMPLETE', summary(), flush=True)


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('action', choices=['discover', 'prepare', 'status'])
    parser.add_argument('--pages', type=int, default=3)
    parser.add_argument('--target', type=int, default=1000)
    parser.add_argument('--workers', type=int, default=5)
    parser.add_argument('--countries', nargs='+', choices=['CN', 'TR', 'AE'], default=['CN', 'TR', 'AE'])
    args = parser.parse_args()
    if args.action == 'discover':
        discover(args.pages, args.countries)
    elif args.action == 'prepare':
        prepare(args.target, args.workers)
    else:
        print(summary())
