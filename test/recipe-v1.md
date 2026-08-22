# Recette fonctionnelle V1 - Procédures PV

## Préconditions

- Module `procedurespv` activé.
- Droits accordés à l'utilisateur de test : lecture, écriture, collecte, validation collecte, validation mandat, CARDi, conventions, mise en service et relances.
- Module Agenda activé pour vérifier les événements de relance et de mise en service.
- Répertoire documentaire du module accessible en écriture.

## Scénario 1 - Raccordement avec Centrale PV présente

### Données de test

- Tiers client existant.
- Projet Dolibarr existant.
- Centrale PV existante, si le module Centrale PV est installé.
- Puissance installée : `36 kWc`.
- Type d'exploitation : autoconsommation avec surplus.

### Étapes

1. Créer un raccordement depuis `Raccordement > Nouveau raccordement`.
2. Renseigner le client, le projet, l'identifiant Centrale PV et sélectionner `Centrale PV` comme source du site.
3. Enregistrer puis vérifier que le nom du site, l'adresse, le code postal, la commune, le PRM/PDL, la puissance installée et la puissance du raccordement disponibles dans la centrale sont repris dans le raccordement.
4. Ouvrir l'onglet `Collecte client` et générer un lien public de collecte.
5. Ouvrir le lien public et vérifier que les données du site de la centrale sont préremplies.
6. Corriger au moins une donnée du site, compléter les informations, déposer une facture d'électricité et signer le mandat.
7. Revenir côté interne et vérifier que les corrections, y compris celles des puissances, figurent dans le raccordement et dans la centrale PV sélectionnée.
8. Vérifier dans le tableau des pièces que l'origine commence par une majuscule et que l'absence de fichier affiche le libellé natif « Pas de documents téléversés ».
9. Valider la pièce et le mandat.
10. Ouvrir `Demande de raccordement`, renseigner la référence ENEDIS et marquer déposé.
11. Vérifier que le snapshot est figé.
12. Ajouter une convention, la marquer signée.
13. Marquer la mise en service réalisée.

### Résultat attendu

- Le raccordement avance jusqu'au statut `Mise en service réalisée`.
- Les données disponibles de la centrale sont reprises dans le raccordement et la collecte, puis les corrections du mandat sont enregistrées dans les deux objets.
- La pièce et le mandat signé sont visibles côté interne.
- La convention signée fait évoluer le statut global vers `Convention / contrat signé`.
- La mise en service réalisée crée un événement Agenda si Agenda est actif.

### Points de contrôle techniques

- Les chemins documentaires utilisent le répertoire Dolibarr du raccordement.
- Les actions internes sensibles portent un token.
- Aucun fichier n'est servi directement depuis `public/`.
- L'objet reste filtré par `entity`.

## Scénario 2 - Raccordement sans Centrale PV

### Données de test

- Tiers client existant.
- Aucun identifiant Centrale PV.
- Site local : adresse, code postal, ville, PRM.

### Étapes

1. Créer un raccordement autonome.
2. Saisir les données de site local.
3. Générer le lien public.
4. Compléter et soumettre la collecte côté public.
5. Contrôler les informations côté interne.

### Résultat attendu

- Le raccordement fonctionne sans dépendance Centrale PV.
- Les champs du site local restent portés par le raccordement.
- Le client ne peut pas modifier une centrale PV inexistante ou externe.

### Points de contrôle techniques

- Aucune erreur fatale si Centrale PV est absent.
- L'adapter Centrale PV retourne un état indisponible propre.

## Scénario 3 - Lien public expiré

### Données de test

- Raccordement avec lien public actif.
- Date d'expiration forcée en base ou durée de validité réglée à `1` jour puis dépassée.

### Étapes

1. Ouvrir le lien public expiré.
2. Tenter d'accéder au formulaire.

### Résultat attendu

- Le formulaire ne s'affiche pas.
- Un message générique indique que le lien est invalide, expiré, soumis ou révoqué.
- Aucune information client ou raccordement n'est divulguée.

### Points de contrôle techniques

- Le token stocké en base est hashé.
- L'accès est refusé avant tout affichage métier.

## Scénario 4 - Mandat non conforme

### Données de test

- Raccordement avec mandat signé en ligne.

### Étapes

1. Ouvrir l'onglet `Collecte client`.
2. Refuser le mandat.
3. Vérifier le statut du mandat.

### Résultat attendu

- Le mandat passe en statut `Non conforme`.
- Le raccordement ne passe pas automatiquement en dépôt ENEDIS.

### Points de contrôle techniques

- Le mandat signé reste distinct du mandat validé.
- Le refus est protégé par token et droit `validate_mandat`.

