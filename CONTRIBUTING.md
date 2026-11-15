# Contributing to PeeStream Multi-Hoster

Thank you for helping improve the PeeStream ecosystem.

## Before opening an issue

- Search [existing issues](https://github.com/PeeStream/peestream-multihoster/issues) for duplicates.
- State the plugin version, WordPress, PHP and database versions, and whether the healing queue was enabled.
- Share concise reproduction steps and sanitized logs. Never include host credentials, API keys, nonces, `LOGGED_IN_KEY`/`NONCE_KEY` values or personal data.

## Pull requests

1. Open an issue describing the intended change before substantial work.
2. Keep the plugin free of automatic uploads, local temporary-video staging and hidden network calls; hosters stay manually entered.
3. Never add webhook receivers here: signed AutoPublish V2 delivery belongs to `peestream-wp-dooplay` and `dle-peestream-addon`.
4. Add or update tests in `tests/` and run every `php tests/test-*.php` file. Identify mocked behaviour explicitly and never label a simulation as a live end-to-end test.
5. Preserve credential encryption (libsodium secretbox, AES-256-GCM fallback) and the size/redirect limits in health checks and the adapter.
6. Keep capability and nonce checks on every admin action and queue trigger.
7. Public documentation, issue templates and pull request descriptions are in professional English. Do not commit credentials or production configuration.
