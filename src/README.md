# Flux UI CLI - Development

## Setup

```bash
cd ~/.claude/skills/flux-ui/src
composer install
php flux --help
```

## Development Commands

These commands are available in development (not in production binary):

```bash
php flux update              # Scrape latest docs from fluxui.dev
php flux build               # Build production binary
php flux test                # Run tests
```

## Updating Documentation

When Flux UI releases new components:

```bash
php flux update              # Scrape latest docs
cd ..
git add data/
git commit -m "Update Flux UI docs"
git push
```

### Update Options

```bash
php flux update                        # Update all
php flux update --item=button          # Update single component
php flux update --category=component   # Category for single item
php flux update --delay=1000           # Custom delay (ms)
php flux update --dry-run              # Preview only
```

## Building

### First-time setup (builds PHP + micro.sfx)

```bash
phpcli-spc-setup --doctor
phpcli-spc-build --extensions "bcmath,ctype,curl,dom,fileinfo,filter,iconv,mbstring,mbregex,openssl,pdo,phar,pcntl,posix,session,simplexml,sockets,sodium,tokenizer,xml,zlib"
```

### Build production binary

```bash
php flux build               # Builds + copies to ../flux
php flux build --no-install  # Only builds to builds/flux
```

## Testing

```bash
php flux test
# or
./vendor/bin/pest
```
