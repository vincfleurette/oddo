<?php

/**
 * Script de nettoyage pour supprimer tous les fichiers de debug
 */

declare(strict_types=1);

echo "🧹 Nettoyage du projet Oddo Slim API\n";
echo "=====================================\n\n";

$rootDir = dirname(__DIR__);
$cleaned = [];
$errors = [];

// Fichiers à supprimer (patterns)
$filesToDelete = [
    // Fichiers de debug temporaires
    "debug_*.php",
    "test_*.php",
    "minimal_*.php",

    // Logs de développement
    "*.log",
    "debug.txt",

    // Fichiers de cache temporaires
    "storage/debug_*",
    "storage/test_*",
];

// Dossiers à nettoyer
$dirsToClean = ["storage", "public", "src"];

function deleteFiles(string $pattern, string $baseDir): array
{
    $deleted = [];
    $files = glob($baseDir . "/" . $pattern);

    foreach ($files as $file) {
        if (is_file($file)) {
            if (unlink($file)) {
                $deleted[] = $file;
            }
        }
    }

    return $deleted;
}

function cleanDirectory(string $dir, array $patterns): array
{
    $cleaned = [];

    if (!is_dir($dir)) {
        return $cleaned;
    }

    foreach ($patterns as $pattern) {
        $files = deleteFiles($pattern, $dir);
        $cleaned = array_merge($cleaned, $files);
    }

    return $cleaned;
}

// Nettoyer les fichiers de debug dans chaque dossier
foreach ($dirsToClean as $dir) {
    $fullPath = $rootDir . "/" . $dir;
    echo "🔍 Nettoyage de $dir/\n";

    $deletedFiles = cleanDirectory($fullPath, $filesToDelete);

    if (!empty($deletedFiles)) {
        foreach ($deletedFiles as $file) {
            echo "   ✅ Supprimé: " . basename($file) . "\n";
            $cleaned[] = $file;
        }
    } else {
        echo "   ✨ Déjà propre\n";
    }
}

// Nettoyer les fichiers de cache expirés
echo "\n🗑️  Nettoyage du cache expiré\n";
$storageDir = $rootDir . "/storage";

if (is_dir($storageDir)) {
    $cacheFiles = glob($storageDir . "/*.json");
    $now = time();

    foreach ($cacheFiles as $file) {
        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if (isset($data["ttl"]) && $data["ttl"] > 0) {
            $timestamp = strtotime($data["timestamp"]);
            $expiry = $timestamp + $data["ttl"];

            if ($now > $expiry) {
                if (unlink($file)) {
                    echo "   ✅ Cache expiré supprimé: " .
                        basename($file) .
                        "\n";
                    $cleaned[] = $file;
                }
            }
        }
    }
}

// Optimiser l'autoloader Composer
echo "\n⚡ Optimisation de l'autoloader\n";
$composerCmd = "composer dump-autoload --optimize";
exec($composerCmd, $output, $returnCode);

if ($returnCode === 0) {
    echo "   ✅ Autoloader optimisé\n";
} else {
    echo "   ❌ Erreur lors de l'optimisation\n";
    $errors[] = "Composer optimization failed";
}

// Vérifier les permissions du dossier storage
echo "\n🔒 Vérification des permissions\n";
if (is_dir($storageDir)) {
    if (is_writable($storageDir)) {
        echo "   ✅ storage/ est accessible en écriture\n";
    } else {
        echo "   ⚠️  storage/ n'est pas accessible en écriture\n";
        echo "      Exécutez: chmod 755 storage/\n";
    }
} else {
    echo "   📁 Création du dossier storage/\n";
    if (mkdir($storageDir, 0755, true)) {
        echo "   ✅ Dossier storage/ créé\n";
    } else {
        echo "   ❌ Impossible de créer storage/\n";
        $errors[] = "Cannot create storage directory";
    }
}

// Vérifier la configuration
echo "\n⚙️  Vérification de la configuration\n";
$envFile = $rootDir . "/.env";

if (file_exists($envFile)) {
    echo "   ✅ Fichier .env trouvé\n";

    // Vérifier les variables critiques
    $envContent = file_get_contents($envFile);
    $requiredVars = ["ODDO_BASE_URI", "JWT_SECRET"];

    foreach ($requiredVars as $var) {
        if (strpos($envContent, $var . "=") !== false) {
            echo "   ✅ $var configuré\n";
        } else {
            echo "   ⚠️  $var manquant dans .env\n";
        }
    }
} else {
    echo "   ⚠️  Fichier .env manquant\n";
    echo "      Exécutez: cp .env.example .env\n";
}

// Résumé
echo "\n📊 Résumé du nettoyage\n";
echo "=====================\n";
echo "Fichiers supprimés: " . count($cleaned) . "\n";

if (!empty($errors)) {
    echo "Erreurs: " . count($errors) . "\n";
    foreach ($errors as $error) {
        echo "   ❌ $error\n";
    }
}

if (empty($cleaned) && empty($errors)) {
    echo "✨ Projet déjà propre et optimisé!\n";
} elseif (empty($errors)) {
    echo "✅ Nettoyage terminé avec succès!\n";
} else {
    echo "⚠️  Nettoyage terminé avec des avertissements.\n";
}

echo "\n🚀 Projet prêt pour la production!\n";
