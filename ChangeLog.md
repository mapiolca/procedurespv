# ChangeLog

## 0.1.0

- Initialisation du squelette du module `procedurespv`.
- Ajout de l'objet `Raccordement`, de la table principale, des permissions et des pages internes de base.
- Ajout des tables V1 pour les liens publics, signatures, pièces, conventions et relances.
- Ajout d’un adapter conservateur pour l’intégration optionnelle avec Centrale PV.
- Ajout de la signature simple du mandat ENEDIS, du PDF signé et du hash SHA-256.
- Ajout de l’onglet Demande de raccordement et des champs techniques ENEDIS.
- Ajout de l’onglet CARDi et de son workflow interne.
- Ajout de l’onglet Convention / contrat avec table enfant, statuts et suivi multi-documents.
- Ajout de l’onglet Mise en service avec dates, Consuel, autorisation d’injection et événement Agenda à la réalisation.
- Ajout de l’onglet Relances avec planification, marquage envoyé/annulé, événement Agenda manuel et indicateurs sur la synthèse.
- Complément de la configuration module, des filtres opérationnels de liste et de la recette fonctionnelle V1.
- Remplacement des champs texte de modèles de courriels par des sélecteurs natifs Dolibarr filtrés par type d’objet.
- Remplacement du champ texte du modèle PDF mandat ENEDIS par la gestion native des modèles de documents Dolibarr.
- Correction de la désactivation du module en appelant `_remove()` avec la signature native Dolibarr.
- Amélioration du formulaire public de collecte et ajout du SIRET bénéficiaire obligatoire pour les clients de type société.
- Correction de la gestion du type documentaire natif du modèle PDF mandat ENEDIS dans les réglages.
- Déclaration des types de modèles de courriels du module dans la liste native Dolibarr.
- Ajout de trois modèles de courriels natifs par défaut pour la collecte client et les relances.
- Ajout du logo d’entité, des mentions légales et d’une navigation par ancres dans la page publique de collecte.
- Génération d’un mandat ENEDIS structuré d’après Enedis-MOP-RAC_046E, téléchargement public du mandat signé et tampon entreprise configurable.
- Extension de la collecte publique entreprise selon la fiche DDR ENEDIS : informations société, représentant, raccordement existant et pièces K-Bis/autorisation administrative/CARD conditionnelle.
- Raccourcissement des types de modèles de courriels natifs pour respecter la taille de colonne Dolibarr `c_email_templates.type_template`.
