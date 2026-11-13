# Changelog

## [8.0.0](https://github.com/Aohzan/dolibarr-micro-entreprise-compta/compare/v7.1.0...v8.0.0) (2026-11-13)

### ⚠ BREAKING CHANGES

* disable then enable the module after the update, to
migrate the rates to the new "rate" column. The module needs Dolibarr 23
or later. The turnover now excludes VAT, and each partial payment counts
in the month it is received instead of the month of the first payment.

### Features

* add ceilings, a home widget, deadline reminders and two activities ([acbb960](https://github.com/Aohzan/dolibarr-micro-entreprise-compta/commit/acbb960c6fe76d743b00c1d93489f9784519c1d1))
* merge a tax renamed like another one ([ec8db45](https://github.com/Aohzan/dolibarr-micro-entreprise-compta/commit/ec8db4519e3611033579d5833b8aef4b14bbf891))
* rewrite the module around setup, declarations and statistics ([bc2c2ee](https://github.com/Aohzan/dolibarr-micro-entreprise-compta/commit/bc2c2ee7c2beaabb582d62592597ec04eb378310))

### Bug Fixes

* find the sale of a deposit through the invoice that deducts it ([b793e17](https://github.com/Aohzan/dolibarr-micro-entreprise-compta/commit/b793e17961735174b605a4831c0d4b852537f161))
* keep the statistics charts inside their column ([5b44d1e](https://github.com/Aohzan/dolibarr-micro-entreprise-compta/commit/5b44d1eea7394b890c8d9d90370b597ca3f7ad89))
* settle a deposit like a partial payment of its sale ([de5eea7](https://github.com/Aohzan/dolibarr-micro-entreprise-compta/commit/de5eea7a387d315b4b2cf5e16b3bcd53dd4fa0f9))
* settle deposits, discounts and refunds as found in review ([1490a4b](https://github.com/Aohzan/dolibarr-micro-entreprise-compta/commit/1490a4b63f15620dab02268770f6acf08b2c126f))

## 7.1.0 (2026-11-12)

- Fork maintained at <https://github.com/Aohzan/dolibarr-micro-entreprise-compta>.
- Dolibarr 24 compatibility: `GETPOST()` with an empty check type now always
  returns an empty string, which broke the year selection of the setup page
  and the rate edit form.

## 7.0.1 (2018-09-03)

- Maintenance update.
- Bring back module menu pages from «billing» to «accountancy» section in the
  top menu.
- The module now depends on «modComptabilite» internal module.

## 7.0.0 (2018-08-28)

- Maintenance update: drop support for Dolibarr < 7.
- Move module from «accountancy» to «billing» top menu.
- Update description file with newest one coming from Dolibarr 7, and update
  code accordingly.
- Minor fixes.

Older versions (2013-2018) are listed in `docs/ChangeLog`.
