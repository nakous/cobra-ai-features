<?php
/**
 * Script de recompilation rapide du .mo
 * À exécuter après chaque modification du .po
 */

$po_file = __DIR__ . '/fr_FR.po';
$mo_file = __DIR__ . '/cobra-ai-fr_FR.mo';

// Parser le .po
$entries = [];
$current = null;
$in_msgid = false;
$in_msgstr = false;

$lines = file($po_file, FILE_IGNORE_NEW_LINES);
foreach ($lines as $line) {
    if (empty($line) || $line[0] === '#') {
        if ($current && !empty($current['msgid']) && !empty($current['msgstr'])) {
            $entries[] = $current;
            $current = null;
        }
        continue;
    }
    
    if (preg_match('/^msgid\s+"(.*)"\s*$/', $line, $matches)) {
        if ($current && !empty($current['msgid']) && !empty($current['msgstr'])) {
            $entries[] = $current;
        }
        $current = ['msgid' => $matches[1], 'msgstr' => ''];
        $in_msgid = true;
        $in_msgstr = false;
    }
    elseif (preg_match('/^msgstr\s+"(.*)"\s*$/', $line, $matches)) {
        if ($current) {
            $current['msgstr'] = $matches[1];
        }
        $in_msgid = false;
        $in_msgstr = true;
    }
    elseif (preg_match('/^"(.*)"\s*$/', $line, $matches)) {
        if ($in_msgid && $current) {
            $current['msgid'] .= $matches[1];
        } elseif ($in_msgstr && $current) {
            $current['msgstr'] .= $matches[1];
        }
    }
}

if ($current && !empty($current['msgid']) && !empty($current['msgstr'])) {
    $entries[] = $current;
}

// Générer le .mo
function write_mo_file($entries, $output_file) {
    $offsets = [];
    $ids = '';
    $strings = '';
    
    usort($entries, function($a, $b) {
        return strcmp($a['msgid'], $b['msgid']);
    });
    
    foreach ($entries as $entry) {
        $id = $entry['msgid'];
        $str = $entry['msgstr'];
        
        $offsets[] = [strlen($ids), strlen($id)];
        $ids .= $id . "\0";
        
        $offsets[] = [strlen($strings), strlen($str)];
        $strings .= $str . "\0";
    }
    
    $keyoffsets = [];
    $valueoffsets = [];
    for ($i = 0; $i < count($offsets); $i += 2) {
        $keyoffsets[] = $offsets[$i];
        $valueoffsets[] = $offsets[$i + 1];
    }
    
    $count = count($keyoffsets);
    $magic = 0x950412de;
    $revision = 0;
    
    $idsoffset = 28;
    $stringsoffset = $idsoffset + ($count * 8);
    $idsindex = $stringsoffset + ($count * 8);
    $stringsindex = $idsindex + strlen($ids);
    
    $fp = fopen($output_file, 'wb');
    
    fwrite($fp, pack('V', $magic));
    fwrite($fp, pack('V', $revision));
    fwrite($fp, pack('V', $count));
    fwrite($fp, pack('V', $idsoffset));
    fwrite($fp, pack('V', $stringsoffset));
    fwrite($fp, pack('V', 0));
    fwrite($fp, pack('V', 0));
    
    foreach ($keyoffsets as $offset) {
        fwrite($fp, pack('V', $offset[1]));
        fwrite($fp, pack('V', $idsindex + $offset[0]));
    }
    
    foreach ($valueoffsets as $offset) {
        fwrite($fp, pack('V', $offset[1]));
        fwrite($fp, pack('V', $stringsindex + $offset[0]));
    }
    
    fwrite($fp, $ids);
    fwrite($fp, $strings);
    
    fclose($fp);
}

write_mo_file($entries, $mo_file);

echo "✅ Compilé : " . count($entries) . " entrées → " . filesize($mo_file) . " octets\n";
