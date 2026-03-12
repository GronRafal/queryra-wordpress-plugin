# Queryra - AI Search for WordPress & WooCommerce

[![WordPress Plugin](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/plugins/queryra-ai-search/)
[![Rating](https://img.shields.io/badge/Rating-★★★★★%205%2F5-yellow.svg)](https://wordpress.org/plugins/queryra-ai-search/#reviews)
[![Active Installs](https://img.shields.io/badge/WordPress.org-Live-brightgreen.svg)](https://wordpress.org/plugins/queryra-ai-search/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](LICENSE)

**Your customer types "present for my girlfriend". Your store returns: 0 results.**

You sell gift boxes, perfumes, skincare sets — exactly what she'd love. But WooCommerce search can't connect "present for girlfriend" to "Gift Box".

Your customer leaves. Sale lost. This happens every day.

**Queryra is different.** It understands the full intent behind queries — not just meaning, but price filters, brand exclusions, and sorting preferences. And it only knows YOUR store. When someone searches "present for my girlfriend", Queryra finds YOUR gift boxes, perfume sets, and skincare bundles.

👉 **[Live WooCommerce AI Search Demo](https://woo.queryra.com)** — over 200 products across 10 brands. Search naturally and see the difference.

[Start Free Trial](https://queryra.com/signup) · [Documentation](https://queryra.com/docs) · [FAQ](https://queryra.com/faq) · [Pricing](https://queryra.com/pricing) · [Blog](https://queryra.com/blog)

## The Problem With WordPress Search

Default WooCommerce search only matches exact keywords. If your product is called "Maison Blanc Gift Box" but someone searches "present for my girlfriend", they get nothing.

Real searches that return 0 results in most stores:

* "my skin looks tired" → Night creams, recovery oils, eye patches
* "gift for mom who loves candles" → Scented candles, home fragrance sets
* "looking older than my age" → Anti-aging serums, firming creams
* "before wedding skincare" → Bridal kits, radiance creams, ritual sets
* "postpartum hair loss" → Hair rescue sets, growth serums

**Queryra understands these searches and finds the right products.** [Try it live →](https://woo.queryra.com)

## Why Queryra vs Other Search Plugins?

**Trained on YOUR Store**
Generic AI plugins use the same model for everyone. Queryra builds a custom AI model from YOUR products, YOUR descriptions, YOUR categories.

**No ChatGPT Key Required**
Other plugins require you to create an OpenAI account and manage API keys. Queryra includes everything – one API key, no extra accounts.

**WordPress-Native**
Built specifically for WordPress and WooCommerce. Auto-syncs on publish, supports product variations, works with any theme. [Setup guide →](https://queryra.com/docs/wordpress-integration)

**Intent-Aware Search**
Most AI plugins only match meaning. Queryra adds a second layer: a query like "wireless headphones under $80, not Beats" applies the price filter AND excludes the brand. Vector-only plugins ignore both constraints. [Learn more →](https://queryra.com/blog/beyond-vector-search-woocommerce)

## Built for WooCommerce

* **Product Search** – Search by title, description, SKU, categories, tags, and attributes
* **Smart Product Ranking** – AI understands which products best match the query
* **Boost Control** – Promote high-margin products or slow-moving inventory
* **Live Search Results** – Instant AJAX-powered search suggestions
* **Auto-Sync** – New products indexed automatically when published
* **Smart Context Detection** – Automatically searches only products in WooCommerce shop pages, posts elsewhere
* **Search Analytics** – See what customers search for, including zero-result queries. Find gaps in your inventory before customers leave

## Perfect For Any WooCommerce Store

* **Beauty & Skincare** – "my skin looks tired" finds night creams and recovery oils — [see it live →](https://woo.queryra.com)
* **Fashion** – Find items by style, occasion, or color family
* **Electronics** – Search by features, not just model numbers
* **Home & Garden** – "cozy living room" finds rugs, lamps, pillows
* **Food & Beverage** – "healthy snacks" finds protein bars, nuts
* **Any catalog** – Works with any product type

## Beyond WooCommerce

Queryra also works with regular WordPress content — posts, pages, and custom post types. Perfect for knowledge bases, blogs, and FAQ sections.

👉 **[Try it live on a WooCommerce store →](https://woo.queryra.com)** — search "present for my girlfriend" or "my skin looks tired" and see the difference.

## Features

### Core Features
- 🤖 **Semantic AI Search** - Understands meaning, not just keywords
- 🎯 **Intent-Aware Search** - Understands price filters ("under $50"), brand exclusions ("not Beats"), and sorting ("best rated") in natural language
- 🛒 **WooCommerce Integration** - Indexes products with prices, stock, and featured status
- 🔄 **Auto-Sync** - Posts sync automatically when published or updated
- 📝 **Custom Post Types** - Works with posts, pages, and any custom type
- ⭐ **Sticky Post Priority** - Important posts and featured products rank higher
- 🎨 **Theme Compatible** - Works with any WordPress theme
- 📦 **Bulk Operations** - Send all existing posts with one click
- 📊 **Search Analytics** - See top searches and zero-result queries (find inventory gaps)

### Developer Features
- 🔐 **Secure** - WordPress nonces, HTTPS, input sanitization
- 📊 **Admin Dashboard** - Monitor sync status and statistics
- 🛠️ **Hooks & Filters** - Extend functionality (coming soon)
- ⚡ **Lightweight** - No bloat, follows WordPress coding standards
- 🔌 **REST API Ready** - Full API for headless WordPress

## How It Works

```mermaid
graph LR
    A[Publish Post] --> B[WordPress Plugin]
    B --> C[Queryra API]
    C --> D[AI Training]
    D --> E[Smart Search]
```

1. **Install** - Add plugin to WordPress
2. **Connect** - Paste API key from [Queryra](https://queryra.com/signup)
3. **Sync** - Auto-sync posts or bulk send existing content
4. **Search** - AI-powered results appear instantly

[How to Install AI Search on WooCommerce →](https://queryra.com/docs/wordpress-integration)

## Quick Start

### 1. Install Plugin

**Option A: WordPress Dashboard (Recommended)**
1. Go to **Plugins → Add New** in WordPress
2. Search for **"Queryra"**
3. Click **Install Now** then **Activate**

**Option B: Manual Installation**
```bash
cd /wp-content/plugins/
# Upload queryra-search folder
# Activate via WordPress Admin → Plugins
```

### 2. Get API Key

[Start your free trial →](https://queryra.com/signup) → Copy API key from dashboard

### 3. Configure Plugin

1. Go to **Settings → Queryra** in WordPress admin
2. Paste your **API Key**
3. Select which **Post Types** to sync (posts, pages, custom types)
4. Enable **Auto Sync** (recommended)
5. Click **Save Changes**

### 4. Sync Content

**Option A: Bulk Sync**
- Click **"Send All Posts to Queryra"** to sync existing content

**Option B: Automatic**
- New posts sync automatically when published

### 5. Done!

Your search is now powered by AI trained on your content. [Full setup guide →](https://queryra.com/docs/wordpress-integration)

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 5.8+ |
| PHP | 7.4+ |
| MySQL | 5.6+ |
| HTTPS | Recommended |

**Tested with:**
- WordPress 6.9, 6.7.2, 6.6, 6.5
- PHP 8.2, 8.1, 8.0, 7.4
- Popular themes: Twenty Twenty-Four, Astra, Kadence, GeneratePress

## Pricing

| Plan | Products | Searches/mo | Price |
|------|----------|-------------|-------|
| Free Trial | 100 | 500 | $0 for 14 days |
| Genesis Club | 200 | 500 | $0 for 30 days |
| Pro | 500+ | 5,000+ | From $9.99/mo |

No ChatGPT API key costs. No hidden fees. [See full pricing →](https://queryra.com/pricing)

### Genesis Club — First 100 Users

Join the Genesis Club and lock in exclusive benefits (automatic after email verification):

* **Extended trial** — 30 days instead of 14
* **Double records** — 200 products on trial
* **$9.99/mo locked forever** — price never increases
* **Direct founder access** — talk to the dev team
* **Lifetime Genesis badge** + priority support

[Join Genesis Club →](https://queryra.com/signup)

### Partner Program

Love Queryra? Help us grow and get rewarded. Write a review, share a link, or create content about Queryra — and get your Pro account ($9.99/mo) for free.

[Learn about the Partner Program →](https://queryra.com/blog/partner-program-pro-for-free)

## FAQ

### Can I see a live demo before installing?
Yes! Try our **[WooCommerce demo](https://woo.queryra.com)** with 200+ products across 10 brands. Our [blog](https://queryra.com/blog) and [FAQ page](https://queryra.com/faq) are also powered by Queryra.

### How much does it cost?
14-day free trial with 100 products and 500 searches/month. No credit card required. After the trial, Pro plans start at $9.99/month. Genesis Club members get 30 days free and 200 products. [See pricing →](https://queryra.com/pricing)

### How is this different from ChatGPT search plugins?
Queryra trains a custom model on YOUR content. No OpenAI account needed. Plus, Queryra understands price filters, brand exclusions, and sorting in natural language — not just semantic meaning. [Learn more →](https://queryra.com/blog/beyond-vector-search-woocommerce)

### How is Queryra different from Algolia?
Algolia costs $50-500/month and requires developer setup. Queryra starts with a free trial and takes 5 minutes to install. [Compare →](https://queryra.com/pricing)

### Does it work with my theme?
Yes! Works with any WordPress theme. Hooks into standard WordPress search functionality.

### Will it slow down my site?
No. Search queries are processed by Queryra's servers in milliseconds. No impact on your WordPress hosting.

### What happens after the free trial?
You can upgrade to Pro ($9.99/mo), or join our [Partner Program](https://queryra.com/blog/partner-program-pro-for-free) to earn a free Pro account by writing reviews or sharing Queryra.

### What happens to my data if I deactivate the plugin?
Search returns to WordPress default. Your data stays in Queryra until you delete it from the [dashboard](https://queryra.com/dashboard).

For more questions, visit our [FAQ page →](https://queryra.com/faq)

## Roadmap

The roadmap is actively being shaped by user feedback:

- Custom hooks and filters for developers
- Multilingual support (WPML/Polylang)
- Advanced analytics dashboard
- Search widget for Gutenberg
- REST API expansion

Your input matters! [Contact us](mailto:support@queryra.com) or [open a discussion](https://github.com/GronRafal/queryra-wordpress-plugin/issues) to share your ideas.

## Support

* [Setup Guide](https://queryra.com/docs/wordpress-integration) — Step-by-step WordPress setup
* [Documentation](https://queryra.com/docs) — All documentation
* [FAQ](https://queryra.com/faq) — Common questions answered
* **Email:** support@queryra.com
* [GitHub Issues](https://github.com/GronRafal/queryra-wordpress-plugin/issues) — Bug reports
* [GitHub Discussions](https://github.com/GronRafal/queryra-wordpress-plugin/issues) — Ask questions, share tips

## Contributing

We welcome contributions!

**Report Bugs** — [Open an issue](https://github.com/GronRafal/queryra-wordpress-plugin/issues) with WordPress version, PHP version, steps to reproduce, and expected vs actual behavior.

**Suggest Features** — [Email us](mailto:support@queryra.com) or [open a discussion](https://github.com/GronRafal/queryra-wordpress-plugin/issues).

**Submit Code:**
1. Fork the repo
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

```bash
# Clone repo
git clone https://github.com/GronRafal/queryra-wordpress-plugin.git
cd wordpress-plugin

# Install dev dependencies (optional)
composer install --dev

# Run WordPress coding standards check
phpcs --standard=WordPress
```

## Changelog

### 1.1.5 (2026-03-03)
**Batched bulk import for large sites**
- New: Batched import with real-time progress bar (supports 50K+ records)
- New: Plan limit check before import starts
- Improved: Import reliability with automatic retry support
- Improved: Setup Wizard import now uses batched sync

### 1.1.4 (2026-02-25)
**Configurable cache & content type filtering**
- New: Configurable cache duration (1 minute to forever)
- New: Record type (post/page/product) and platform metadata
- New: Search filtering by content type
- Improved: Search Analytics with top queries and zero-result queries
- Improved: Cache settings UI in admin panel

### 1.1.3 (2026-02-12)
**WooCommerce demo store & updated documentation**
- New: Live WooCommerce demo store at woo.queryra.com
- Improved: Plugin description with tested search examples from demo store
- Improved: Simplified search integration for better theme compatibility

### 1.1.2 (2026-02-02)
**Anonymous usage analytics**
- New: Anonymous event tracking to understand user flow
- New: Opt-out via `define('QUERYRA_DISABLE_ANALYTICS', true);` in wp-config.php
- Privacy: No personal data collected — see [Privacy Policy](https://queryra.com/privacy)

### 1.1.1 (2026-02-01)
**Full WooCommerce product search support**
- New: Product SKU, price, and attribute indexing
- New: Smart product boost controls
- Improved: Setup Wizard with one-click product import
- Improved: Search relevance algorithm

### 1.1.0 (2026-01-28)
**Enhanced user experience and onboarding**
- New: Setup Wizard — 4-step guided onboarding for new users
- New: Tabbed admin interface — organized settings for better UX
- New: Exit survey — collect feedback on plugin deactivation
- Enhanced: WooCommerce product indexing improvements

### 1.0.0 (2026-01-23)
**Initial public release**
- Production-ready AI search trained on your content
- WordPress.org directory submission
- Full security audit passed

## License

GPL v2 or later - see [LICENSE](LICENSE) file for details

---

## Links

- [WordPress.org Plugin Page](https://wordpress.org/plugins/queryra-ai-search/)
- [WooCommerce Live Demo](https://woo.queryra.com) — Demo beauty store with over 200 products
- [Queryra Homepage](https://queryra.com)
- [Start Free Trial](https://queryra.com/signup)
- [Pricing](https://queryra.com/pricing)
- [Documentation](https://queryra.com/docs)
- [Setup Guide](https://queryra.com/docs/wordpress-integration)
- [FAQ](https://queryra.com/faq)
- [Partner Program](https://queryra.com/blog/partner-program-pro-for-free)
- [Blog](https://queryra.com/blog) — Tips on WooCommerce search optimization and AI product discovery
- [Why Vector Search Alone Isn't Enough for Ecommerce](https://queryra.com/blog/beyond-vector-search-woocommerce)

---

**Made with ❤️ for the WordPress community**

[⭐ Star Queryra AI Search Plugin](https://github.com/GronRafal/queryra-wordpress-plugin) if you find it useful!