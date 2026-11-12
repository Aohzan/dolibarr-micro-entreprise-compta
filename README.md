# AutoMicroEntreprise module for [Dolibarr ERP CRM](https://www.dolibarr.org)

Turnover reports for French micro-entreprises, by month, quarter and year, for products and services, with an estimate of the charges due from the taxes and rates set for each year.

## About this fork

This repository is a maintained fork of AutoMicroEntreprise, written by
Fabrice Delliaux and later kept by Sébastien Serre
([framagit.org/sebastienserre/automicroent](https://framagit.org/sebastienserre/automicroent)),
both now unmaintained. Its full history is kept here. Changes are listed in
[ChangeLog.md](ChangeLog.md).

Tested with Dolibarr 24.0.1 and PHP 8.4.

Only the repository has a new name: the module itself is still
`automicroent` (directory, rights, `llx_ame_rates` table, translations), so
an existing installation upgrades in place and keeps its rates. Install it in
a directory named `automicroent`:

```sh
git clone https://github.com/Aohzan/dolibarr-micro-entreprise-compta.git htdocs/custom/automicroent
```

Bugs and pull requests are welcome in the
[issues](https://github.com/Aohzan/dolibarr-micro-entreprise-compta/issues).

## Features

![Screenshot 1](img/s01.png "screenshot 1")
![Screenshot 2](img/s02.png "screenshot 2")
![Screenshot 3](img/s03.png "screenshot 3")
![Screenshot 4](img/s04.png "screenshot 4")
![Screenshot 5](img/s05.png "screenshot 5")

## Install

### From the release zip

Download `module_automicroent-<version>.zip` from the
[releases](https://github.com/Aohzan/dolibarr-micro-entreprise-compta/releases),
then in Dolibarr go to `Home - Setup - Modules - Deploy external module` and
upload the zip file.

If this screen says there is no custom directory, edit `htdocs/conf/conf.php`
in your Dolibarr installation and set (uncomment) these lines to match your
installation, for example on UNIX:

```php
$dolibarr_main_url_root_alt = '/custom';
$dolibarr_main_document_root_alt = '/var/www/Dolibarr/htdocs/custom';
```

or on Windows:

```php
$dolibarr_main_url_root_alt = '/custom';
$dolibarr_main_document_root_alt = 'C:/My Web Sites/Dolibarr/htdocs/custom';
```

## Development

Tools are managed by [mise](https://mise.jdx.dev): `mise install`, then
`mise run install` (Composer and npm dependencies, git hooks).

| Command | What it does |
| --- | --- |
| `mise run lint` | All linters (prek: PHPCS, PHPStan, codespell, yamllint, markdownlint, zizmor) |
| `mise run test` | PHPUnit |
| `mise run dev` | Seeded Dolibarr on <http://localhost:8080> (admin / admin), `DOLIBARR=previous` for the previous major |
| `mise run e2e` | Playwright end-to-end tests against `mise run dev` |
| `mise run down` | Stop Dolibarr and drop its data |

With Podman, copy `mise.local.toml.example` to `mise.local.toml`.

Commits follow [Conventional Commits](https://www.conventionalcommits.org):
merging to `master` releases a new version with semantic-release.

## Licenses

### Main code

![GPLv3 logo](img/gplv3-88x31.png)

GPLv3 or (at your option) any later version.

See [COPYING](https://www.gnu.org/licenses/gpl-3.0.en.html "GPLv3 license") for more information.

### Documentation

See also others documentation files into docs/ subdirectory of the module.
