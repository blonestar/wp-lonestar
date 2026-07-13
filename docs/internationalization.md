# Internationalization

Lonestar parent-framework source strings are English and use the single `lonestar` text domain. This framework does not include a multilingual content plugin, language switcher, or translated content.

## Parent workflow

Install the WordPress i18n-command package for WP-CLI, then regenerate the catalog from the theme root:

```bash
wp package install wp-cli/i18n-command
npm run i18n:pot
```

The command writes `languages/lonestar.pot` and excludes generated output and tests. Regenerate it whenever PHP, JavaScript, `block.json`, or parent module metadata strings change. A second run must produce no diff.

For example, a bundled Serbian theme translation uses a working PO catalog and compiles the runtime theme catalog as `languages/sr_RS.mo`; WordPress theme files in the custom theme directory use locale-only MO filenames. Generate JavaScript catalogs from the domain/locale PO catalog without removing its entries:

```bash
wp i18n make-json languages --no-purge
```

This produces WordPress-named JSON catalogs such as `languages/lonestar-sr_RS-<hash>.json`. POT is the source template; PO is a concrete locale catalog; MO is its compiled PHP runtime catalog; JSON catalogs serve JavaScript translations.

## Child themes and modules

Child themes own their strings and may use their own domain and `languages/` directory. Parent module metadata defaults to `lonestar`; child module metadata remains literal unless its `module.json` explicitly provides a `textdomain`. Parent module metadata strings are mirrored as literal gettext calls in `inc/core/module-metadata-i18n.php` so the POT remains extractable.
