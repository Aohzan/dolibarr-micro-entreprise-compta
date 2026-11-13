# Micro-entreprise (déclarations URSSAF et cotisations)

## Description courte

Chiffre d'affaires à déclarer à l'URSSAF chaque mois ou trimestre, cotisations
estimées, plafonds et statistiques pour les micro-entreprises
(auto-entrepreneurs), calculés à partir des paiements clients déjà saisis dans
Dolibarr.

## Description

Le module ajoute une section **Micro-entreprise** au menu Comptabilité.

**Déclarations** : une année à la fois, une ligne par trimestre ou par mois avec
l'échéance URSSAF et son état. Pour la période choisie : les montants à saisir
sur autoentrepreneur.urssaf.fr, arrondis à l'euro, une ligne par activité
(ventes de marchandises, prestations BIC, prestations BNC) ; le détail des
cotisations estimées ; les encaissements avec le lien vers chaque facture. Aussi
le chiffre d'affaires de l'année pour la déclaration de revenus (2042-C-PRO), les
plafonds micro-entreprise et les seuils de franchise de TVA avec une alerte, et
un export CSV.

**Configuration** : les taux de cotisations dans un seul tableau, les années en
colonnes et les taxes en lignes. Une nouvelle année part de la précédente ou du
catalogue officiel URSSAF (2025 et 2026 : cotisations sociales, formation
professionnelle, taxes de chambre consulaire, versement libératoire, plafonds),
pour les commerçants, les artisans et les professions libérales.

**Statistiques** : chiffre d'affaires et cotisations par année, chiffre
d'affaires mensuel et cumulé année après année, évolution, taux effectif.

**Et aussi** : un widget d'accueil avec la période à déclarer, et des rappels
dans l'agenda avant chaque échéance (travail planifié).

Calcul du chiffre d'affaires : à l'encaissement (chaque paiement compte le mois
où il est reçu), hors TVA ; les paiements partiels et les acomptes règlent
d'abord les marchandises, puis les services ; les avoirs et remboursements
diminuent le chiffre d'affaires.

Les cotisations sont des estimations : vérifiez que les taux correspondent à
votre situation.

## Prérequis

Dolibarr 23 ou plus récent, PHP 7.2 à 8.5, modules Factures et Comptabilité
(simplifiée).

## Mise à jour depuis AutoMicroEntreprise 7

Déployez la nouvelle version, désactivez puis réactivez le module : les taux
sont conservés et convertis.

## Support

Questions et signalements de bugs, en français ou en anglais :
<https://github.com/Aohzan/dolibarr-micro-entreprise-compta/issues>
