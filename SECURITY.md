# Security Policy

Security matters for a WordPress plugin that stores host credentials, probes the source URLs you enter, and can call the PeeStream API on your behalf.

## Supported versions

| Version | Supported |
| ------- | --------- |
| 0.3.x   | yes       |

Requires WordPress 6.0+ and PHP 7.4-8.4.

This plugin is **not** an AutoPublish V2 webhook receiver. It exposes no public endpoint, does not verify `X-PeeStream-Signature`, and does not use `?secret=` URLs. Its outbound traffic is limited to HEAD (range-limited GET fallback) health checks of the source URLs you configure and the optional PeeStream adapter documented in the README.

## Reporting a vulnerability

Please **do not post API keys, host credentials, nonces, `LOGGED_IN_KEY`/`NONCE_KEY` values, full database dumps, or exploit details in public Issues**.

If GitHub offers **Report a vulnerability** in this repository's **Security** tab, use that private channel. Otherwise use the [official PeeStream website](https://peestream.com) to request a private security contact method before sharing sensitive details.

Include the plugin version, WordPress/PHP/database versions, the settings relevant to encryption or the healing queue, the impact, and minimal reproduction steps with sanitized URLs and logs.

## Safe configuration

- Credentials are encrypted with libsodium secretbox when available, otherwise AES-256-GCM. There is no plaintext fallback. `LOGGED_IN_KEY` and `NONCE_KEY` must be unique and backed up: changing either makes previously stored credentials undecryptable.
- Keep the healing queue disabled (leave `psmh_healing_enabled` unset) until credentials and target behaviour are validated on staging. It can replace a dead source URL automatically when another source for the same post is alive.
- Health checks and the adapter disable redirect following and cap response size. Do not bypass either limit in custom code.
- Do not commit credentials, staging configuration or production database dumps to this repository.
- Back up the database before rotating encryption keys or changing the plugin schema.

## Verifying a release

Each release asset is listed in `SHA256SUMS`:

```bash
sha256sum -c SHA256SUMS
```