## Scénario 5 - Relance

### Données de test

- Raccordement actif.
- Relance de type `Collecte client non soumise`.
- Canal `email`.

### Étapes

1. Ouvrir l'onglet `Relances`.
2. Créer une relance planifiée à une date passée.
3. Vérifier l'alerte de retard dans la liste et la synthèse.
4. Marquer la relance envoyée.
5. Ouvrir l'Agenda Dolibarr.

### Résultat attendu

- La relance passe en statut `Envoyée`.
- `date_envoi` est renseignée.
- Un événement Agenda est créé et lié au raccordement si Agenda est actif.
- La fiche synthèse affiche dernière relance, prochaine relance et nombre de relances actives.

### Points de contrôle techniques

- `fk_actioncomm` est renseigné lorsque l'événement Agenda est créé.
- Relancer l'action envoyée sur une relance déjà liée ne doit pas créer un doublon Agenda.

## Scénario 6 - Révision, mandat et pièces justificatives

1. Générer une collecte et vérifier que toutes les pièces attendues sont pré-listées.
2. Signer puis soumettre sans une pièce obligatoire : elle doit passer à `Manquant`.
3. Déposer la pièce côté interne : elle passe à `À contrôler` et son aperçu/téléchargement est disponible.
4. Refuser puis remplacer la pièce, avant de la valider avec le mandat.
5. Valider la collecte, puis générer une nouvelle révision et vérifier le préremplissage complet des champs et Select2.

Résultats attendus : l’historique précédent est conservé, les actions impossibles disparaissent et restent refusées côté serveur, et une collecte n’est validable qu’avec le dernier mandat et toutes les pièces obligatoires valides.

## Scénario 7 - Bénéficiaire et SIRET

1. Saisir le SIRET d’un Tiers accessible et vérifier le préremplissage de ses coordonnées et de son Contact.
2. Modifier une coordonnée dans la collecte puis soumettre.
3. Vérifier la mise à jour du Tiers et du Contact dans la bonne entité.
4. Recommencer avec un SIRET inconnu et vérifier la création du Tiers.

Résultats attendus : la synchronisation est atomique, respecte les droits et ne laisse aucune création partielle en cas d’erreur.

## Scénario 8 - Équipements PowerPlantPV

1. Sélectionner deux modèles d’onduleurs et deux modèles de modules, avec des quantités différentes.
2. Vérifier les puissances unitaires, les totaux de ligne et les agrégats affichés en direct.
3. Enregistrer et comparer les totaux serveur : somme des quantités, kVA des onduleurs et kWc des modules.
4. Tester un produit sans puissance avec puis sans les droits de modification PowerPlantPV.
5. Modifier ensuite le catalogue : l’instantané existant ne change pas ; après suppression/réajout, la valeur actuelle est reprise.
6. Tester un produit d’une mauvaise catégorie et un produit d’une entité inaccessible.

Résultats attendus : aucune liste d’identifiants n’est stockée, les instantanés restent stables, les caractéristiques manquantes sont corrigées uniquement via les API PowerPlantPV et la puissance du raccordement (`puissance_injection_kva`) reste indépendante des équipements.

## Scénario 9 - Documents, Agenda et Multicompany

1. Déposer chaque document technique et justificatif, puis contrôler sa présence dans `Fichiers joints`.
2. Vérifier les liens d’aperçu et de téléchargement avec un utilisateur en lecture seule.
3. Contrôler l’unicité des événements Agenda après dépôt, validation/refus, soumission, synchronisation et modification des équipements.
4. Refaire la recette sur un raccordement appartenant à une seconde entité puis consulté par partage.

Résultats attendus : les fichiers restent dans le répertoire de l’entité propriétaire, aucun événement n’est dupliqué et les droits d’écriture restent distincts du droit de lecture documentaire.

## Scénario 10 - Pages transverses natives indépendantes

1. Ouvrir successivement `Contacts/Adresses`, `Fichiers joints` et `Événements/Agenda` depuis la fiche d’un raccordement.
2. Vérifier que chaque onglet charge son propre fichier PHP et conserve la bannière, les onglets et les droits du raccordement.
3. Ajouter puis retirer un contact interne et un contact externe avec les types proposés pour le raccordement.
4. Déposer, renommer, prévisualiser, télécharger puis supprimer un fichier depuis la page documentaire.
5. Créer un événement depuis la page Agenda et vérifier son lien vers le raccordement.
6. Ouvrir les anciennes URL `card.php?id=<id>&tab=contacts`, `documents` et `agenda`.

