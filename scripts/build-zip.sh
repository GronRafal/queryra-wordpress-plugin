#!/bin/bash

# Build WordPress.org submission ZIP
# Excludes development files, keeps only production code

VERSION="1.0.5"
PLUGIN_SLUG="queryra-ai-search"
OUTPUT_FILE="${PLUGIN_SLUG}.zip"

# Remember where we were called from (this is where ZIP will be created)
ORIGINAL_DIR=$(pwd)

# Get script directory and plugin root
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PLUGIN_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

echo "Building WordPress.org submission ZIP for version ${VERSION}..."
echo "Plugin root: $PLUGIN_ROOT"
echo "Output directory: $ORIGINAL_DIR"

# Change to plugin root directory
cd "$PLUGIN_ROOT"

# Remove old ZIP if exists in original directory
rm -f "$ORIGINAL_DIR/$OUTPUT_FILE"

# Create ZIP in original directory, excluding development files
zip -r "$ORIGINAL_DIR/$OUTPUT_FILE" . \
  -x ".git/*" \
  -x ".gitignore" \
  -x ".idea/*" \
  -x "README.md" \
  -x "scripts/*" \
  -x "*.zip"

echo ""
echo "✅ Created: $ORIGINAL_DIR/$OUTPUT_FILE"
echo ""
echo "Contents:"
unzip -l "$ORIGINAL_DIR/$OUTPUT_FILE"
