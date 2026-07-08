# Prise de décision hospitalière - SEADO

Ce projet intègre un moteur de règles expert écrit en Prolog pour qualifier et router automatiquement les plaintes des patients vers les files d'attente adéquates.

## Améliorations Système

- **Filtrage des Salutations :** Bloque les saisies de type "Bonjour", "Salut" pour forcer un motif médical.
- **Vérification de Longueur :** Rejette les mots uniques (ex: "tete") et demande une phrase contextuelle courte explicative.
- **Gestion Hors-Contexte (Sécurité) :** Les demandes n'ayant aucun rapport médical ne saturent plus le système et renvoient vers la réception humaine.

## Exécution locale

1. Placez `prise_decision.pl` et `test_ia.php` dans votre répertoire web local (ex: `www/`).
2. Accédez à `http://localhost/test_ia.php` depuis votre navigateur.