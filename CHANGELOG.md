# Changelog

All notable changes to `vite-with-wordpress` will be documented in this file.

## 1.0.1 - 2025-05-23

- Fix for module replacement for scripts
- Update README
- Update composer info

## 1.0.0 - 2025-05-18

- Native WordPress support for Vite assets
- Automatic dev server detection with HMR support
- Manifest-based asset URL resolution for production
- Easy static facade: Vite::asset() to get asset URLs
- Injects type="module" on scripts for ES modules support
- Supports extracting and enqueuing CSS linked from JS entry points
