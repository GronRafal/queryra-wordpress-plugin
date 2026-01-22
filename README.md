# Queryra Search for WordPress

AI-powered semantic search for your WordPress content. Automatically syncs posts, pages, and custom post types to Queryra.

## Features

- 🔄 **Auto-sync** - Automatically sync posts when published or updated
- 📝 **Post Types** - Sync posts, pages, and custom post types
- 🎯 **Manual Sync** - Bulk sync all existing posts with one click
- 📊 **Stats Dashboard** - View sync status and statistics
- ⚡ **Fast & Reliable** - Built with WordPress best practices

## Installation

### From WordPress Dashboard

1. Download the plugin ZIP file
2. Go to **Plugins → Add New**
3. Click **Upload Plugin**
4. Choose the ZIP file and click **Install Now**
5. Activate the plugin

### Manual Installation

1. Upload the `queryra-search` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress

## Configuration

1. Go to **Queryra** in WordPress admin menu
2. Enter your **API Key** from [Queryra Dashboard](https://queryra.com/dashboard)
3. Click **Test Connection** to verify
4. Select which **Post Types** to sync
5. Enable **Auto Sync** (recommended)
6. Click **Save Changes**

## Getting Your API Key

1. Sign up at [queryra.com](https://queryra.com)
2. Go to your [Dashboard](https://queryra.com/dashboard)
3. Copy your API key
4. Paste it in WordPress plugin settings

## Usage

### Automatic Sync

With auto-sync enabled, posts are automatically synced to Queryra when:
- A new post is published
- An existing post is updated
- A post is deleted (removed from Queryra)

### Manual Sync

To sync all existing posts:
1. Go to **Queryra** settings
2. Click **Sync All Posts**
3. Wait for confirmation

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Active Queryra account

## Support

Need help? Contact us:
- Email: contact@queryra.com
- Documentation: [queryra.com/docs](https://queryra.com/docs)
- FAQ: [queryra.com/faq](https://queryra.com/faq)

## License

GPL v2 or later

## Changelog

### 1.0.0
- Initial release
- Auto-sync posts on save/update
- Manual bulk sync
- Settings page
- Stats dashboard
