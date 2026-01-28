# Queryra - AI Search for WordPress

[![WordPress Plugin](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](LICENSE)
[![WordPress.org](https://img.shields.io/badge/WordPress.org-Awaiting%20Review-orange.svg)](https://wordpress.org/plugins/queryra-ai-search/)

🔍 **AI-powered search trained on YOUR content** - not generic ChatGPT

Transform WordPress search from keyword matching to intelligent, context-aware discovery.
No ChatGPT API key needed. Works with any theme. See results in 5 minutes.

[Get Started](https://queryra.com/signup) • [Documentation](https://queryra.com/docs) • [FAQ](https://queryra.com/faq)

## Why Queryra?

- 🎯 **Custom AI Training** - Learns from YOUR content, not generic for every site
- ⚡ **No Setup Hassle** - No ChatGPT account, no API keys to manage
- 🔒 **Privacy First** - Your data stays secure, HTTPS encrypted
- 🚀 **WordPress Native** - Auto-sync on publish, works with any theme
- 💚 **Free to Start** - Test on your actual content before deciding

## Features

### Core Features
- 🤖 **Semantic AI Search** - Understands meaning, not just keywords
- 🛒 **WooCommerce Integration** - Indexes products with prices, stock, and featured status
- 🔄 **Auto-Sync** - Posts sync automatically when published or updated
- 📝 **Custom Post Types** - Works with posts, pages, and any custom type
- ⭐ **Sticky Post Priority** - Important posts and featured products rank higher
- 🎨 **Theme Compatible** - Works with any WordPress theme
- 📦 **Bulk Operations** - Send all existing posts with one click

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

[Detailed setup guide →](https://queryra.com/docs/wordpress-integration)

## Quick Start

### 1. Install Plugin

**Option A: WordPress Dashboard (Recommended)**
1. Download the plugin ZIP file
2. Go to **Plugins → Add New** in WordPress
3. Click **Upload Plugin**
4. Choose the ZIP file and click **Install Now**
5. Activate the plugin

**Option B: Manual Installation**
```bash
cd /wp-content/plugins/
# Upload queryra-search folder
# Activate via WordPress Admin → Plugins
```

### 2. Get API Key

[Sign up free](https://queryra.com/signup) at Queryra → Copy API key from dashboard

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
- New posts sync automatically when published ✨

### 5. Done! 🎉

Your search is now powered by AI trained on your content.

[View full documentation →](https://queryra.com/docs/wordpress-integration)

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 5.8+ |
| PHP | 7.4+ |
| MySQL | 5.6+ |
| HTTPS | Recommended |

**Tested with:**
- WordPress 6.7.1, 6.6, 6.5
- PHP 8.2, 8.1, 8.0, 7.4
- Popular themes: Twenty Twenty-Four, Astra, Kadence, GeneratePress

**Need an account?** [Sign up free at Queryra](https://queryra.com/signup)

## FAQ

### Do I need a Queryra account?
Yes, [sign up free](https://queryra.com/signup) to get an API key. No credit card required.

### How is this different from WordPress default search?
WordPress uses basic SQL keyword matching. Queryra uses AI trained on YOUR content to understand meaning and context.

### How is this different from generic ChatGPT plugins?
Generic plugins use the same ChatGPT model for every site. Queryra trains a custom model specifically on YOUR content, making results more relevant.

### Does it work with my theme?
Yes! Works with any WordPress theme. Hooks into standard WordPress search functionality.

### Does it work with Gutenberg/Classic Editor?
Both! Works with any editor that creates WordPress posts.

### Is it secure?
Yes. All data transmitted over HTTPS. Plugin follows WordPress security standards (nonces, sanitization, escaping).

### Will it slow down my site?
No. Search queries are processed by Queryra's servers, so there's no impact on your WordPress hosting.

### What happens to my data if I deactivate the plugin?
Search returns to WordPress default. Your data stays in Queryra until you delete it from the dashboard.

[More questions? Check our FAQ →](https://queryra.com/faq)

## Roadmap

🚀 **In Development**

The roadmap is actively being shaped by user feedback. We're building features that matter most to the WordPress community:

- Custom hooks and filters for developers
- Multilingual support (WPML/Polylang)
- Advanced analytics dashboard
- Search widget for Gutenberg
- REST API expansion

💡 **Your input matters!** Contact us at support@queryra.com to share your ideas and influence the direction of Queryra.

## Support

### Documentation
- [Setup Guide](https://queryra.com/docs/wordpress-integration) - Step-by-step WordPress setup
- [Complete Docs](https://queryra.com/docs) - All documentation
- [FAQ](https://queryra.com/faq) - Common questions answered

### Direct Support
- **Email:** support@queryra.com
- **Issues:** [GitHub Issues](https://github.com/GronRafal/queryra-wordpress-plugin/issues) for bug reports

### Community
- [GitHub Discussions](https://github.com/GronRafal/queryra-wordpress-plugin/discussions) - Ask questions, share tips

## Contributing

We welcome contributions! Here's how you can help:

### Report Bugs
Found a bug? [Open an issue](https://github.com/GronRafal/queryra-wordpress-plugin/issues) with:
- WordPress version
- PHP version
- Steps to reproduce
- Expected vs actual behavior

### Suggest Features
Have an idea? Email us at support@queryra.com or open a discussion on GitHub.

### Submit Code
1. Fork the repo
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

### Development
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

### 1.0.0 (2026-01-23)
🚀 **Initial public release**
- Production-ready AI search trained on your content
- Stable API with 99.9% uptime
- WordPress.org directory submission
- Full security audit passed

### 0.9.0 (2026-01-15) - Beta 2
- Improved relevance scoring with cosine similarity
- Added adaptive threshold filtering for better results
- Enhanced embedding generation with title weighting
- Fixed sync issues with large content libraries
- Performance optimizations for faster searches

### 0.8.0 (2026-01-08) - Beta 1
- Public beta release
- AI model training improvements
- Custom post type support
- Sticky post priority in search results
- Real-time sync on publish/update
- Settings dashboard with sync status
- Testing with 50+ beta users

### 0.7.0 (2025-12-20) - Alpha 3
- ChromaDB integration for vector storage
- Sentence transformer embeddings (all-MiniLM-L6-v2)
- Semantic search algorithm refinements
- Auto-sync functionality
- API key authentication system

### 0.6.0 (2025-12-10) - Alpha 2
- Initial AI model training on WordPress content
- Embedding generation pipeline
- Search relevance testing and tuning
- Categories and tags support

### 0.5.0 (2025-12-01) - Alpha 1
- First working prototype
- Basic semantic search functionality
- Manual sync of posts and pages
- WordPress search hook integration
- Internal testing phase

## License

GPL v2 or later - see [LICENSE](LICENSE) file for details

---

## Links

- [Queryra Homepage](https://queryra.com)
- [Sign Up Free](https://queryra.com/signup)
- [Documentation](https://queryra.com/docs)
- [WordPress Integration Guide](https://queryra.com/docs/wordpress-integration)
- [FAQ](https://queryra.com/faq)

---

**Made with ❤️ for the WordPress community**

[⭐ Star this repo](https://github.com/GronRafal/queryra-wordpress-plugin) if you find it useful!
