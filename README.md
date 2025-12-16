# Flux UI CLI

> **Note**: This tool caches documentation from [fluxui.dev](https://fluxui.dev). Permission to redistribute this content is pending approval from the Flux UI team.

Self-contained CLI for accessing Flux UI documentation offline. No PHP required.

## Usage

```bash
# List all documentation
./flux docs

# Search for components
./flux search button

# Show component documentation
./flux show modal
```

## Installation

The `flux` binary is self-contained. You can:

1. Run directly: `~/.claude/skills/flux-ui/flux docs`
2. Symlink to PATH: `ln -sf ~/.claude/skills/flux-ui/flux ~/.local/bin/flux`

## Development

See [src/README.md](src/README.md) for building from source and updating documentation.
