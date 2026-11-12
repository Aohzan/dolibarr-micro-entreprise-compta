# AutoMicroEntreprise Module FOR <a href="https://www.dolibarr.org">DOLIBARR ERP CRM</a>

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

![Screenshot 1](img/s01.png "screenshot 1"){imgmd}
![Screenshot 2](img/s02.png "screenshot 2"){imgmd}
![Screenshot 3](img/s03.png "screenshot 3"){imgmd}
![Screenshot 4](img/s04.png "screenshot 4"){imgmd}
![Screenshot 5](img/s05.png "screenshot 5"){imgmd}

Other modules are available on <a href="https://www.dolistore.com" target="_new">Dolistore.com</a>.



## Install
----------

### From the ZIP file and GUI interface

- If you get the module in a zip file (like when downloading it from the market place [Dolistore](https://www.dolistore.com)), go into
menu ```Home - Setup - Modules - Deploy external module``` and upload the zip file.


Note: If this screen tell you there is no custom directory, check your setup is correct: 

- In your Dolibarr installation directory, edit the ```htdocs/conf/conf.php``` file and check that following lines are not commented:

    ```php
    //$dolibarr_main_url_root_alt ...
    //$dolibarr_main_document_root_alt ...
    ```

- Uncomment them if necessary (delete the leading ```//```) and assign a sensible value according to your Dolibarr installation

    For example :

    - UNIX:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = '/var/www/Dolibarr/htdocs/custom';
        ```

    - Windows:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = 'C:/My Web Sites/Dolibarr/htdocs/custom';
        ```
        
Licenses
--------

### Main code

![GPLv3 logo](img/gplv3-88x31.png)

GPLv3 or (at your option) any later version.

See [COPYING](https://www.gnu.org/licenses/gpl-3.0.en.html "GPLv3 license") for more information.

#### Documentation

See also others documentation files into docs/ subdirectory of the module.
