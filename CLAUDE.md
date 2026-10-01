# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

WordPress plugin ("FACT Ultimate Member - CleverReach Integration", PHP >= 8.1, built against Ultimate Member 2.9.1). It adds a newsletter opt-in to the Ultimate Member (UM) register form and subscribes the new user to a CleverReach group with double opt-in.

This is the CleverReach version, kept on `main` for reference. The rapidmail version lives on the `feat/rapidmail-integration` branch.

The repo root is the plugin directory (the `.gitignore` is the WordPress template), so it is meant to live under `wp-content/plugins/`.

## Commands

There is no build, lint, or test tooling (no composer, phpunit, or phpcs). To exercise changes, install the plugin in a WordPress site with Ultimate Member active and submit the UM register form with the `register_for_nl` field set.

## Architecture

Two files make up the plugin:

- `UmCrIntegration.php`: plugin entry point. Class `UmCrIntegration` hooks `um_submit_form_errors_hook` and, when `register_for_nl` is in the submitted data, runs this flow:
  1. `createNewUser()` builds the CleverReach receiver (`activated => 0`; the double opt-in activates it).
  2. Fetch an OAuth token and set bearer auth.
  3. POST the receiver to `/groups.json/{groupId}/receivers`.
  4. If that succeeded, POST `/forms.json/{formId}/send/activate` to send the DOI email.

  The CleverReach group ID and form ID are hardcoded properties on the class.
- `rest_client.php`: class `rest` (lowercase), a curl wrapper for the CleverReach REST v3 API. It covers client-credentials token fetch (`getAccessToken()`), auth modes (`setAuthMode`), and `get`/`post`/`put`/`delete`.

`package.xml` is an unrelated Xdebug PECL manifest, not part of the plugin. It is very large; don't read it in full.

## Gotchas

- `rest::returnResult()` throws `\Exception("<http_code>;<message>")` on non-2xx responses, except when the body contains "duplicate": it then returns `false`. An already-subscribed email therefore skips the DOI step without an error.
- The CleverReach OAuth client id and secret are hardcoded and committed in `rest_client.php`. Don't copy them elsewhere.
- A new access token is requested on every registration (no caching).
- `UmCrIntegration.php` includes `rest_client.php` before the `ABSPATH` and `class_exists('UM')` guards.
- `um_submit_form_errors_hook` fires before registration succeeds, so the receiver is also created when the form has validation errors.
