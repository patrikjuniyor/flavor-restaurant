#!/usr/bin/env python3
"""Generate the Flavor Elementor page templates.

Writes flavor/elementor/templates/*.json in the layout Elementor exports
(version 0.4, type "page"). Run after changing a widget's controls:

    python3 dev-tools/elementor/build-templates.py

tests/theme/test-elementor-widgets.php then checks every widgetType and
every setting key against the widget that renders it, so a template cannot
silently reference a control that no longer exists.
"""

import json
import os
import hashlib

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, '..', '..', 'flavor', 'elementor', 'templates')


def node_id(seed: str) -> str:
    """Stable 7-character id, as Elementor writes them."""
    return hashlib.sha1(seed.encode('utf-8')).hexdigest()[:7]


class Builder:
    def __init__(self, slug: str):
        self.slug = slug
        self.count = 0

    def _id(self) -> str:
        self.count += 1
        return node_id(f'{self.slug}:{self.count}')

    def widget(self, widget_type: str, settings: dict) -> dict:
        return {
            'id': self._id(),
            'elType': 'widget',
            'widgetType': widget_type,
            'settings': settings,
            'elements': [],
        }

    def section(self, *widgets: dict) -> dict:
        return {
            'id': self._id(),
            'elType': 'section',
            'settings': {},
            'elements': [
                {
                    'id': self._id(),
                    'elType': 'column',
                    'settings': {'_column_size': 100, '_inline_size': None},
                    'elements': list(widgets),
                    'isInner': False,
                }
            ],
            'isInner': False,
        }


def template(slug: str, title: str, sections) -> dict:
    b = Builder(slug)
    return {
        'version': '0.4',
        'title': title,
        'type': 'page',
        'page_settings': [],
        'content': [fn(b) for fn in sections],
    }


def home_sections():
    return [
        lambda b: b.section(b.widget('flavor_hero', {
            'title': 'طعم اصیل، در هر وعده',
            'text': 'مواد تازه، دستور پخت خانگی و میزبانی گرم؛ برای هر روز و هر مناسبت.',
            'cta': 'مشاهده منو',
        })),
        lambda b: b.section(b.widget('flavor_features', {
            'title': 'چرا رستوران ما؟',
        })),
        lambda b: b.section(b.widget('flavor_stats', {})),
        lambda b: b.section(b.widget('flavor_menu', {
            'title': 'منوی امروز',
            'count': 6,
            'show_price': 'yes',
        })),
        lambda b: b.section(b.widget('flavor_testimonials', {})),
        lambda b: b.section(b.widget('flavor_order_cta', {
            'title': 'همین حالا سفارش دهید',
        })),
    ]


def about_sections():
    return [
        lambda b: b.section(b.widget('flavor_about', {
            'title': 'داستان ما',
            'text': 'از یک آشپزخانهٔ کوچک آغاز شد و امروز با همان وسواس پخت می‌شود.',
        })),
        lambda b: b.section(b.widget('flavor_process', {
            'title': 'چطور سفارش دهید؟',
        })),
        lambda b: b.section(b.widget('flavor_stats', {})),
        lambda b: b.section(b.widget('flavor_chefs', {
            'title': 'آشپزهای ما',
        })),
        lambda b: b.section(b.widget('flavor_gallery', {})),
    ]


def menu_sections():
    return [
        lambda b: b.section(b.widget('flavor_menu', {
            'title': 'منوی غذا',
            'count': 12,
            'orderby': 'menu_order',
            'show_price': 'yes',
        })),
        lambda b: b.section(b.widget('flavor_price_list', {
            'title': 'نوشیدنی‌ها',
        })),
        lambda b: b.section(b.widget('flavor_offers', {
            'title': 'پیشنهاد ویژه',
            'text': 'تا پایان هفته، با کد زیر تخفیف بگیرید.',
            'button_text': 'سفارش',
        })),
        lambda b: b.section(b.widget('flavor_faq', {
            'title': 'پرسش‌های متداول',
        })),
    ]


TEMPLATES = {
    'home': ('صفحهٔ نخست', home_sections()),
    'about': ('درباره ما', about_sections()),
    'menu': ('صفحهٔ منو', menu_sections()),
}


def main() -> None:
    os.makedirs(OUT, exist_ok=True)
    for slug, (title, sections) in TEMPLATES.items():
        data = template(slug, title, sections)
        path = os.path.join(OUT, f'flavor-{slug}.json')
        with open(path, 'w', encoding='utf-8') as fh:
            json.dump(data, fh, ensure_ascii=False, indent=2)
            fh.write('\n')
        print('wrote', os.path.relpath(path, os.path.join(HERE, '..', '..')))


if __name__ == '__main__':
    main()
