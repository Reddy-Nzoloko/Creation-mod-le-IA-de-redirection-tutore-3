<?php
// Initialisation des variables pour l'affichage
$serviceRecommande = "";
$messageErreur = "";
$plainteSaisie = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST['plainte'])) {
    $plainteSaisie = trim($_POST['plainte']);
    
    // 1. Nettoyage de la chaîne pour éviter les injections de commandes systeme
    // On retire les caractères spéciaux dangereux pour le terminal
    $plainteSecurisee = preg_replace('/[^a-zA-Z0-9 áàâäãåçéèêëíìîïñóòôöõúùûüýÿæœÁÀÂÄÃÅÇÉÈÊËÍÌÎÏÑÓÒÔÖÕÚÙÛÜÝŸÆŒ]/u', '', $plainteSaisie);

    // 2. Construction de la commande SWI-Prolog
    // On appelle swipl en mode silencieux (-q), on charge le fichier, et on lance la requête
    // path_decision.pl doit être dans le même dossier.
    $scriptProlog = __DIR__ . '/prise_decision.pl';
    
    // Cette commande interroge le prédicat service_recommande de ton fichier Prolog
    // On convertit la phrase en liste de mots via le découpage en Prolog pour coller à ta structure
  // Version avec le chemin direct vers l'exécutable (Windows standard)
$cheminSwipl = '"C:\Program Files\swipl\binswipl-win.exe"';

$commande = sprintf(
    '%s -q -s "%s" -g "normalize_string(\'%s\', Mots), (service_recommande(Mots, S) -> writeln(S) ; writeln(autre)), halt."',
    $cheminSwipl,
    $scriptProlog,
    addslashes($plainteSecurisee)
);

    // 3. Exécution de la commande et récupération du résultat
    $output = [];
    $returnVar = 0;
    exec($commande, $output, $returnVar);

    // 4. Analyse de la réponse du moteur Prolog
    if ($returnVar === 0 && !empty($output)) {
        // Le premier élément du tableau de sortie contient le nom du service renvoyé par writeln(S)
        $serviceRecommande = trim($output[0]);
    } else {
        $messageErreur = "Le moteur IA Prolog n'a pas pu traiter la demande. Vérifie que SWI-Prolog est bien installé et accessible dans le PATH.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Orientation IA - SEADO</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; color: #333; padding: 40px; }
        .container { max-width: 600px; background: white; margin: 0 auto; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h2 { color: #2c3e50; text-align: center; margin-bottom: 25px; }
        label { font-weight: bold; display: block; margin-bottom: 8px; color: #555; }
        textarea { width: 100%; height: 100px; padding: 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; resize: none; font-size: 16px; }
        button { width: 100%; background: #3498db; color: white; border: none; padding: 12px; font-size: 16px; border-radius: 4px; cursor: pointer; margin-top: 15px; font-weight: bold; }
        button:hover { background: #2980b9; }
        .result { margin-top: 30px; padding: 20px; border-radius: 6px; text-align: center; font-size: 18px; }
        .success { background: #e8f8f5; border: 2px dashed #2ecc71; color: #27ae60; }
        .error { background: #fdf2f2; border: 2px dashed #e74c3c; color: #c0392b; }
        .badge { display: inline-block; padding: 6px 12px; background: #2c3e50; color: white; border-radius: 20px; text-transform: uppercase; font-size: 14px; margin-top: 5px; }
    </style>
</head>
<body>

<div class="container">
    <h2>Orientation Intelligente du Patient</h2>
    
    <form method="POST" action="">
        <label for="plainte">Décrivez ce qui ne va pas (Symptômes) :</label>
        <textarea id="plainte" name="plainte" placeholder="Ex: J'ai une terrible douleur au coude depuis ma chute ou J'ai des palpitations cardiaques..." required><?php echo htmlspecialchars($plainteSaisie); ?></textarea>
        <button type="submit">Soumettre à l'IA d'Orientation</button>
    </form>

    <?php if (!empty($serviceRecommande)): ?>
        <div class="result success">
            Résultat de l'analyse Prolog : <br>
            Le patient doit être orienté vers la file d'attente : <br>
            <span class="badge"><?php echo htmlspecialchars($serviceRecommande); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($messageErreur)): ?>
        <div class="result error">
            <strong>Erreur :</strong> <?php echo htmlspecialchars($messageErreur); ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>