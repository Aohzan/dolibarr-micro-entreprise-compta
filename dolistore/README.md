# Dolistore listing

Texts and checklist to publish the module on [Dolistore](https://www.dolistore.com).
Dolistore suspended the validation of new modules from 10 November to the end of
2026: submit in January 2027 at the earliest.

## Before submitting

1. Get a module number block of its own. The module still uses 451050, from
   the block of the original editor (451000-451999, depordi.fr), and Dolistore
   wants a number reserved by the publisher. The wiki page *List of modules
   id* is protected: ask on the Dolibarr forum (Developing for Dolibarr) for a
   block of 10 in 100000-499999. Then change `numero` and the rights ids in
   `core/modules/modAutoMicroEnt.class.php` and the `Module…`/`Permission…`
   lang keys, in a major version (`feat!:`): users re-enable the module and
   grant the rights again.
2. A release exists on GitHub: download `module_automicroent-<version>.zip`.
3. Test the zip on a clean Dolibarr: Home > Setup > Modules > Deploy external
   module, enable the module, open the three pages. Dolistore refuses modules
   that cannot be deployed this way.
4. Decide the price: free, or a support price. A paid module needs the
   support link below, and the seller invoices the Dolibarr foundation (80 %
   of the sales are paid back, from 50 EUR).

## Product sheet

| Field | Value |
| --- | --- |
| Name | Micro-entreprise (URSSAF declarations and contributions) |
| Module version | The released version, e.g. 8.0.0 |
| Dolibarr versions | 23 to 24 (update with each tested major) |
| Licence | GPL v3 |
| Languages | English, French |
| Support | <https://github.com/Aohzan/dolibarr-micro-entreprise-compta/issues> (English or French) |
| Images | `docs/screenshots/*.png` |

Descriptions: [description.en.md](description.en.md) (required) and
[description.fr.md](description.fr.md). The Dolistore rules forbid mentioning
or linking to other platforms that sell the module.

The original module still has a discontinued listing (Auto Micro Entreprise V2,
number 512) with the same table and directory. If the submission is refused as
a duplicate (rule S4 of the seller terms), explain to the Dolistore team that
this is the maintained continuation of that module, ported to Dolibarr 23 and
24, with the original copyright notices kept.
