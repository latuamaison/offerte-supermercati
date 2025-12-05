<?php
// trigger_eurospin.php - Script per aggiornare Eurospin su Firebase
header('Content-Type: text/plain; charset=utf-8');
echo "=== AGGIORNAMENTO PROMOZIONI EUROSPIN ===\n";

// CONFIGURAZIONE SICURA
$cartella_progetto = __DIR__;
$script_python = $cartella_progetto . '/estrai_eurospin.py';

// ============================================================================
// 1. OTTENIMENTO DEL DATABASE SECRET DI FIREBASE
// ============================================================================

$chiave_segreta = getenv('FIREBASE_SECRET');

// Se stiamo testando LOCALMENTE: usa file firebase_secret.txt
if (empty($chiave_segreta)) {
    $secret_file = __DIR__ . '/firebase_secret.txt';
    
    if (file_exists($secret_file)) {
        $chiave_segreta = trim(file_get_contents($secret_file));
        echo "ℹ️  MODALITÀ LOCALE: usando Database Secret da file\n";
    } else {
        echo "❌ ERRORE: firebase_secret.txt non trovato localmente\n";
        echo "   Per test locale, crea il file con il tuo Database Secret\n";
        exit(1);
    }
}

// Controllo finale che la chiave esista
if (empty($chiave_segreta)) {
    echo "❌ ERRORE CRITICO: Database Secret non configurato!\n";
    echo "   Su GitHub: imposta FIREBASE_SECRET in Settings > Secrets > Actions\n";
    echo "   Localmente: crea file firebase_secret.txt con il Database Secret\n";
    exit(1);
}

// ============================================================================
// 2. COSTRUZIONE URL FIREBASE CON AUTENTICAZIONE
// ============================================================================

$url_firebase = 'https://offerte-spesa-default-rtdb.europe-west1.firebasedatabase.app/promozioni/eurospin.json?auth=' . urlencode($chiave_segreta);

echo "Percorso: $cartella_progetto\n";
echo "Firebase: Database configurato correttamente\n";
echo "Secret: " . substr($chiave_segreta, 0, 8) . "... (" . strlen($chiave_segreta) . " caratteri)\n\n";

// ============================================================================
// 3. ESEGUI SCRIPT PYTHON (CROSS-PLATFORM)
// ============================================================================

if (!chdir($cartella_progetto)) {
    echo "❌ ERRORE: Cartella non trovata\n";
    exit(1);
}

echo ">> [1] Esecuzione script Python...\n";

// Rileva sistema operativo per compatibilità Windows/Linux
$sistema_operativo = strtoupper(PHP_OS_FAMILY);

if ($sistema_operativo === 'WINDOWS' || $sistema_operativo === 'WINNT') {
    // Windows: usa 'python' o 'py'
    $comando = 'python "' . $script_python . '" 2>&1';
    echo "   Sistema: Windows (usa 'python')\n";
} else {
    // Linux/macOS (GitHub Actions): usa 'python3'
    $comando = 'python3 "' . $script_python . '" 2>&1';
    echo "   Sistema: Linux/macOS (usa 'python3')\n";
}

exec($comando, $output, $return_code);

foreach ($output as $riga) {
    echo "   " . $riga . "\n";
}

if ($return_code !== 0) {
    echo "\n❌ Python fallito (Codice: $return_code)\n";
    
    // Diagnostica aggiuntiva
    if ($sistema_operativo === 'WINDOWS' || $sistema_operativo === 'WINNT') {
        echo "   Su Windows, prova a cambiare 'python' in 'py' e riesegui\n";
    } else {
        echo "   Su Linux, assicurati che python3 sia installato\n";
    }
    
    exit(1);
}

echo "\n✅ Python completato.\n";

// ============================================================================
// 4. VERIFICA FILE JSON
// ============================================================================

$file_json = $cartella_progetto . '/promozioni_eurospin.json';
if (!file_exists($file_json)) {
    echo "❌ File JSON non creato\n";
    exit(1);
}

echo "✅ JSON trovato.\n";

