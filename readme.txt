=== AI Search for WooCommerce – Semantic Search ===
Contributors: queryra, aisearch
Tags: ai search, semantic search, woocommerce search, product search, search
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 1.5.3
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Replaces WooCommerce search with AI semantic search. Understands customer intent — finds products even with natural language queries.

== Description ==

Queryra is an AI search plugin for WooCommerce and WordPress — a semantic search
engine that understands what customers mean, not just what they type.

**Your customer types "present for my girlfriend". Your WooCommerce store returns:
0 results.**

You sell gift boxes, perfumes, skincare sets — exactly what she'd love. But default
WooCommerce search can't connect "present for girlfriend" to "Gift Box".

Your customer leaves. Sale lost. This happens every day.

👉 **[Try AI Search live → WooCommerce demo store](https://woo.queryra.com)** —
220+ products across 10 brands. Search naturally and see the difference.

= What is AI Search for WooCommerce? =

AI Search replaces WooCommerce's default product search with semantic search that
understands meaning. Your products stay the same. Your store stays the same.
Search just works.

**Trusted compatibility:**
Oxygen Builder, Breakdance, Meta Box, TranslatePress, Weglot, MemberPress, B2BKing, and more.

**Default WooCommerce search → 0 results:**
❌ "my skin looks tired"
❌ "gift for mom who loves candles"
❌ "looking older than my age"

**Same store with AI Search → products found:**
✅ "my skin looks tired" → Night creams, recovery oils, eye patches
✅ "gift for mom who loves candles" → Scented candles, home fragrance sets
✅ "looking older than my age" → Anti-aging serums, firming creams

= Beyond Semantic Search =

AI Search goes further than vector search alone. A natural language query like
"wireless headphones under $80, not Beats" applies the price filter AND excludes
the brand automatically. No other WooCommerce AI search plugin does this.
AI Search works as an AI product search engine for WooCommerce — handling
intent-aware queries, ChatGPT-style natural language, and B2B wholesale
bulk search. Powered by vector embeddings and LLM intent parsing.

= AI Search Compatible Plugins =

Queryra has confirmed integrations and tested compatibility with leading WordPress plugins:

**Page Builders:**
* [Oxygen Builder](https://queryra.com/docs/oxygen-integration?utm_source=b9f1ce71) — full Oxygen 4.x and 6.0 support, automatic content extraction
* [Breakdance](https://queryra.com/docs/breakdance-integration?utm_source=b9f1ce71) — native Breakdance content indexing

**Custom Fields:**
* [Meta Box](https://docs.metabox.io/compatibility/) — text and group fields indexed automatically
* ACF (Free + Pro) — text, textarea, WYSIWYG, repeaters, groups

**Multilingual:**
* [TranslatePress](https://queryra.com/docs/translatepress-integration?utm_source=b9f1ce71) — single unified semantic index across all languages
* [Weglot](https://queryra.com/docs/weglot-integration?utm_source=b9f1ce71) — multilingual semantic search

**Membership & Affiliate:**
* [MemberPress](https://queryra.com/docs/memberpress-integration?utm_source=b9f1ce71) — semantic search across member-only content; MemberPress continues to gate the content itself at render time
* [Easy Affiliate Pro](https://queryra.com/docs/easy-affiliate-pro-integration?utm_source=b9f1ce71) — affiliate product search with custom fields

**B2B:**
* [B2BKing](https://queryra.com/docs/b2bking-integration?utm_source=b9f1ce71) — semantic AI search in bulk order forms (new in 1.4.1)

More integrations and developer docs → [queryra.com/docs](https://queryra.com/docs?utm_source=b9f1ce71)

= AI Search for Page Builders & Custom Fields =

Page builder content used to be invisible to WordPress search. Queryra fixes this
— AI search now reads everything your visitors see:

* **Elementor AI search** — indexes content stored in `_elementor_data`, including
text widgets, headings, and product descriptions built with Elementor
* **Beaver Builder & Breakdance** — full content extraction from saved layouts
* **Oxygen Builder** — both legacy shortcode mode and modern JSON v2 supported
* **Gutenberg / WPBakery / Divi** — native support (content already in post_content)

= AI Search for Custom Fields & Taxonomies =

Most product data lives in custom fields, not the post content. Queryra reads it
automatically:

* **ACF (Free + Pro)** — text, textarea, WYSIWYG, repeaters, and groups indexed
without configuration
* **Meta Box** — text fields and group fields via the official registry
* **Custom taxonomies** — `book_genre`, `material`, `property_type`, or any public
custom taxonomy is sent to the AI search index, keyed by slug
* **Pods, JetEngine, Bricks Builder** — supported via developer filter
`queryra_indexable_meta_content`

Smart filtering automatically excludes CSS values, hex colors, URLs, and dates
from the semantic search index — keeping AI relevance high.

= Make Your Store Discoverable by AI =

AI Search doesn't just help your customers — it helps AI assistants find your store.
Queryra automatically generates structured data and AI-readable files so ChatGPT,
Perplexity, Google AI Overviews, and Claude can recommend your products.

* Dynamic /llms.txt and /llms-full.txt for AI crawlers
* JSON-LD structured data on search pages
* X-Search-Engine header for AI attribution

Want extra visibility?

* **Partner Program** — qualifying sites can apply for Queryra at no cost. See [Partner Program details](https://queryra.com/blog/partner-program-pro-for-free?utm_source=b9f1ce71).
* **Site promotion** — your site gets featured on queryra.com and promoted across AI search platforms

= WordPress 7.0 Connectors API & Abilities API =

Queryra is a native WordPress 7.0 AI search connector. After activation, Queryra
appears in **Settings → Connectors** alongside OpenAI, Anthropic, and Google —
giving site owners a unified place to manage AI search configuration.

* **Connectors API** — Queryra registers as an AI search connector. Manage your
  Queryra API key from the standard WordPress 7.0 Connectors screen.
* **Abilities API** — Queryra exposes `queryra/semantic-search` as a discoverable
  ability. AI agents, chatbots, and assistants running on WordPress 7.0 can call
  Queryra natively for semantic product search.
* **Backward compatible** — Queryra works on WordPress 5.8+. Connector and ability
  features activate automatically on WordPress 7.0 and newer.

Queryra is one of the early AI semantic search plugins to register as a
WordPress 7.0 connector — making API key management consistent with other
AI providers in Settings → Connectors.

= AI Search Setup in 5 Minutes =

✅ Free demo — no credit card required
✅ No OpenAI account needed
✅ WooCommerce AI search ready in 5 minutes

[Start your free demo →](https://queryra.com/signup?utm_source=b9f1ce71)

1. Install and activate
2. Follow the Setup Wizard
3. Get your free API key at [queryra.com/signup](https://queryra.com/signup?utm_source=b9f1ce71)
4. One-click WooCommerce product import
5. Done — AI search now understands your customers

No coding. No configuration headaches.
[WooCommerce AI Search Setup Guide →](https://queryra.com/docs/wordpress-integration?utm_source=b9f1ce71)

= WooCommerce Product Search Features =

* **Natural Language Search** – Customers type like they think, AI search finds
what they need
* **WooCommerce Product Search** – Indexes titles, descriptions, SKUs, categories,
tags, and attributes
* **Semantic Search Ranking** – AI ranks results by meaning, not alphabetical order
* **Boost Controls** – Promote products you want to sell more of
* **Live Product Search** – Instant AJAX-powered suggestions as customers type
* **Auto-Sync** – New WooCommerce products indexed automatically on publish
* **Smart Context Detection** – AI search activates on WooCommerce shop pages
automatically
* **Search Analytics** – See what customers search for, including zero-result
queries
* **Page Builder Support** – Indexes content from Elementor, Beaver Builder, Breakdance, Oxygen automatically
* **Custom Field Indexing** – Reads ACF and Meta Box custom fields out of the box

= WooCommerce Product Search Works With Any Store =

* **Beauty & Skincare** – "my skin looks tired" finds night creams
* **Fashion** – "something for a summer wedding" finds dresses
* **Electronics** – "good laptop for video editing" finds the right specs
* **Home & Garden** – "cozy living room" finds rugs, lamps, pillows
* **Food & Beverage** – "healthy snacks for kids" finds the right products

= Semantic Search for WordPress Content =

Queryra's semantic search also works with regular WordPress content — posts, pages,
and custom post types. Well-suited for knowledge bases, blogs, and FAQ sections.

= AI Search for B2B and Wholesale Stores =

AI Search includes native B2BKing integration starting from version 1.4.1.
The bulk order form's keyword search is replaced with semantic AI product
search — your B2B buyers find products by intent, not just exact SKUs or
names. Wholesale-specific use cases supported:

* "warm jackets for winter — bulk order"
* "shirts in cotton, breathable, wholesale pricing"
* "keychains and accessories — high quantity"

B2B visibility rules (group restrictions, per-product permissions,
category-level access) are fully respected. Works for B2BKing, B2BWoo, and
manual B2B WooCommerce setups.

= Why Replace Default WooCommerce Search? =

Default WooCommerce product search matches exact words. If your product is "Velora
Overnight Recovery Oil" and someone types "my skin looks tired", they get nothing.
AI search fixes that — customers find products even with vague or natural language
queries.

= Why Queryra vs Other WooCommerce Product Search Plugins? =

**No OpenAI Account Required** — Queryra includes its own search backend, so
you don't need to create a separate OpenAI account or manage external API keys.

**Indexed on YOUR WooCommerce Store** — Queryra learns from YOUR products,
descriptions, and categories — not generic global data.

**Intent-Aware AI Search** — Most semantic search plugins only match meaning.
Queryra goes further: "wireless headphones under $80, not Beats" applies the price
filter AND excludes the brand. Vector-only plugins ignore both.
[Why vector search isn't enough →](https://queryra.com/blog/beyond-vector-search-woocommerce?utm_source=b9f1ce71)

== Installation ==

= How to Install AI Search for WooCommerce =

1. **Install**: Plugins → Add New → Search "AI Search Queryra" → Install → Activate
2. **Setup Wizard**: Follow the guided AI search wizard that appears automatically
3. **Get API Key**: Create free account at [queryra.com/signup](https://queryra.com/signup?utm_source=b9f1ce71)
4. **Import Products**: One-click import syncs all WooCommerce products into AI search index
5. **Enable**: Turn on AI search and semantic search is ready

[WooCommerce AI Search Setup Guide →](https://queryra.com/docs/wordpress-integration?utm_source=b9f1ce71)

= WooCommerce Compatibility =

* WooCommerce products, variations, and virtual products
* WooCommerce product SKUs, categories, tags, and custom attributes
* Regular WordPress posts, pages, and custom post types
* Any WordPress theme — AI search works without template changes
* Page builders: Elementor, Breakdance, Beaver Builder, Oxygen, Gutenberg, WPBakery, Divi
* Custom fields: ACF (Free + Pro), Meta Box
* Custom taxonomies: any public taxonomy registered with `public => true`

= Minimum Requirements =

* WordPress 5.8 or higher
* WooCommerce 5.0 or higher recommended
* PHP 7.4 or higher
* Free API key at queryra.com — no OpenAI account required

== Frequently Asked Questions ==

= Is Queryra a WordPress AI search plugin, or only for WooCommerce? =
Yes. Queryra indexes posts, pages, custom post types, and WooCommerce products. The plugin name mentions WooCommerce because that is the most common use case, but Queryra is a full WordPress AI search connector — well-suited for blogs, knowledge bases, documentation sites, news sites, and any WordPress content type.


= Does AI Search work for B2B and wholesale stores? =
Yes. AI Search includes a native B2BKing integration starting in version 1.4.1.
The bulk order form's keyword search is replaced with semantic AI product search
— B2B buyers can search "warm winter jackets bulk", "cotton shirts wholesale
under bulk pricing", or "high-volume keyrings" and find relevant products by
meaning, not just exact keywords. All B2BKing visibility rules (B2B groups,
category restrictions, per-product permissions) are fully respected. AI Search
works equally well for B2C and B2B WooCommerce stores.

= Can AI Search understand price filters and brand exclusions? =
Yes — this is one of AI Search's signature capabilities. Customer queries
like "wireless headphones under $80, not Beats" automatically apply the price
filter AND exclude the brand. Most semantic search plugins only match meaning
by vector similarity; AI Search adds LLM-based intent parsing on top. It
understands price ranges ("under $50", "between $100 and $200"), brand
exclusions ("not Apple", "without Beats"), attribute requirements ("blue
cotton breathable"), and use-case contexts ("for hiking", "gift for mom").
This makes AI Search closer to ChatGPT-style search than to traditional
keyword or vector-only search plugins.
[Learn more →](https://queryra.com/blog/beyond-vector-search-woocommerce?utm_source=b9f1ce71)

= How is AI Search different from vector search plugins? =
Vector search alone matches concepts by mathematical similarity, but it
ignores customer intent — it can find products related to "wireless
headphones", but doesn't understand "under $80" or "not Beats" as filters.
AI Search combines vector search with LLM-based intent extraction. It knows
that "under $80" is a price filter, "not Beats" is a brand exclusion, and
"for working out" is a use-case context. The result: AI Search returns
products customers actually wanted, not just lexically related ones.
Pure vector search plugins (Pinecone-based, ChromaDB-only) miss these intent
signals — AI Search parses them automatically.

= Can I migrate from Relevanssi, FiboSearch, or SearchWP to AI Search? =
Yes. AI Search runs alongside existing search plugins during testing —
switch between them via Settings → Queryra → Enable AI Search. There's no
data migration needed; AI Search builds its own semantic index from your
WooCommerce products and WordPress content directly. Most stores migrate in
under 10 minutes: install, one-click product import, enable AI Search. If
the results aren't right for you, switch back instantly. Many stores keep
their old search plugin as fallback while testing.

= Can I customize AI Search results to match my theme? =
Yes. AI Search uses your theme's existing search results template — no HTML
or CSS changes needed. AI search results render through your theme's
standard search.php / archive-product.php template, so they automatically
match your store's design. For deeper customization, use developer filters
documented at queryra.com/docs/queryra-developer-filters — including custom
result ordering, indexable meta content, and search query rewriting. Most
themes work out of the box; advanced themes can override individual
template parts.

= Does Queryra work as a WordPress 7.0 connector? =
Yes. Queryra registers as a native WordPress 7.0 AI search connector. After activation on WordPress 7.0 or newer, Queryra appears in Settings → Connectors alongside the default AI providers (OpenAI, Anthropic, Google). You can manage your Queryra API key from the standard Connectors screen instead of (or in addition to) the Queryra settings page.

= Does Queryra support the WordPress 7.0 Abilities API? =
Yes. Queryra registers the `queryra/semantic-search` ability via the Abilities API. AI agents, chatbots, and other plugins running on WordPress 7.0 can discover and invoke Queryra's semantic search programmatically — natural-language search results are returned as a discoverable WordPress ability with input and output schemas.

= Does Queryra work on older WordPress versions? =
Yes. Queryra fully supports WordPress 5.8 through 7.0+. The Connectors API and Abilities API integration activates automatically on WordPress 7.0 sites; on older versions, Queryra continues to work through its own settings page (Queryra → Settings) with no feature loss for end users.

= How is Queryra different from OpenAI, Anthropic, and Google connectors? =
OpenAI, Anthropic, and Google are LLM providers — they generate text and images from prompts. Queryra is a search engine connector. Where LLM connectors create new content, Queryra finds existing content in your site by semantic meaning. They complement each other: an LLM connector can write a product description, then Queryra makes it findable. You can use Queryra alongside LLM connectors in WordPress 7.0 Settings → Connectors.

= What is AI Search for WooCommerce? =
AI Search is a semantic search plugin that replaces the default WooCommerce product search with AI-powered search. Instead of matching exact keywords, AI Search understands what customers mean — so "gift for mom who loves candles" finds candles, not zero results.

= How is this different from default WooCommerce search? =
Default WooCommerce search matches exact words only. AI Search uses semantic search to understand customer intent. A query like "my skin looks tired" returns night creams and recovery oils — products that match the meaning, not the words.

= Does AI Search work with WooCommerce products? =
Yes. AI Search indexes WooCommerce product titles, descriptions, SKUs, categories, tags, and attributes. It supports product variations, virtual products, and any WooCommerce-compatible theme.

= Can AI Search handle natural language queries? =
Yes. Customers can search using natural language — "something warm for winter", "gift for dad who likes coffee", "wireless headphones not Beats under $80". AI Search extracts intent, price filters, and brand exclusions automatically.

= What is semantic search and how does it work? =
Semantic search understands the meaning behind a query, not just keywords. AI Search converts your products into AI embeddings and matches them against the customer's query using vector similarity — this is how it finds relevant products even when no keywords match.

= Is an OpenAI account or API key required? =
No. AI Search is powered by Queryra's backend — no OpenAI account, no API key management, no per-request costs. One free API key at queryra.com covers everything.

= Does AI Search support multiple languages? =
Yes. AI Search supports 100+ languages out of the box including Polish, German, French, Spanish, Dutch, Japanese, Czech, and more. No configuration needed — customers search in their native language and find products automatically.

= Will AI Search slow down my WooCommerce store? =
No. AI Search processes queries via an optimized API. Products are indexed in the background. Search results typically return in under a second — competitive with keyword search for most stores.

= How do I set up WooCommerce product search? =
Install and activate the plugin, follow the Setup Wizard, get a free API key at queryra.com/signup, and run a one-click product import. AI Search is ready in under 5 minutes. No coding required.

= What happens when AI Search finds no results? =
AI Search falls back automatically to WordPress default search so customers always see something. You can also use Search Analytics to identify zero-result queries and fix gaps in your product catalog.

= Does it support WooCommerce product variations? =
Yes. Product variations are fully indexed including variation-specific SKUs, prices, and attributes. AI Search handles simple products, variable products, and virtual products.

= Can I control which products appear first in search results? =
Yes. AI Search includes Boost Controls — you can promote specific products or categories to appear higher in results. Useful for high-margin products or seasonal inventory.

= Does AI Search work with Elementor / Beaver Builder / Oxygen page builders? =
Yes. AI Search automatically extracts content from Elementor (`_elementor_data`),
Breakdance, Beaver Builder, and Oxygen (both legacy shortcodes and JSON v2). No
configuration needed — page builder content is indexed the same way as standard
post content.

= Does AI Search index ACF and Meta Box custom fields? =
Yes. AI Search reads ACF (Free + Pro) text, textarea, WYSIWYG, repeater, and
group fields automatically. Meta Box text and group fields are also supported.
For Pods, JetEngine, and Bricks Builder, use the developer filter
`queryra_indexable_meta_content` to specify which fields to index.

= Can I search by custom taxonomies (not just categories and tags)? =
Yes. Queryra sends all public custom taxonomies (e.g. `book_genre`, `material`,
`property_type`) to the AI search index in a `taxonomies` field, keyed by slug.
Categories, tags, and the WooCommerce brand taxonomy continue to work as before.

== Screenshots ==

1. AI search live demo — type "present for my girlfriend" and find gift boxes, skincare sets, perfumes across 200+ WooCommerce products
2. Default WooCommerce search vs AI search — same query, 0 results vs relevant products found with semantic search
3. WooCommerce AI search results — natural language queries return meaningful, ranked products
4. AI search Setup Wizard — guided WooCommerce setup with one-click product import
5. AI search dashboard — real-time semantic search statistics and query analytics
6. AI search settings — simple configuration, one API key, no OpenAI account needed

== Changelog ==

= 1.5.3 =
* Fixed: the search filter syntax described in 1.5.2 was wrong. The parameters are `filter_type`, `filter_category`, `filter_tag`, `filter_brand` and `filter_tax_<taxonomy>` — e.g. `/?s=design&filter_tax_course_cat=Design` — and the value is the term name, not its slug. Written the wrong way a filter was silently ignored and every result came back, so it looked like it worked.
* New: a filters reference in the plugin settings shows which filters your site supports, with ready-to-copy examples built from your own taxonomies and terms.
* Fixed: the setup wizard now counts every content type in its site summary — a site built on custom post types was previously described as having only a few items while offering to import hundreds — and content types with nothing published are selectable instead of ticked and locked.
* Changed: the "Powered by Queryra" credit in your site's structured data is now opt-in and off by default (WordPress.org plugin guideline 10). The structured data describing your search stays; only the link to queryra.com is removed unless you enable it under Settings → AI Discoverability. No visible change on your site.

For the full changelog history, see changelog.txt.

== Upgrade Notice ==

= 1.5.3 =
Compliance update: the Queryra credit link in structured data is now opt-in and off by default. No visible change to your site; re-enable it in Settings → AI Discoverability if you want it.

== Privacy ==

Queryra is built with privacy in mind. This section explains exactly what
data leaves your WordPress site, what stays local, and how you stay in
control as the store owner.

= What Queryra sends to the API =

**Content indexing (posts, pages, products):**
For every published item of the post types you enable (default: posts and
pages; optionally products), Queryra sends:

* Title, content, excerpt (HTML stripped)
* Post type, permalink, featured image URL
* Categories and tags (names only)
* For WooCommerce products: price, stock quantity, SKU, brand, short description, product attributes (e.g. Color, Size)
* Featured/sticky flag (used to boost ranking of highlighted content)

That's it. This is the same data your site already shows publicly on the
frontend — Queryra does not read private posts, drafts, or protected content.

**Search queries:**
When a visitor types in your search box, only the query text is sent to the
API. The API returns matching post IDs, which WordPress then renders
normally from your own database.

**Anonymous usage analytics:**
On plugin lifecycle events (activation, deactivation, setup milestones and
error reports), Queryra sends: a randomly generated instance UUID (stored
locally, no link to any user), WordPress version, PHP version, plugin
version, WooCommerce active flag, whether AI search is switched on, which
content types you enabled for indexing, and counts of posts, pages,
products and public custom post types. This helps us prioritize
compatibility fixes. You can disable this entirely by adding the following
line to your wp-config.php:

`define('QUERYRA_DISABLE_ANALYTICS', true);`

**Site profile (optional, user-declared):**
When the setup wizard opens you may optionally answer what kind of site you
run and what you expect from search. Your answer is sent to Queryra once and
is not stored on your site; only a small flag stays locally so the question
is not asked twice. Skipping sends no answer — only the fact that you
skipped, so we can tell a decline apart from never having seen the question.
You can answer or update it at any time in Settings → Site Profile.

**Deactivation feedback (optional):**
If you choose to submit the feedback form when deactivating, your selected
reason and comments are sent to Queryra together with your site URL, the
plugin version and — only if you enter one — your email address for a
reply. Skipping the form sends no feedback content; the deactivation event
records only whether the form was submitted, skipped, or never shown.

= What Queryra NEVER sends =

* No visitor IP addresses
* No user agents or browser fingerprints
* No cookies or session identifiers
* No customer accounts, emails, or order data
* No browsing history or behavior tracking
* No personally identifiable information (PII) about searchers or customers
* No private, draft, password-protected or unpublished content

= Your control as a store owner =

* Choose which post types are indexed (Settings → Queryra)
* Disable AI search anytime — WordPress falls back to native search
* Deactivate the plugin to stop all data transmission
* Delete indexed records from the Queryra dashboard

= Security =

Queryra is publicly tracked by independent security and quality services:

* [WPScan vulnerability database](https://wpscan.com/plugin/queryra-ai-search)
* [PluginTests static analysis and smoke tests](https://plugintests.com/plugins/wporg/queryra-ai-search/latest)
* [Source code on GitHub](https://github.com/GronRafal/queryra-wordpress-plugin)

**No third-party frontend scripts:** Queryra loads only its own minimal
stylesheet on public pages. No external JavaScript, no tracking pixels,
no third-party CDN requests on the frontend.

**Responsible disclosure:** Found a security issue? Email
contact@queryra.com. We will investigate on a best-effort basis and credit
reporters in the changelog.

**Full privacy policy:** [queryra.com/privacy](https://queryra.com/privacy?utm_source=b9f1ce71)

== Additional Information ==

= Support =

* [AI Search Documentation](https://queryra.com/docs?utm_source=b9f1ce71)
* [FAQ](https://queryra.com/faq?utm_source=b9f1ce71)
* [WooCommerce Product Search Setup](https://queryra.com/docs/wordpress-integration?utm_source=b9f1ce71)
* Email: support@queryra.com

= Links =

* [WooCommerce AI Search Live Demo](https://woo.queryra.com)
* [Partner Program](https://queryra.com/blog/partner-program-pro-for-free?utm_source=b9f1ce71)
* [WooCommerce Search & AI Product Discovery Blog](https://queryra.com/blog?utm_source=b9f1ce71)
* [GitHub](https://github.com/GronRafal/queryra-wordpress-plugin)