Résultats attendus : les trois pages utilisent les composants natifs Dolibarr, les anciennes URL redirigent vers les nouvelles pages, les actions sont protégées par droits et token, et les documents utilisent l’entité propriétaire du raccordement.

## Scénario 11 - Badges, Consuel et verrouillage de la MES

1. Vérifier les badges d’une collecte en attente, soumise puis validée : gris contour, orange puis vert.
2. Vérifier les badges `À compléter`, `À contrôler`, `Invalide` et `Clôturé` : ambre, ambre, rouge et gris plein.
3. Renseigner la date et la référence Consuel sans fichier, puis contrôler que `Demander la mise en service` reste grisé et que son aide mentionne le fichier manquant.
4. Joindre puis remplacer l’attestation Consuel et contrôler son aperçu, son téléchargement et sa présence dans la page indépendante `Fichiers joints`.
5. Tester séparément chaque prérequis MES : collecte/mandat/pièces, demande ENEDIS, CARDi requis, convention et triplet Consuel.
6. Appeler directement l’action `mark_requested` avec un prérequis manquant et vérifier le refus serveur, puis refaire le test avec le dossier complet.
7. Marquer la MES réalisée avec toutes les étapes terminées et vérifier la clôture automatique, sans bouton manuel `Clôturer`.
8. Invalider ensuite un CARDi ou rendre la convention obsolète et vérifier la réouverture sur la première étape incomplète.
9. Rejouer le dépôt et le téléchargement du Consuel sur deux entités et depuis un objet partagé.

Résultats attendus : aucun badge spécifique au module n’est utilisé, les éléments facultatifs restent neutres et ne bloquent pas la complétude, le Consuel est stocké dans le répertoire documentaire de l’entité propriétaire, et aucune requête directe ne contourne les prérequis MES.

## Scénario 12 - Autocomplétion des adresses françaises

1. Ouvrir un lien public de collecte utilisable et commencer à saisir une adresse française du bénéficiaire.
2. Choisir une suggestion et contrôler que le numéro et la voie sont réunis dans le champ Adresse, puis que la commune, le code postal, le code INSEE et le pays France sont préremplis.
3. Refaire le test avec l’adresse du site de production et contrôler le préremplissage de l’adresse complète, de la commune et du code postal.
4. Utiliser les flèches du clavier, Entrée et Échap pour naviguer dans les suggestions.
5. Couper l’accès au service `data.geopf.fr` et vérifier que la saisie manuelle reste possible avec un message non bloquant.

Résultats attendus : les suggestions proviennent du service IGN Géoplateforme, aucune requête n’est lancée avant trois caractères ni à chaque frappe grâce à la temporisation, les champs restent modifiables après sélection et une panne du service externe ne bloque jamais la soumission manuelle.

## Scénario 13 - Bloc natif des fichiers joints et objets liés

1. Ouvrir la synthèse d’un raccordement sans fichier puis avec plusieurs fichiers et vérifier que le bloc natif `Fichiers joints` reste visible dans les deux cas.
2. Avec le droit d’écriture, ajouter un fichier avec le formulaire natif puis tester l’aperçu, le téléchargement, le renommage et la suppression.
3. Avec le seul droit de lecture, vérifier que l’aperçu et le téléchargement restent accessibles, sans action d’ajout, de renommage ni de suppression.
4. Vérifier que le tiers, le projet, la centrale photovoltaïque et le responsable sont affichés avec leur pictogramme, leur libellé ou référence et leur infobulle `getNomUrl()`.
5. Retirer le droit de lecture de chaque objet lié et contrôler que son rendu natif n’est plus cliquable.
6. Refaire les contrôles documentaires sur un raccordement partagé depuis une autre entité et vérifier que le répertoire de l’entité propriétaire reste utilisé.

Résultats attendus : le bloc et les actions proviennent de `FormFile`, aucun identifiant brut ne remplace un objet résoluble, et les droits ainsi que l’entité propriétaire sont respectés.

## Scénario 14 - Affichage conditionnel de CARD-i

1. Renseigner une puissance de raccordement demandée de `36 kVA` et une puissance souscrite en soutirage de `36 kVA`, puis vérifier que l’onglet CARD-i est absent.
2. Tester également avec des valeurs inférieures et vérifier que l’accès direct à `cardi.php` est refusé.
3. Renseigner une puissance de raccordement demandée de `36,01 kVA` et vérifier que l’onglet CARD-i apparaît.
4. Ramener cette valeur à `36 kVA`, renseigner une puissance souscrite en soutirage de `36,01 kVA` et vérifier que l’onglet reste visible.
5. Ramener les deux valeurs à `36 kVA` et vérifier que l’onglet disparaît sans que l’ancien état CARD-i ne bloque la mise en service.

