<?php
$statutDecision = ""; 
$serviceRecommande = "";
$plainteSaisie = "";
$messageErreur = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST['plainte'])) {
    $plainteSaisie = trim($_POST['plainte']);
    
    // Nettoyage de la chaîne
    $plainteSecurisee = preg_replace('/[^a-zA-Z0-9 áàâäãåçéèêëíìîïñóòôöõúùûüýÿæœÁÀÂÄÃÅÇÉÈÊËÍÌÎÏÑÓÒÔÖÕÚÙÛÜÝŸÆŒ]/u', '', $plainteSaisie);

    // Ajustement automatique du nom de fichier
    $scriptProlog = __DIR__ . DIRECTORY_SEPARATOR . 'prise_decision.pl';

    $candidatsSwipl = [
        'C:\\Program Files\\swipl\\bin\\swipl.exe',
        'C:\\Program Files\\swipl\\bin\\swipl-win.exe',
        'swipl.exe',
        'swipl'
    ];

    $cheminSwipl = null;
    foreach ($candidatsSwipl as $candidat) {
        if (file_exists($candidat)) {
            $cheminSwipl = $candidat;
            break;
        }
    }

    if ($cheminSwipl === null) {
        $messageErreur = 'SWI-Prolog est introuvable. Vérifiez votre installation.';
    } else {
        $plainteProlog = '"' . str_replace('"', '\\"', $plainteSecurisee) . '"';
        
        // CORRECTION : Appel à evaluer_requete/2 et capture textuelle des retours complexes
        $goal = 'normalize_string(' . $plainteProlog . ', Mots), (evaluer_requete(Mots, ok(S)) -> writeln(S) ; (evaluer_requete(Mots, salutation) -> writeln(\'salutation\') ; (evaluer_requete(Mots, trop_court) -> writeln(\'trop_court\') ; writeln(\'reception\')))), halt.';

        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $process = proc_open([$cheminSwipl, '-q', '-s', $scriptProlog, '-g', $goal], $descriptorspec, $pipes);

        if (is_resource($process)) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
            $returnVar = proc_close($process);

            $output = array_values(array_filter(explode(PHP_EOL, trim((string) $stdout))));

            if ($returnVar === 0 && !empty($output)) {
                $reponseIA = trim($output[0]);
                if ($reponseIA === 'salutation' || $reponseIA === 'trop_court' || $reponseIA === 'reception') {
                    $statutDecision = $reponseIA;
                } else {
                    $statutDecision = "ok";
                    $serviceRecommande = $reponseIA;
                }
            } else {
                $messageErreur = 'Erreur d\'exécution du moteur IA.';
                if (!empty(trim((string) $stderr))) {
                    $messageErreur .= ' Détail : ' . trim((string) $stderr);
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test Orientation IA - SEADO</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f9; padding: 40px; }
        .container { max-width: 600px; background: white; margin: 0 auto; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h2 { color: #2c3e50; text-align: center; margin-bottom: 25px; }
        textarea { width: 100%; height: 100px; padding: 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 16px; resize:none; }
        button { width: 100%; background: #3498db; color: white; border: none; padding: 12px; font-size: 16px; border-radius: 4px; cursor: pointer; margin-top: 15px; font-weight: bold; }
        .result { margin-top: 30px; padding: 20px; border-radius: 6px; text-align: center; font-size: 18px; font-weight: bold; }
        .success { background: #e8f8f5; border: 2px dashed #2ecc71; color: #27ae60; }
        .warning { background: #fef9e7; border: 2px dashed #f39c12; color: #d35400; }
        .info { background: #ebf5fb; border: 2px dashed #3498db; color: #2980b9; }
        .danger { background: #fdf2f2; border: 2px dashed #e74c3c; color: #c0392b; }
        .badge { display: inline-block; padding: 6px 12px; background: #2c3e50; color: white; border-radius: 20px; text-transform: uppercase; font-size: 14px; margin-top: 5px; }
    </style>
</head>
<body>

<div class="container">
    <h2>Orientation Intelligente du Patient</h2>
    
    <form method="POST" action="">
        <textarea name="plainte" placeholder="Décrivez vos symptômes..." required><?php echo htmlspecialchars($plainteSaisie); ?></textarea>
        <button type="submit">Soumettre à l'IA d'Orientation</button>
    </form>

    <?php if ($statutDecision === "salutation"): ?>
        <div class="result warning">
            Bonjour ! Vous n'avez saisi qu'une salutation.<br>
            <span style="font-weight:normal; font-size:15px;">Veuillez détailler vos symptômes pour que l'IA puisse vous orienter.</span>
        </div>
    <?php endif; ?>

    <?php if ($statutDecision === "trop_court"): ?>
        <div class="result info">
            Votre description est trop courte.<br>
            <span style="font-weight:normal; font-size:15px;">Veuillez donner une brève explication (ex: "J'ai mal au coeur" au lieu de juste "coeur") pour être acheminé vers la bonne file.</span>
        </div>
    <?php endif; ?>

    <?php if ($statutDecision === "reception"): ?>
        <div class="result danger">
            Désolé, cette file n'existe pas dans notre hôpital.<br>
            <span style="font-weight:normal; font-size:15px;">Consigne : Veuillez aller consulter directement le réceptionniste.</span>
        </div>
    <?php endif; ?>

    <?php if ($statutDecision === "ok"): ?>
        <div class="result success">
            Analyse réussie ! Dirigez-vous vers :<br>
            <span class="badge"><?php echo htmlspecialchars($serviceRecommande); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($messageErreur)): ?>
        <div class="result danger"><strong>Erreur :</strong> <?php echo htmlspecialchars($messageErreur); ?></div>
    <?php endif; ?>
</div>

</body>
</html>