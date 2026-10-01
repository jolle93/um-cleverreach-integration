# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

WordPress plugin ("FACT Ultimate Member - rapidmail Integration", PHP >= 8.1, built against Ultimate Member 2.9.1). It adds a newsletter opt-in to the Ultimate Member (UM) register form and subscribes the new user to a rapidmail recipient list with double opt-in. It replaced a CleverReach integration (repo/URLs still say `um-cleverreach-integration`).

The repo root is the plugin directory (the `.gitignore` is the WordPress template), so it is meant to live under `wp-content/plugins/`.

## Commands

There is no build, lint, or test tooling (no composer, phpunit, or phpcs). Syntax check with `php -l <file>`. To exercise changes, install the plugin in a WordPress site with Ultimate Member active, fill in Settings -> UM rapidmail, and submit the UM register form with the `register_for_nl` field set. rapidmail demo accounts never send activation mails.

## Architecture

- `UmCrIntegration.php`: plugin entry point. Class `UmCrIntegration` hooks `um_submit_form_errors_hook`; when `register_for_nl` is submitted it builds a recipient (`recipientlist_id`, `email`, `firstname`, `lastname`) and calls `RapidmailClient::createRecipient()`. It also registers the settings page (Settings API) with API username, API password and recipient list ID, stored in `wp_options` (`umcr_rapidmail_*`). The password field is never rendered; submitting it empty keeps the stored value.
- `RapidmailClient.php`: thin wrapper around the rapidmail REST API v3 using `wp_remote_request` and HTTP Basic auth. `POST /recipients?send_activationmail=yes` creates the recipient and triggers the double-opt-in mail in one call. Any 2xx is success, 409 is reported as `DUPLICATE`, everything else throws `RuntimeException`.

`package.xml` is an unrelated Xdebug PECL manifest, not part of the plugin. It is very large; don't read it in full.

## Gotchas

- API reference: OpenAPI specs at `https://developer.rapidmail.wiki/specs/public/<Name>_v1.json` (listed on `documentation.html`; the page itself is JS-rendered, fetch the JSON). `send_activationmail=yes` only has an effect for recipients with `status: new`, so the payload sets it explicitly. 409 means the email already exists in the list; a deleted recipient with the same email is re-activated on create.
- `checkForNLregister` swallows and logs all errors (`error_log`) so a newsletter failure never breaks the UM registration.
- `um_submit_form_errors_hook` fires before registration succeeds, so the recipient is also created when the form has validation errors. `um_registration_complete` would be the better hook.
- The old CleverReach OAuth secret remains in git history; revoke it on the CleverReach side.
