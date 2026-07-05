# Prise de décision hospitalière

Ce projet est un modèle Prolog pour guider un patient vers le service hospitalier le plus adapté en fonction de sa plainte.

## Objectif

- Un patient arrive, décrit son problème.
- Le système Prolog analyse la plainte.
- Il propose le service hospitalier et gère une file d'attente pour ce service.

## Services inclus

- `cardiologie`
- `orthopedie`
- `gyneco`
- `imagerie`
- `pediatrie`
- `urgence`
- `neurologie`
- `autre`

## Étapes de travail

1. Installer SWI-Prolog.
2. Ouvrir `prise_decision_hospitalier.pl`.
3. Lancer Prolog dans le dossier de projet.
4. Charger le fichier : `?- [prise_decision_hospitalier].`
5. Démarrer : `?- start.`
6. Ajouter de nouveaux mots-clés et services selon les besoins.

## Développement étape par étape

1. Définir les services et la file d'attente pour chaque service.
2. Écrire les règles de correspondance entre plainte et service.
3. Créer des prédicats pour enregistrer un patient et l'ajouter à une queue.
4. Afficher l'état des files d'attente.
5. Améliorer progressivement le diagnostic (plus de mots-clés, phrases, NLP simple).
6. Penser à l'intégration dans une interface externe (site web ou application).

## Extension possible

- Ajouter un service `dermatologie`, `psychiatrie`, `ORL`, etc.
- Remplacer la recherche textuelle simple par un moteur NLP plus avancé.
- Créer une interface web qui envoie les plaintes à Prolog.
- Ajouter un mécanisme de priorité pour les urgences.
