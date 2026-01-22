# Queryra Search for WordPress

AI-powered semantic search for your WordPress content. Automatically sends posts, pages, and custom post types to Queryra.

## Features

- **Auto-send** - Automatically send posts to Queryra when published or updated
- **Post Types** - Send posts, pages, and custom post types
- **Manual Send** - Bulk send all existing posts with one click
- **Stats Dashboard** - View send status and statistics
- **Fast & Reliable** - Built with WordPress best practices

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

### How It Works

The plugin works in 2 steps:

**Step 1: Send (WordPress Plugin)**
- Posts are sent to Queryra (automatic or manual)
- Records are stored but not yet searchable

**Step 2: Sync (Queryra Dashboard)**
- Go to [Queryra Dashboard](https://queryra.com/dashboard/sync)
- Click "Sync Records" to generate AI embeddings
- Records become searchable

### Automatic Send

With auto-send enabled, posts are automatically sent to Queryra when:
- A new post is published
- An existing post is updated
- A post is deleted (removed from Queryra)

**Note:** After sending, sync in Queryra dashboard to make them searchable.

### Manual Send

To send all existing posts:
1. Go to **Queryra** settings in WordPress
2. Click **Send All Posts**
3. Go to [Queryra Dashboard](https://queryra.com/dashboard/sync) and click "Sync Records"

### Tips

- **Single post:** Edit and click "Update" to send it individually
- **Manage records:** Delete or edit in [Queryra Dashboard](https://queryra.com/dashboard/records)
- **Deleted posts:** Automatically removed from Queryra

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
