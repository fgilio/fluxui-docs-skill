# Flux UI CLI

> **Note**: This tool caches documentation from [fluxui.dev](https://fluxui.dev). Permission to redistribute this content is pending approval from the Flux UI team.

Self-contained CLI for accessing Flux UI documentation offline. No PHP required.

## Usage

```bash
# List all documentation
./fluxui-docs docs

# Search for components
./fluxui-docs search button

# Show component documentation
./fluxui-docs show modal
```

## Installation

The `fluxui-docs` binary is self-contained. You can:

1. Run directly: `~/.claude/skills/fluxui-docs/fluxui-docs docs`
2. Symlink to PATH: `ln -sf ~/.claude/skills/fluxui-docs/fluxui-docs ~/.local/bin/fluxui-docs`

## Development

See [src/README.md](src/README.md) for building from source and updating documentation.
