# Changelog

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

For previous versions, see `docs/ChangeLog`.
