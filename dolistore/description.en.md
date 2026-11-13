# Micro-entreprise (URSSAF declarations and contributions)

## Short description

Turnover to declare to the URSSAF each month or quarter, estimated
contributions, ceilings and statistics for French micro-entreprises
(auto-entrepreneurs), computed from the customer payments already recorded in
Dolibarr.

## Description

The module adds a **Micro-entreprise** section to the Accounting menu.

**Declarations**: one year at a time, one row per quarter or month with the
URSSAF deadline and its status. For the selected period: the amounts to type on
autoentrepreneur.urssaf.fr, rounded to the euro, one line per activity (sales of
goods, BIC services, BNC services); the detail of the estimated contributions;
the payments received with links to the invoices. Also the yearly turnover for
the income tax return (2042-C-PRO), the micro-entreprise ceilings and VAT
franchise thresholds with an alert, and a CSV export.

**Setup**: contribution rates in one table, years in columns and taxes in rows.
A new year starts from the previous one or from the official URSSAF catalog
(2025 and 2026: social contributions, vocational training, chamber fees, income
tax in full discharge, ceilings), for traders, craftsmen and liberal
professions.

**Statistics**: yearly turnover and contributions, monthly and cumulated
turnover year after year, change from the previous year, effective rate.

**Also**: a home page widget with the period to declare, and agenda reminders
before each deadline (scheduled job).

How the turnover is computed: cash basis (each payment counts in the month it
is received), excluding VAT; partial payments and deposits settle the goods
first, then the services; credit notes and refunds lower the turnover.

The contributions are estimates: check that the rates match your situation.

## Requirements

Dolibarr 23 or later, PHP 7.2 to 8.5, Invoices and Accounting (simplified)
modules.

## Upgrading from AutoMicroEntreprise 7

Deploy the new version, disable then enable the module: the rates are kept and
converted.

## Support

Questions and bug reports, in English or French:
<https://github.com/Aohzan/dolibarr-micro-entreprise-compta/issues>
