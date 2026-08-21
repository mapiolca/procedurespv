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
- Sauvegarde provisoire de la collecte avant signature, avec reprise du même lien sur mobile sans soumettre le dossier.
- Dépôt public de pièces avec contrôle taille, extension et MIME.
- Signature simple du mandat ENEDIS, génération PDF et hash SHA-256.
- Mandat ENEDIS structuré d’après Enedis-MOP-RAC_046E, avec téléchargement public après soumission et tampon entreprise configurable.
- Collecte publique entreprise enrichie selon la fiche DDR ENEDIS : société, représentant, site, raccordement existant et pièces obligatoires, avec CARD conditionnelle.
- Révisions de collecte conservant les données soumises, le mandat et les pièces justificatives associés.
- Workflow de contrôle des pièces (`En attente`, `Facultatif`, `Manquant`, `À contrôler`, `Valide`, `Invalide`) avec dépôt interne, aperçu, téléchargement, validation et refus.
- Synchronisation transactionnelle du bénéficiaire avec les Tiers et Contacts Dolibarr à partir du SIRET.
- Sélection structurée des onduleurs et modules depuis le catalogue PowerPlantPV, avec quantités, instantanés de puissance et agrégats serveur.
- Affichage des fichiers collectés et générés dans le répertoire documentaire et l’onglet natif `Fichiers joints`.
- Téléversement des documents reçus et signés depuis les conventions, avec aperçu et remontée automatique dans l’onglet `Fichiers joints` et le bloc documentaire de la synthèse.
- Autocomplétion des adresses françaises du bénéficiaire et du site de production avec le service de géocodage IGN Géoplateforme alimenté par la Base Adresse Nationale ; le navigateur transmet uniquement le texte recherché à `data.geopf.fr` et la saisie manuelle reste disponible en cas d’indisponibilité du service.
- Bloc natif de fichiers joints directement sur la synthèse du raccordement, avec actions documentaires soumises aux droits Dolibarr.
- Affichage natif des tiers, projets, centrales photovoltaïques et responsables liés via le `getNomUrl()` de leur objet.
- Lorsque la centrale PV est la source du site, reprise automatique des données disponibles de la centrale dans le raccordement et la collecte, y compris les puissances installée et de raccordement ; les corrections saisies lors du mandat sont répercutées dans le raccordement et dans l’objet Centrale PV natif.
- Pages indépendantes `Contacts/Adresses`, `Fichiers joints` et `Événements/Agenda`, basées directement sur les actions, gabarits et listes natifs Dolibarr.
- Badges d’avancement harmonisés avec `dolGetStatus()`, clôture automatique du raccordement et réouverture sur la première étape obligatoire redevenue incomplète.
- Consuel obligatoire fondé sur la date, la référence et une pièce documentaire native, avec verrouillage serveur de la demande de mise en service tant que le dossier n’est pas conforme.
- Sélection des modèles de courriels depuis les modèles natifs Dolibarr par type d’objet.
- Sélection native du modèle PDF du mandat ENEDIS dans les modèles de documents Dolibarr.
- Onglets internes : Collecte client, Demande ENEDIS, Convention / contrat, Mise en service et Relances ; l’onglet CARD-i apparaît uniquement si la puissance de raccordement demandée ou la puissance souscrite en soutirage est strictement supérieure à 36 kVA.
- Tables V1 : raccordement, liens publics, signatures, pièces, équipements, conventions, relances.
- Modèle de numérotation minimal et modèle PDF mandat ENEDIS.

## Workflow

1. Créer un raccordement depuis la liste ou le menu.
2. Générer le lien public de collecte client depuis l'onglet Collecte.
3. Le client complète les informations, téléverse les pièces et signe le mandat ENEDIS.
4. L'équipe contrôle les pièces et le mandat, puis prépare la demande ENEDIS.
5. Le suivi se poursuit dans les onglets Convention / contrat, Mise en service et Relances, ainsi que dans CARD-i lorsque le seuil de puissance applicable est dépassé.

## Limites connues

- Pas de dépôt automatique sur le portail ENEDIS.
- Pas de synchronisation ENEDIS.
- La sélection structurée des équipements est conditionnelle à l’activation de PowerPlantPV et à la disponibilité de ses API publiques compatibles ; les valeurs historiques restent lisibles sinon.
- Le formulaire public CARDi est prévu mais désactivé en V1.
- Les relances automatiques par cron ne sont pas encore activées ; la classe `Relance` expose `findDueRelances()` pour le lot suivant.
- Les modèles de courriels doivent être créés dans l’administration native Dolibarr avec les types `procedurespv_collecte`, `procedurespv_relance_collecte` ou `procedurespv_relance_mandat`.

## Recette

La recette fonctionnelle V1 est disponible dans `test/recipe-v1.md`.
