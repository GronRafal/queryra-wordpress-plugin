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

[Start Free Trial](https://queryra.com/signup) · [AI Search Documentation](https://queryra.com/docs) · [FAQ](https://queryra.com/faq) · [Pricing](https://queryra.com/pricing) · [Blog](https://queryra.com/blog)

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

**Indexed on YOUR Store**
Generic AI plugins use the same model for everyone. Queryra learns from YOUR products, YOUR descriptions, YOUR categories — your content, not generic global data.

**No ChatGPT Key Required**
Other plugins require you to create an OpenAI account and manage API keys. Queryra includes everything – one API key, no extra accounts.

**WordPress-Native**
Built specifically for WordPress and WooCommerce. Auto-syncs on publish, supports product variations, works with any theme. [WordPress Product Search Setup Guide →](https://queryra.com/docs/wordpress-integration)

**Intent-Aware Search**
Most AI plugins only match meaning. Queryra adds a second layer: a query like "wireless headphones under $80, not Beats" applies the price filter AND excludes the brand. Vector-only plugins ignore both constraints. [Why vector search alone isn't enough for WooCommerce product search →](https://queryra.com/blog/beyond-vector-search-woocommerce)

## Built for WooCommerce

* **Product Search** – Search by title, description, SKU, categories, tags, and attributes
* **Smart Product Ranking** – AI understands which products best match the query
* **Boost Control** – Promote high-margin products or slow-moving inventory
* **Live Search Results** – Instant AJAX-powered search suggestions
* **Auto-Sync** – New products indexed automatically when published
* **Smart Context Detection** – Automatically searches only products in WooCommerce shop pages, posts elsewhere
* **Search Analytics** – See what customers search for, including zero-result queries. Find gaps in your inventory before customers leave

## Well-Suited for Any WooCommerce Store

* **Beauty & Skincare** – "my skin looks tired" finds night creams and recovery oils — [see it live →](https://woo.queryra.com)
* **Fashion** – Find items by style, occasion, or color family
* **Electronics** – Search by features, not just model numbers
* **Home & Garden** – "cozy living room" finds rugs, lamps, pillows
* **Food & Beverage** – "healthy snacks" finds protein bars, nuts
* **Any catalog** – Works with any product type

## Beyond WooCommerce

Queryra also works with regular WordPress content — posts, pages, and custom post types. Well-suited for knowledge bases, blogs, and FAQ sections.

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
    C --> D[AI Indexing]
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

Your search is now powered by AI indexed on your content. [WooCommerce Search Plugin Setup Guide →](https://queryra.com/docs/wordpress-integration)

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 5.8+ |
| PHP | 7.4+ |
| MySQL | 5.6+ |
| HTTPS | Recommended |

**Tested with:**
- WordPress 7.0, 6.9, 6.7.2, 6.6, 6.5
- PHP 8.2, 8.1, 8.0, 7.4
- Popular themes: Twenty Twenty-Four, Astra, Kadence, GeneratePress

## Pricing

Free trial available. No credit card required. No ChatGPT API key costs. No hidden fees.

[See pricing & plans →](https://queryra.com/pricing) · [Join Genesis Club →](https://queryra.com/signup) · [Partner Program →](https://queryra.com/blog/partner-program-pro-for-free)

## FAQ

### Can I see a live demo before installing?
Yes! Try our **[WooCommerce demo](https://woo.queryra.com)** with 200+ products across 10 brands. Our [blog](https://queryra.com/blog) and [FAQ page](https://queryra.com/faq) are also powered by Queryra.

### How much does it cost?
Free trial available — no credit card required. [See current pricing and plans →](https://queryra.com/pricing)

### How is this different from ChatGPT search plugins?
Queryra trains a custom model on YOUR content. No OpenAI account needed. Plus, Queryra understands price filters, brand exclusions, and sorting in natural language — not just semantic meaning. [Why vector search alone isn't enough for WooCommerce product search →](https://queryra.com/blog/beyond-vector-search-woocommerce)

### How is Queryra different from enterprise search platforms?
Enterprise search platforms are powerful but typically aimed at developers and require integration work. Queryra is built specifically for WordPress and WooCommerce — install via WordPress admin, connect with an API key, and you're ready. [See pricing →](https://queryra.com/pricing)

### Does it work with my theme?
Yes! Works with any WordPress theme. Hooks into standard WordPress search functionality.

### Will it slow down my site?
No. Search queries are processed by Queryra's servers in milliseconds. No impact on your WordPress hosting.

### What happens after the free trial?
You can upgrade to a paid plan, or join our [Partner Program](https://queryra.com/blog/partner-program-pro-for-free) to earn a free Pro account by writing reviews or sharing Queryra. [See pricing →](https://queryra.com/pricing)

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

* [WooCommerce Product Search Setup](https://queryra.com/docs/wordpress-integration) — Step-by-step WordPress setup
* [AI Search Documentation](https://queryra.com/docs) — All documentation
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

### 1.5.1 (2026-07-21)
**Setup survey, deactivation feedback rework, cache fix**
- New: optional setup question shown when the wizard opens — what kind of site this is, and what you want better search to do for it. One click, fully optional, visible Skip. Asked before setup so it still reaches people who hit trouble during installation.
- Privacy: the answer is not stored on the site — it is sent to Queryra once and only a small flag stays locally so the question is not asked twice. Answerable and updatable in Settings → Site Profile.
- New: deactivation and survey events record interaction state (submitted / skipped / never shown), so a decline can be told apart from a WP-CLI or bulk deactivation. No answer content is ever sent for a skip.
- Fixed (important): the search cache was silently switched to "Disabled" whenever any other settings tab was saved — all tabs share one settings group and the cache duration only existed on the Cache tab, so saving elsewhere wrote an empty value over it. Many sites were sending an API call for every single search without knowing.
- Fixed: deactivation feedback now uses one comment box whose prompt changes with the selected reason. Previously each reason had its own box, so text typed under one reason was silently dropped after picking another — and two reasons had no box at all.
- Improved: deactivation feedback adds a "trial limits were too small" reason, an optional reply-to email address, and is recorded as an analytics event (source of truth) alongside the email notification.
- Fixed: the activation event was missing on some hosts, and reported zero posts/pages/products when it did arrive. It is now sent after WordPress has registered all post types.
- New: usage analytics include whether AI search is switched on — the feature ships disabled, so "indexed content but no searches" was previously ambiguous.

### 1.5.0 (2026-07-11)
**Critical admin fix — Connectors polling removed**
- Fixed (critical): the WordPress 7.0 Connectors status notice ran on every wp-admin page and injected a script that repeatedly polled a REST endpoint. On rate-limited or managed hosts (nginx limits, Cloudflare, mod_security) this could trigger an HTTP 429 cascade that broke admin assets (jQuery, the block editor) and produced a blank admin screen. The notice now renders only on the Connectors screen and reads status server-side.
- Removed: the `/wp-json/queryra/v1/key-status` REST endpoint and all client-side polling. Key status is a single server-side read — zero background requests from the admin.
- Improved: admin JavaScript debug logging is silent unless `WP_DEBUG` is enabled, matching the PHP logger.

### 1.4.4 (2026-06-15)
**Custom Post Type support**
- New: AI search indexes any public custom post type (recipes, vehicles, portfolios, listings, courses), not just posts, pages and WooCommerce products. Enable each type in Settings → Content or during the setup wizard.
- New: ACF / Meta Box custom fields and custom taxonomies on those types are indexed automatically — the extraction engine is post-type agnostic.
- Improved: Records tab counts and lists enabled custom post types; trashing or unpublishing a CPT entry removes it from the index.
- Improved: anonymous analytics report a breakdown of public custom post types and their published-post counts.

### 1.4.3 (2026-06-10)
**Error visibility, security and resilience**
- New: "Recent issues" panel on the Settings tab — import, search, API key and integration problems are visible in wp-admin instead of hidden in `debug.log`.
- New: error reporting for previously silent failure paths — auto-sync on save/delete, search fallback, B2BKing integration, key validation, and client-side import errors. Anonymous and bounded; honours `QUERYRA_DISABLE_ANALYTICS`.
- Security: fixed an XSS in the setup wizard test-search results — record names returned from the API are escaped before rendering in admin.
- Fixed: trashed or unpublished posts are removed from the index (previously only permanent deletion was synced, so trashed products kept consuming record quota).
- Fixed: the setup wizard validates the API key before saving it, so a mistyped key no longer overwrites a working one.
- Fixed: "Clear All Search Cache" works on hosts with Redis or Memcached object caching; bulk import pagination is stable when many posts share a publish date.
- Improved: visitor-facing search uses a 5-second timeout with a 60-second back-off after failure, so a slow API cannot pile up hung requests.

### 1.4.2 (2026-06-02)
**Maintenance — UTM tracking, claim audit, Plugin Check compliance**
- New: AI search admin links carry tracking parameters (UTM) for support-side referral attribution. Sentinel `pre-init` flags fresh installs in logs.
- Improved: AI search documentation accuracy — refined Partner Program copy, broadened audience wording (sites, blogs, catalogues), and corrected technical phrasing (semantic AI search is indexed on content, not "trained").
- Improved: Replaced `rel="noreferrer"` with `rel="noopener"` on admin links to enable server-side referrer attribution.
- Fixed: Plugin Check warnings cleared — plugin name match between header and readme; inline JS values encoded via `wp_json_encode()`.

### 1.4.1 (2026-05-25)
**B2BKing integration — semantic AI search for bulk order forms**
- New: B2BKing (B2B for WooCommerce) integration replaces the bulk order form keyword search with Queryra semantic AI search. Loads only when B2BKing is active; core untouched.
- Security: Visibility-intersect respects B2B group, category, and per-product visibility rules. Fail-closed default when allow-list cannot be verified.
- Performance: O(1) intersect lookups scale to 100k+ products.
- UX: Bulk order form rebrand — search placeholder, sort option ("AI Pick"), loader text, and search icon updated to match the Queryra context.
- Toggle: Single master kill-switch shared with the main "Enable Queryra AI Search" option.

### 1.4.0 (2026-05-25)
**WordPress integration improvements & developer hooks**
- New: Search ability registered for site automation tools and AI assistants — Queryra semantic search is now discoverable and callable programmatically by other plugins and agents.
- New: Developer filter `queryra_validate_api_key` for external API key validation. Returns `true` on success, `WP_Error` on failure. Reuses the existing `Queryra_API::test_connection()` round-trip so validation behavior is identical everywhere.
- New: Defensive guard against destructive overwrites of saved configuration by external code.
- Improved: API key field uses partial masking with click-to-edit (last 4 characters visible) on both Settings tab and Setup Wizard.
- Improved: Tested up to the latest WordPress version. Backward compatible — all features work on WordPress 5.8 onwards.

### 1.3.2 (2026-05-20)
**AI search compatibility — Oxygen Builder 6.0 support**
- Added: AI search now indexes content from Oxygen Builder 6.0 stable — the new builder format (`_oxygen_data` with `tree_json_string` double-encoded JSON tree) is parsed automatically alongside Classic Oxygen 4.x
- Added: Pages built with Oxygen 6.0 are fully searchable via AI search — `OxygenElements\Text` nodes, headings, and all visible widgets are extracted and indexed
- Improved: AI search page builder coverage expanded — Oxygen Builder 6.0 joins Elementor, Breakdance, Beaver Builder, and Classic Oxygen for comprehensive AI search across the most popular WordPress page builders
- Improved: AI search relevance for sites migrated from Classic Oxygen 4.x to Oxygen 6.0 — both legacy (`_ct_builder_*`) and new (`_oxygen_data`) postmeta keys are read so no content is lost in the upgrade path

### 1.3.1 (2026-05-18)
**Critical fix — bulk sync on sites without custom taxonomies**
- Fixed: Bulk sync returned HTTP 422 on any site without custom taxonomies (the majority — blogs and stores using only standard categories/tags). The empty `taxonomies` field serialized as a JSON array `[]` instead of an object `{}`, failing API validation. Initial import and re-sync now work on all sites.

### 1.3.0 (2026-05-07)
**Page builder & custom field support — AI search now sees what visitors see**
- New: Automatic content extraction from Elementor (`_elementor_data` JSON), Breakdance, Beaver Builder, Oxygen (both legacy shortcodes and modern JSON v2)
- New: Automatic content extraction from ACF (Free + Pro) — text, textarea, wysiwyg, repeaters, groups
- New: Automatic content extraction from Meta Box — text fields and group fields via registry
- New: Smart text filtering excludes CSS values, hex colors, URLs, dates, and other technical strings automatically

**Custom taxonomies — beyond categories and tags**
- New: All public custom taxonomies (`book_genre`, `material`, `property_type`, etc.) are sent in a new `taxonomies` API field, keyed by slug
- Existing `categories`, `tags`, and `brand` fields unchanged for backward compatibility

**Search results display**
- Fixed: Search query now properly shows in the page title (`Search results for: "foo"`) and stays in the search input on results pages — previously both were empty after the AI resolved the query
- Implemented via `posts_search` filter; the `s` query var is preserved so themes work without modification

**Description quality**
- Fixed: Description was sending the first 30 words of content twice — only manual excerpts are now included, and skipped if they're a substring of the content (removes embedding bias)
- Fixed: WooCommerce `short_description` is now deduplicated against the long description

**Performance**
- Improved: Bulk sync prefetches all postmeta in one query per batch — dramatic speedup on sites with many custom fields (was N queries per post, now 1 query per batch)

**For developers**
- New filter: `queryra_indexable_meta_content` — add content from custom field plugins not auto-detected (Pods, JetEngine, custom postmeta)
- New filter: `queryra_indexable_taxonomies` — control which custom taxonomies are sent

#### Compatibility (v1.3.0)

Auto-detected page builders (no setup required):
- Gutenberg / Block Editor — native (content in `post_content`)
- WPBakery / Visual Composer — native (shortcodes in `post_content`)
- Divi — native (shortcodes in `post_content`)
- Elementor — new
- Breakdance — new
- Beaver Builder — new
- Oxygen — new

Auto-detected custom field plugins:
- ACF (Free + Pro) — new
- Meta Box — new

Not auto-detected (use developer filter):
- Pods, JetEngine, Toolset, Bricks Builder (intentional)

Backward compatibility:
- All existing API record fields are unchanged
- New `taxonomies` field is additive — older backends ignore it without issues
- No database schema changes, no options removed, no behavior changes for sites without builders or custom fields

### 1.2.0 (2026-05-07)
**AI Discoverability — make your search engine visible to LLMs**
- New: Dynamic `/llms.txt` and `/llms-full.txt` files for ChatGPT, Perplexity, Claude, Google AI Overviews
- New: JSON-LD structured data — `SearchResultsPage` on search pages, `Service` schema site-wide, both attributing search to Queryra
- New: `X-Search-Engine` HTTP header on search responses
- New: Detects existing static `llms.txt` and offers a copy-paste snippet for manual integration

**Admin UX**
- New: Plugin row meta links on Plugins page (Live Demo, Docs, Support, conditional Get API Key)
- New: "Try a test search" shortcut in Settings tab → opens wizard step 4
- New: Dismissible tip card on Settings & wizard — relabel suggestions to help visitors discover AI search
- New: Support tab — Clubs section (Sandbox + partner program) and "Need more records or searches?" link
- Improved: Support tab "Documentation" → "Resources" (broader scope: compatibility matrix, plugin tests)

**Cleanup & Security**
- Improved: All external admin links use `rel="noopener noreferrer"`
- Removed: Obsolete 1.1.4 upgrade notice

### 1.1.8 (2026-03-30)
**Multilingual search**
- Updated: Multilingual search now supports 50+ languages out of the box — no configuration needed
- Updated: Documentation to reflect multilingual support launch

### 1.1.7 (2026-03-19)
**Updated branding & centralized pricing**
- Updated: Plugin title and short description for better WordPress.org discoverability
- New: "Beyond semantic search" example — intent-aware price filter and brand exclusion demo
- Improved: Removed hardcoded pricing from all docs — single source of truth at queryra.com/pricing

### 1.1.6 (2026-03-15)
**Simplified settings & instance tracking**
- Removed: API URL field from settings (hardcoded to default)
- New: Instance ID visible in Support tab for easier troubleshooting
- Improved: Status endpoint sends instance_id and plugin_type for better tracking
- Improved: Partner referral tracking via site_url for partner API keys

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
- Production-ready AI search indexed on your content
- WordPress.org directory submission
- Full security audit passed

## License

GPL v2 or later - see [LICENSE](LICENSE) file for details

---

## Links

- [WordPress.org Plugin Page](https://wordpress.org/plugins/queryra-ai-search/)
- [WooCommerce AI Search Live Demo](https://woo.queryra.com)
- [AI Product Search Plugin](https://queryra.com)
- [Start Free Trial](https://queryra.com/signup)
- [Pricing](https://queryra.com/pricing)
- [AI Search Documentation](https://queryra.com/docs)
- [WooCommerce Product Search Setup](https://queryra.com/docs/wordpress-integration)
- [FAQ](https://queryra.com/faq)
- [Partner Program](https://queryra.com/blog/partner-program-pro-for-free)
- [WooCommerce Search & AI Product Discovery Blog](https://queryra.com/blog)
- [Why Vector Search Alone Isn't Enough for Ecommerce](https://queryra.com/blog/beyond-vector-search-woocommerce)

---

**Made with ❤️ for the WordPress community**

[⭐ Star Queryra AI Search Plugin](https://github.com/GronRafal/queryra-wordpress-plugin) if you find it useful!