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

    // 2. Exécution robuste de SWI-Prolog
    $scriptProlog = __DIR__ . DIRECTORY_SEPARATOR . 'prise_decision_hospitalier.pl';

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

        if ($candidat === 'swipl.exe' || $candidat === 'swipl') {
            $version = shell_exec(escapeshellcmd($candidat) . ' --version 2>&1');
            if ($version !== null && stripos($version, 'SWI-Prolog') !== false) {
                $cheminSwipl = $candidat;
                break;
            }
        }
    }

    if ($cheminSwipl === null) {
        $messageErreur = 'SWI-Prolog est introuvable. Installez-le ou vérifiez le chemin d\'accès.';
    } else {
        $plainteProlog = '"' . str_replace('"', '\\"', $plainteSecurisee) . '"';
        $goal = 'normalize_string(' . $plainteProlog . ', Mots), (service_recommande(Mots, S) -> writeln(S) ; writeln(autre)), halt.';

        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $process = proc_open([$cheminSwipl, '-q', '-s', $scriptProlog, '-g', $goal], $descriptorspec, $pipes);

        if (is_resource($process)) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $returnVar = proc_close($process);

            $output = array_values(array_filter(explode(PHP_EOL, trim((string) $stdout)), static function ($ligne) {
                return trim($ligne) !== '';
            }));

            if ($returnVar === 0 && !empty($output)) {
                $serviceRecommande = trim($output[0]);
            } else {
                $messageErreur = 'Le moteur IA Prolog n\'a pas pu traiter la demande.';
                if (!empty(trim((string) $stderr))) {
                    $messageErreur .= ' Détail : ' . trim((string) $stderr);
                }
            }
        } else {
            $messageErreur = 'Impossible d\'initialiser SWI-Prolog depuis PHP.';
        }
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