#!/bin/bash
#
# Release Script for Queryra WordPress Plugin
# Handles Git push, Git tag, SVN trunk update, and SVN tag creation
#

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Paths
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SVN_DIR="$PLUGIN_DIR/queryra-ai-search"
MAIN_FILE="$PLUGIN_DIR/queryra-ai-search.php"

# Get version from main plugin file
VERSION=$(grep -E "^\s*\*\s*Version:" "$MAIN_FILE" | sed 's/.*Version:\s*//' | tr -d ' \r')

if [ -z "$VERSION" ]; then
    echo -e "${RED}Error: Could not detect version from $MAIN_FILE${NC}"
    exit 1
fi

echo "=================================================="
echo -e "🚀 ${GREEN}Release Script - Queryra WordPress Plugin${NC}"
echo "=================================================="
echo ""
echo "Version: $VERSION"
echo "Git Tag: v$VERSION"
echo ""

# Step 1: Git status
echo "Step 1/6: Git status..."
cd "$PLUGIN_DIR"

if ! git diff --quiet || ! git diff --cached --quiet; then
    echo ""
    git status --short
    echo ""
    echo -e "${YELLOW}⚠️  You have uncommitted changes.${NC}"
    read -p "Commit all changes with message 'Release $VERSION'? (y/n): " COMMIT_CHANGES
    if [ "$COMMIT_CHANGES" = "y" ]; then
        git add -A
        git commit -m "Release $VERSION"
        echo -e "${GREEN}✅ Changes committed${NC}"
    else
        echo -e "${RED}Aborting. Please commit your changes first.${NC}"
        exit 1
    fi
else
    echo ""
    echo -e "${GREEN}✅ No changes to commit${NC}"
fi

# Step 2: Git push
echo ""
echo "Step 2/6: Git push..."
git push origin main
echo -e "${GREEN}✅ Pushed to remote${NC}"

# Step 3: Git tag
echo ""
echo "Step 3/6: Git tag v$VERSION..."
if git rev-parse "v$VERSION" >/dev/null 2>&1; then
    echo -e "${YELLOW}⚠️  Tag v$VERSION already exists${NC}"
    read -p "Delete and recreate? (y/n): " RECREATE_TAG
    if [ "$RECREATE_TAG" = "y" ]; then
        git tag -d "v$VERSION"
        git push origin --delete "v$VERSION" 2>/dev/null || true
        git tag "v$VERSION"
        git push origin "v$VERSION"
        echo -e "${GREEN}✅ Git tag v$VERSION recreated${NC}"
    else
        echo "Skipping tag creation."
    fi
else
    git tag "v$VERSION"
    git push origin "v$VERSION"
    echo -e "${GREEN}✅ Git tag v$VERSION created${NC}"
fi

# Step 4: SVN update trunk
echo ""
echo "Step 4/6: SVN update trunk..."

# Check if SVN directory exists
if [ ! -d "$SVN_DIR" ]; then
    echo -e "${YELLOW}SVN directory not found. Checking out...${NC}"
    svn checkout https://plugins.svn.wordpress.org/queryra-ai-search "$SVN_DIR"
fi

cd "$SVN_DIR"
svn update

# Copy plugin files to trunk (excluding dev files)
echo "Copying files to trunk..."
rsync -av --delete \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='.idea' \
    --exclude='scripts' \
    --exclude='docs' \
    --exclude='*.md' \
    --exclude='queryra-ai-search' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='.DS_Store' \
    --exclude='.claude' \
    "$PLUGIN_DIR/" "$SVN_DIR/trunk/"

# Restore readme.txt (WordPress.org needs it)
cp "$PLUGIN_DIR/readme.txt" "$SVN_DIR/trunk/readme.txt"

# Add new files, remove deleted files
svn add --force trunk/* 2>/dev/null || true
svn status trunk | grep '!' | awk '{print $2}' | xargs -I {} svn delete {} 2>/dev/null || true

echo ""
echo "Changes in trunk:"
svn status trunk

read -p "Commit trunk to SVN? (y/n): " COMMIT_TRUNK
if [ "$COMMIT_TRUNK" = "y" ]; then
    svn commit -m "Release $VERSION"
    echo -e "${GREEN}✅ Trunk committed${NC}"
else
    echo "Skipping trunk commit."
    exit 0
fi

# Step 5: SVN tag
echo ""
echo "Step 5/6: SVN tag $VERSION..."

if [ -d "$SVN_DIR/tags/$VERSION" ]; then
    echo -e "${YELLOW}⚠️  SVN tag $VERSION already exists${NC}"
    read -p "Delete and recreate? (y/n): " RECREATE_SVN_TAG
    if [ "$RECREATE_SVN_TAG" = "y" ]; then
        svn delete "tags/$VERSION"
        svn commit -m "Delete old tag $VERSION"
    else
        echo "Skipping SVN tag creation."
    fi
fi

if [ ! -d "$SVN_DIR/tags/$VERSION" ]; then
    svn copy trunk "tags/$VERSION"
    svn commit -m "Tag $VERSION"
    echo -e "${GREEN}✅ SVN tag $VERSION created${NC}"
fi

# Step 6: Assets
echo ""
echo "Step 6/6: Assets (optional)..."
read -p "Upload assets (icons/banners)? (y/n): " UPLOAD_ASSETS
if [ "$UPLOAD_ASSETS" = "y" ]; then
    if [ -d "$PLUGIN_DIR/assets" ]; then
        echo "Copying assets..."
        cp -r "$PLUGIN_DIR/assets"/* "$SVN_DIR/assets/" 2>/dev/null || true
        svn add --force assets/* 2>/dev/null || true
        svn commit -m "Update assets"
        echo -e "${GREEN}✅ Assets uploaded${NC}"
    else
        echo -e "${YELLOW}No assets directory found.${NC}"
    fi
fi

echo ""
echo "=================================================="
echo -e "🎉 ${GREEN}Release $VERSION Complete!${NC}"
echo "=================================================="
echo ""
echo "Git:  https://github.com/GronRafal/queryra-wordpress-plugin/releases/tag/v$VERSION"
echo "SVN:  https://plugins.svn.wordpress.org/queryra-ai-search/"
echo "WP:   https://wordpress.org/plugins/queryra-ai-search/"
echo ""
