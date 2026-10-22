# Contributing

Thank you for your interest in improving the Mollie module for Dolibarr. Bug reports,
feature requests, translations and pull requests are welcome. The module is maintained
by [KleijnICT B.V.](https://kleijnict.nl) on a best-effort basis: we read everything, but
we cannot promise response times.

## Reporting bugs and requesting features

- Use the [issue templates](https://github.com/KleijnICT/dolibarr-mollie-plugin/issues/new/choose).
- Include your Dolibarr version, PHP version, module version and whether the module
  ran in test or live mode.
- **Never paste API keys**, even partially. Mask them (`test_****abcd`) in logs and
  screenshots.
- Security problems go to support@kleijnict.nl, not to the public tracker.
  See [SECURITY.md](SECURITY.md).

## Development environment

The repository ships a Docker Compose stack with MariaDB and Dolibarr 23:

```sh
cd dev
docker compose up -d        # Dolibarr 23 at http://localhost:8080 (admin/admin)
```

The module directory `mollie/` is bind-mounted into the container's custom module
directory, so every change is visible immediately. Enable the module under
Home › Setup › Modules/Applications and configure a Mollie **test** key.

Mollie cannot reach `localhost`, so the module omits the webhook URL on local
instances. Fire the webhook by hand after a test payment:

```sh
curl -d "id=tr_XXXXXXXXXX" http://localhost:8080/custom/mollie/public/webhook.php
```

CLI scripts run as root inside the container; if you create documents from the
shell, `chown` them back to `www-data` afterwards (or run `docker exec -u www-data`).

## Coding standard

- Follow the [Dolibarr coding standard](https://wiki.dolibarr.org/index.php/Language_and_development_rules):
  tabs for indentation, Dolibarr helper functions (`GETPOST`, `dol_syslog`,
  `getDolGlobalString`, ...) instead of raw PHP equivalents, output escaped with
  `dol_escape_htmltag`.
- Keep the module free of external PHP dependencies. The Mollie API is called through
  `class/mollieclient.class.php`; extend that client rather than adding an SDK.
- Every string shown to users goes through the language files in `langs/`. Add new
  keys to **both** `en_US` and `nl_NL` (CI checks that the key sets match).
- Code comments, commit messages and documentation are written in English.
- Keep the two security principles intact: the webhook acts only on what it fetches
  from the Mollie API (never on the request body), and a Mollie payment is recorded
  at most once (deduplication on `ext_payment_id`).

## Pull requests

1. Fork the repository and create a branch from `main`.
2. Make focused changes; one topic per pull request.
3. Run the checks locally before pushing:

   ```sh
   find mollie -name '*.php' -exec php -l {} \;
   ./dev/check-langs.sh
   ./build.sh
   ```

4. Add an entry under an "Unreleased" heading in `mollie/ChangeLog.md`.
5. Describe what you changed and how you tested it (test mode payment, webhook call).

By submitting a pull request you agree that your contribution is licensed under the
GNU General Public License v3.0 or later, the same license as the project.

## Translations

Add a directory `mollie/langs/<ll_CC>/` with a `mollie.lang` file, using `en_US` as the
source. Keep the keys identical and translate the values only.

## Release process (maintainers)

1. Update `$this->version` in `mollie/core/modules/modMollie.class.php`.
2. Move the "Unreleased" entries in `mollie/ChangeLog.md` under a new
   `## x.y.z (YYYY-MM-DD)` heading.
3. Commit, then tag: `git tag -a vx.y.z -m "Mollie module x.y.z"` and push the tag.
4. The release workflow verifies that the tag matches the module version, runs the
   syntax checks, builds `module_mollie-x.y.z.zip` and publishes a GitHub release
   with the changelog section as release notes and the zip as asset.
