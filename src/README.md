# Flux UI CLI - Development

## Setup

```bash
cd ~/.claude/skills/fluxui-docs/src
composer install
php fluxui-docs --help
```

## Development Commands

These commands are available in development (not in production binary):

```bash
php fluxui-docs update              # Scrape latest docs from fluxui.dev
php fluxui-docs build               # Build production binary
php fluxui-docs test                # Run tests
```

## Updating Documentation

When Flux UI releases new components:

```bash
php fluxui-docs update              # Scrape latest docs
cd ..
git add data/
git commit -m "Update Flux UI docs"
git push
```

### Update Options

```bash
php fluxui-docs update                        # Update all
php fluxui-docs update --item=button          # Update single component
php fluxui-docs update --category=component   # Category for single item
php fluxui-docs update --delay=1000           # Custom delay (ms)
php fluxui-docs update --dry-run              # Preview only
```

## Building

### First-time setup (builds PHP + micro.sfx)

```bash
phpcli-spc-setup --doctor
phpcli-spc-build --extensions "ctype,fileinfo,filter,iconv,mbstring,mbregex,phar,tokenizer,zlib"
```

> **Note:** Optimized extension set (9 vs 18). Removed: bcmath, curl, dom, openssl, pcntl, pdo, posix, session, simplexml, sockets, sodium, xml. Scraping deps (guzzle, dom-crawler) moved to require-dev and stripped during build.

### Build production binary

```bash
php fluxui-docs build               # Builds + copies to ../fluxui-docs
php fluxui-docs build --no-install  # Only builds to builds/fluxui-docs
```

## Testing

```bash
php fluxui-docs test
# or
./vendor/bin/pest
```