$contenuto_json = file_get_contents($file_json);
if ($contenuto_json === false) {
    echo "❌ Errore lettura JSON\n";
    exit(1);
}

// ============================================================================
// 5. INVIO A FIREBASE CON SSL INTELLIGENTE
// ============================================================================

echo "\n>> [2] Invio a Firebase...\n";

$ch = curl_init($url_firebase);

// Configurazione SSL intelligente
$ca_cert_path = __DIR__ . '/cacert.pem';

if (file_exists($ca_cert_path)) {
    // Usa cacert.pem locale se esiste (per test locale)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_CAINFO, $ca_cert_path);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    echo "   ✅ Usando certificato SSL locale: cacert.pem\n";
} else {
    // Su server (GitHub Actions) usa i certificati di sistema
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    echo "   ℹ️  Usando certificati di sistema\n";
}

// Configurazione richiesta
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_POSTFIELDS, $contenuto_json);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($contenuto_json)
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

$risposta = curl_exec($ch);
$codice_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_errore = curl_error($ch);
curl_close($ch);

// ============================================================================
// 6. ANALISI RISPOSTA
// ============================================================================

if ($codice_http === 200) {
    echo "✅ SUCCESSO! Firebase aggiornato.\n";
    
    // Statistiche
    $dati = json_decode($contenuto_json, true);
    if (is_array($dati)) {
        $categorie = count($dati);
        $prodotti_totali = 0;
        foreach ($dati as $categoria) {
            if (isset($categoria['prodotti']) && is_array($categoria['prodotti'])) {
                $prodotti_totali += count($categoria['prodotti']);
            }
        }
        echo "   📊 Statistiche: $categorie categorie, $prodotti_totali prodotti\n";
    }
    echo "   📍 Percorso: promozioni/eurospin\n";
    
} else {
    echo "❌ ERRORE nell'invio a Firebase.\n";
    echo "   Codice HTTP: $codice_http\n";
    
    if ($curl_errore) {
        echo "   Errore cURL: $curl_errore\n";
    }
    
    if ($risposta) {
        echo "   Risposta Firebase: $risposta\n";
    }
    
    // Diagnostica errori comuni
    if ($codice_http === 401) {
        echo "\n🔐 ERRORE 401: Autenticazione fallita\n";
        echo "   Controlla:\n";
        echo "   1. Database Secret in firebase_secret.txt (locale) o FIREBASE_SECRET (GitHub)\n";
        echo "   2. Regole Firebase: Imposta temporaneamente '.write': true\n";
        echo "   3. URL Firebase: Controlla il percorso e il progetto\n";
        
    } elseif ($codice_http === 403) {
        echo "\n🔒 ERRORE 403: Permessi negati\n";
        echo "   MODIFICA LE REGOLE FIREBASE:\n";
        echo "   1. Vai su Firebase Console > Database > Realtime Database > Rules\n";
        echo "   2. Imposta temporaneamente:\n";
        echo "      {\n";
        echo "        \"rules\": {\n";
        echo "          \".read\": true,\n";
        echo "          \".write\": true\n";
        echo "        }\n";
        echo "      }\n";
        echo "   3. Clicca 'Publish'\n";
        echo "   4. Riavvia lo script\n";
        
    } elseif ($codice_http === 0) {
        echo "\n🌐 ERRORE: Connessione fallita\n";
        echo "   Verifica:\n";
        echo "   1. Connessione internet\n";
        echo "   2. Firewall/antivirus non blocca cURL\n";
        echo "   3. File cacert.pem presente per test locale\n";
        
    } else {
        echo "\n⚠️  Codice HTTP non riconosciuto\n";
        echo "   Controlla la console Firebase per altri errori\n";
    }
    exit(1);
}

// ============================================================================
// 7. PULIZIA (OPZIONALE)
// ============================================================================

// Opzionale: cancella il file JSON dopo l'invio
// if (file_exists($file_json)) {
//     unlink($file_json);
//     echo "🗑️  File JSON temporaneo eliminato\n";
// }

echo "\n=== OPERAZIONE COMPLETATA CON SUCCESSO ===\n";
?>