Résultats attendus : le seuil est strictement supérieur à `36 kVA`, les deux puissances sont évaluées avec un opérateur logique OU, l’accès direct applique la même règle que les onglets et la puissance installée en kWc ou la puissance du raccordement en kVA n’influence pas cette visibilité.

## Scénario 15 - Reprise de la signature sur mobile

1. Ouvrir un lien public actif sur un ordinateur, renseigner partiellement la collecte et sélectionner une ou plusieurs pièces.
2. Cliquer sur `Enregistrer et signer sur mon mobile` sans compléter les champs obligatoires et sans dessiner de signature.
3. Vérifier que la page confirme l’enregistrement, que la collecte reste non soumise et que le bouton de soumission finale demeure disponible.
4. Rouvrir exactement le même lien sur un mobile et contrôler le préremplissage des champs, du nom et de la commune de signature ainsi que des quatre pouvoirs du mandat déjà cochés.
5. Vérifier que les pièces téléversées lors de la sauvegarde restent rattachées à la révision courante.
6. Signer puis soumettre depuis le mobile et vérifier le workflow habituel.
7. Refaire le scénario en continuant directement sur l’ordinateur après la sauvegarde, sans changer d’appareil.

Résultats attendus : la sauvegarde provisoire n’appelle ni `markSubmitted()`, ni la génération du mandat PDF, ni la synchronisation métier du raccordement ; le lien reste actif et les deux parcours de signature restent possibles.

## Scénario 16 - Documents reçus et signés d’une convention

1. Ouvrir l’onglet `Convention / contrat` d’un raccordement et ajouter une convention.
2. Sélectionner un fichier dans `Document reçu` et un autre dans `Document signé`, puis enregistrer.
3. Vérifier que les deux documents sont proposés en aperçu et en téléchargement dans la liste des conventions.
4. Ouvrir l’onglet `Fichiers joints` et vérifier que les deux fichiers apparaissent dans la liste native.
5. Revenir sur la synthèse et vérifier que les mêmes fichiers apparaissent dans le bloc natif `Fichiers joints`.
6. Modifier la convention, téléverser une nouvelle version d’un document et vérifier que la convention pointe vers la nouvelle version sans supprimer automatiquement l’ancienne pièce jointe.
7. Refaire le test sur un raccordement partagé depuis une autre entité et contrôler que les fichiers restent dans le répertoire documentaire de l’entité propriétaire.
8. Tester un fichier trop volumineux et une extension interdite, puis vérifier que la convention n’est pas enregistrée et qu’aucun fichier partiel ne subsiste.

Résultats attendus : les champs sont de vrais téléversements protégés par droit et token CSRF, les noms enregistrés correspondent aux fichiers du répertoire documentaire natif du raccordement, la synthèse et l’onglet `Fichiers joints` les affichent sans duplication de stockage, et un échec annule les écritures métier ainsi que les nouveaux fichiers de la tentative.

## Scénario 17 - Mandat de représentation FR30-V04

1. Ouvrir un lien public actif et atteindre la section `Mandat ENEDIS`.
2. Vérifier la présence des quatre pouvoirs du modèle FR30-V04 / FOR_RAC_02E et tenter une soumission finale en laissant au moins une case décochée.
3. Contourner ensuite la validation HTML du navigateur et refaire la requête avec une case décochée afin de contrôler le refus serveur.
4. Cocher partiellement les pouvoirs puis utiliser `Enregistrer et signer sur mon mobile` ; rouvrir le même lien et vérifier que les choix provisoires sont restaurés sans soumission.
5. Renseigner le nom et la commune de signature, cocher les quatre pouvoirs, signer puis soumettre la collecte.
6. Télécharger le mandat généré et vérifier qu’il contient exactement les données du modèle : identité et adresse des parties, quatre pouvoirs, clauses du mandat, adresse et nature du site, lieux, dates, signatures et tampon éventuel.
7. Vérifier que l’email, le téléphone, la fonction du signataire, le PRM/PDL, les puissances, le type d’exploitation, l’adresse IP et l’agent utilisateur ne figurent pas dans le PDF.
8. Répéter la génération pour une société puis une collectivité ou administration et vérifier les cases de statut correspondantes ainsi que la mise en page sur deux pages.

Résultats attendus : la soumission finale est impossible tant que les quatre pouvoirs ne sont pas cochés, y compris en contournant le navigateur ; la sauvegarde provisoire reste possible ; le mandat PDF reprend uniquement le contenu du modèle fourni et le format graphique des attestations PowerPlantPV, sans chevauchement ni troisième page.
