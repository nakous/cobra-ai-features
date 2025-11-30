# Script de compilation des fichiers de traduction
# Usage: .\compile-translations.ps1

Write-Host "🌍 Compilation des traductions Cobra AI Features" -ForegroundColor Cyan
Write-Host "=" -repeat 60 -ForegroundColor Gray
Write-Host ""

# Chemin du plugin
$pluginPath = $PSScriptRoot
$languagesPath = Join-Path $pluginPath "languages"

# Vérifier si le dossier languages existe
if (-not (Test-Path $languagesPath)) {
    Write-Host "❌ Erreur: Le dossier 'languages' n'existe pas" -ForegroundColor Red
    exit 1
}

# Trouver tous les fichiers .po
$poFiles = Get-ChildItem -Path $languagesPath -Filter "*.po"

if ($poFiles.Count -eq 0) {
    Write-Host "❌ Aucun fichier .po trouvé dans le dossier languages" -ForegroundColor Red
    exit 1
}

Write-Host "📁 Fichiers .po trouvés: $($poFiles.Count)" -ForegroundColor Yellow
Write-Host ""

# Fonction pour compiler avec msgfmt
function Compile-WithMsgfmt {
    param (
        [string]$poFile,
        [string]$moFile
    )
    
    try {
        $msgfmt = Get-Command msgfmt -ErrorAction Stop
        & msgfmt -o $moFile $poFile 2>&1 | Out-Null
        return $true
    } catch {
        return $false
    }
}

# Fonction pour compiler avec PHP (si gettext est disponible)
function Compile-WithPHP {
    param (
        [string]$poFile,
        [string]$moFile
    )
    
    $phpCode = @"
<?php
if (!function_exists('po2mo')) {
    function po2mo(`$poFile, `$moFile) {
        `$po = file_get_contents(`$poFile);
        `$lines = explode("\n", `$po);
        
        `$entries = [];
        `$msgid = '';
        `$msgstr = '';
        `$inMsgid = false;
        `$inMsgstr = false;
        
        foreach (`$lines as `$line) {
            `$line = trim(`$line);
            
            if (strpos(`$line, 'msgid ') === 0) {
                if (`$msgid && `$msgstr) {
                    `$entries[`$msgid] = `$msgstr;
                }
                `$msgid = trim(substr(`$line, 6), '"');
                `$msgstr = '';
                `$inMsgid = true;
                `$inMsgstr = false;
            } elseif (strpos(`$line, 'msgstr ') === 0) {
                `$msgstr = trim(substr(`$line, 7), '"');
                `$inMsgid = false;
                `$inMsgstr = true;
            } elseif (`$line && `$line[0] === '"') {
                `$text = trim(`$line, '"');
                if (`$inMsgid) {
                    `$msgid .= `$text;
                } elseif (`$inMsgstr) {
                    `$msgstr .= `$text;
                }
            }
        }
        
        if (`$msgid && `$msgstr) {
            `$entries[`$msgid] = `$msgstr;
        }
        
        // Créer le fichier .mo (version simplifiée)
        `$magic = pack('V', 0x950412de);
        `$revision = pack('V', 0);
        `$count = count(`$entries);
        
        `$idsOffset = 28;
        `$stringsOffset = `$idsOffset + `$count * 8;
        `$dataOffset = `$stringsOffset + `$count * 8;
        
        `$data = '';
        `$ids = '';
        `$strings = '';
        
        foreach (`$entries as `$id => `$str) {
            `$ids .= pack('VV', strlen(`$id), `$dataOffset + strlen(`$data));
            `$data .= `$id . chr(0);
            `$strings .= pack('VV', strlen(`$str), `$dataOffset + strlen(`$data));
            `$data .= `$str . chr(0);
        }
        
        `$header = `$magic . `$revision . pack('V', `$count) . 
                  pack('V', `$idsOffset) . pack('V', `$stringsOffset);
        
        file_put_contents(`$moFile, `$header . `$ids . `$strings . `$data);
        return true;
    }
}

po2mo('$poFile', '$moFile');
echo 'OK';
"@
    
    try {
        $result = php -r $phpCode 2>&1
        return $result -eq 'OK'
    } catch {
        return $false
    }
}

# Compteurs
$compiled = 0
$failed = 0

# Compiler chaque fichier .po
foreach ($poFile in $poFiles) {
    $fileName = $poFile.Name
    $moFileName = $fileName -replace '\.po$', '.mo'
    $moFile = Join-Path $languagesPath $moFileName
    
    Write-Host "📝 Traitement: $fileName" -ForegroundColor White
    
    # Vérifier si le fichier .po est valide
    $poContent = Get-Content $poFile.FullName -Raw
    if ([string]::IsNullOrWhiteSpace($poContent)) {
        Write-Host "   ⚠️  Fichier vide, ignoré" -ForegroundColor Yellow
        $failed++
        continue
    }
    
    # Essayer msgfmt d'abord
    $success = Compile-WithMsgfmt -poFile $poFile.FullName -moFile $moFile
    
    if ($success) {
        Write-Host "   ✅ Compilé avec msgfmt" -ForegroundColor Green
        $compiled++
    } else {
        # Essayer avec PHP
        Write-Host "   ℹ️  msgfmt non disponible, tentative avec PHP..." -ForegroundColor Yellow
        $success = Compile-WithPHP -poFile $poFile.FullName -moFile $moFile
        
        if ($success) {
            Write-Host "   ✅ Compilé avec PHP" -ForegroundColor Green
            $compiled++
        } else {
            Write-Host "   ❌ Échec de la compilation" -ForegroundColor Red
            $failed++
        }
    }
    
    Write-Host ""
}

# Résumé
Write-Host "=" -repeat 60 -ForegroundColor Gray
Write-Host "📊 Résumé de la compilation:" -ForegroundColor Cyan
Write-Host "   ✅ Compilés avec succès: $compiled" -ForegroundColor Green
Write-Host "   ❌ Échecs: $failed" -ForegroundColor Red
Write-Host ""

if ($failed -eq 0) {
    Write-Host "🎉 Toutes les traductions ont été compilées avec succès!" -ForegroundColor Green
} else {
    Write-Host "⚠️  Certaines traductions n'ont pas pu être compilées." -ForegroundColor Yellow
    Write-Host "    Solution recommandée:" -ForegroundColor Yellow
    Write-Host "    1. Installer Poedit: https://poedit.net/" -ForegroundColor White
    Write-Host "    2. Ouvrir les fichiers .po avec Poedit et sauvegarder" -ForegroundColor White
    Write-Host "    OU" -ForegroundColor Yellow
    Write-Host "    1. Installer gettext: https://mlocati.github.io/articles/gettext-iconv-windows.html" -ForegroundColor White
    Write-Host "    2. Relancer ce script" -ForegroundColor White
}

Write-Host ""
Write-Host "Appuyez sur une touche pour quitter..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
