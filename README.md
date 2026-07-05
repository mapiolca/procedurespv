# Procédures PV

Module Dolibarr externe pour piloter les procédures photovoltaïques.

La V1 cible l'objet métier `Raccordement` et le suivi des procédures de raccordement ENEDIS. Dans ce dépôt, la racine correspond directement à la racine du module `procedurespv`. En déploiement Dolibarr, son emplacement cible est `htdocs/custom/procedurespv/`.

Compatibilité annoncée : Dolibarr v20+ et PHP 8.0+.

## Périmètre V1 implémenté

- Descripteur de module.
- Permissions de base.
- Configuration interne.
- Objet métier `Raccordement`.
- Liste et fiche de raccordement.
- Lien public de collecte client sécurisé, révocable et expirable.
- Dépôt public de pièces avec contrôle taille, extension et MIME.
- Signature simple du mandat ENEDIS, génération PDF et hash SHA-256.
- Onglets internes : Collecte client, Demande ENEDIS, CARDi, Convention / contrat, Mise en service, Relances.
- Tables V1 : raccordement, liens publics, signatures, pièces, conventions, relances.
- Modèle de numérotation minimal et modèle PDF mandat ENEDIS.

## Workflow

1. Créer un raccordement depuis la liste ou le menu.
2. Générer le lien public de collecte client depuis l'onglet Collecte.
3. Le client complète les informations, téléverse les pièces et signe le mandat ENEDIS.
4. L'équipe contrôle les pièces et le mandat, puis prépare la demande ENEDIS.
5. Le suivi se poursuit dans les onglets CARDi, Convention / contrat, Mise en service et Relances.

## Limites connues

- Pas de dépôt automatique sur le portail ENEDIS.
- Pas de synchronisation ENEDIS.
- Le formulaire public CARDi est prévu mais désactivé en V1.
- Les relances automatiques par cron ne sont pas encore activées ; la classe `Relance` expose `findDueRelances()` pour le lot suivant.

## Recette

La recette fonctionnelle V1 est disponible dans `test/recipe-v1.md`.
