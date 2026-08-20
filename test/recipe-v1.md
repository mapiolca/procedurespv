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
2. Renseigner le client, le projet et l'identifiant Centrale PV.
3. Enregistrer puis ouvrir l'onglet `Collecte client`.
4. Générer un lien public de collecte.
5. Ouvrir le lien public, compléter les informations, déposer une facture d'électricité et signer le mandat.
6. Revenir côté interne, valider la pièce et le mandat.
7. Ouvrir `Demande de raccordement`, renseigner la référence ENEDIS et marquer déposé.
8. Vérifier que le snapshot est figé.
9. Ajouter une convention, la marquer signée.
10. Marquer la mise en service réalisée.

### Résultat attendu

- Le raccordement avance jusqu'au statut `Mise en service réalisée`.
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

Résultats attendus : aucune liste d’identifiants n’est stockée, les instantanés restent stables, les caractéristiques manquantes sont corrigées uniquement via les API PowerPlantPV et `puissance_injection_kva` reste indépendante.

## Scénario 9 - Documents, Agenda et Multicompany

1. Déposer chaque document technique et justificatif, puis contrôler sa présence dans `Fichiers joints`.
2. Vérifier les liens d’aperçu et de téléchargement avec un utilisateur en lecture seule.
3. Contrôler l’unicité des événements Agenda après dépôt, validation/refus, soumission, synchronisation et modification des équipements.
4. Refaire la recette sur un raccordement appartenant à une seconde entité puis consulté par partage.

Résultats attendus : les fichiers restent dans le répertoire de l’entité propriétaire, aucun événement n’est dupliqué et les droits d’écriture restent distincts du droit de lecture documentaire.
