<?php
// trigger_eurospin.php - Script per aggiornare Eurospin su Firebase
header('Content-Type: text/plain; charset=utf-8');
echo "=== AGGIORNAMENTO PROMOZIONI EUROSPIN ===\n";

// CONFIGURAZIONE - MODIFICA 2: PERCORSI RELATIVI
$cartella_progetto = __DIR__;  // Usa automaticamente la cartella dello script
$script_python = $cartella_progetto . '/estrai_eurospin.py';
$url_firebase = 'https://offerte-spesa-default-rtdb.europe-west1.firebasedatabase.app/promozioni/eurospin.json';

echo "Percorso: $cartella_progetto\n";
echo "Firebase: $url_firebase\n\n";

// 1. Vai nella cartella
if (!chdir($cartella_progetto)) {
    echo "❌ ERRORE: Cartella non trovata\n";
    exit(1);
}

// 2. Esegui Python
echo ">> [1] Esecuzione script Python...\n";
// Se 'python' non funziona, cambia in 'py'
$comando = 'python "' . $script_python . '" 2>&1';

exec($comando, $output, $return_code);

foreach ($output as $riga) {
    echo "   " . $riga . "\n";
}

if ($return_code !== 0) {
    echo "\n❌ Python fallito (Codice: $return_code)\n";
    echo "   Prova a cambiare 'python' in 'py' alla riga 24 e riesegui\n";
    exit(1);
}

echo "\n✅ Python completato.\n";

// 3. Cerca il JSON
$file_json = $cartella_progetto . '/promozioni_eurospin.json';
if (!file_exists($file_json)) {
    echo "❌ File JSON non creato\n";
    exit(1);
}

echo "✅ JSON trovato.\n";

// 4. Leggi JSON
$contenuto_json = file_get_contents($file_json);
if ($contenuto_json === false) {
    echo "❌ Errore lettura JSON\n";
    exit(1);
}

// 5. Invia a Firebase CON CONFIGURAZIONE SSL FLESSIBILE
echo "\n>> [2] Invio a Firebase...\n";

$ch = curl_init($url_firebase);

// MODIFICA 3: CONFIGURAZIONE SSL INTELLIGENTE
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Verifica certificati SSL

// Gestione intelligente del certificato CA
$ca_cert_path = __DIR__ . '/cacert.pem';
if (file_exists($ca_cert_path)) {
    // Usa cacert.pem locale se esiste (per test locale)
    curl_setopt($ch, CURLOPT_CAINFO, $ca_cert_path);
    echo "   Usando certificato locale: $ca_cert_path\n";
} else {
    // Su server (GitHub Actions) usa i certificati di sistema
    echo "   Usando certificati di sistema\n";
}

curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_POSTFIELDS, $contenuto_json);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($contenuto_json)
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout di 30 secondi

$risposta = curl_exec($ch);
$codice_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_errore = curl_error($ch);
curl_close($ch);

if ($codice_http === 200) {
    echo "✅ SUCCESSO! Firebase aggiornato.\n";
    
    // Statistiche
    $dati = json_decode($contenuto_json, true);
    $categorie = count($dati);
    $prodotti_totali = 0;
    foreach ($dati as $categoria) {
        $prodotti_totali += count($categoria['prodotti']);
    }
    echo "   📊 Statistiche: $categorie categorie, $prodotti_totali prodotti\n";
    echo "   📍 Percorso: promozioni/eurospin\n";
    
} else {
    echo "❌ ERRORE nell'invio a Firebase.\n";
    echo "   Codice HTTP: $codice_http\n";
    if ($curl_errore) {
        echo "   Errore cURL: $curl_errore\n";
    }
    echo "   Risposta Firebase: " . ($risposta ?: "(vuota)") . "\n";
    
    // Consigli per errori specifici
    if ($codice_http === 401) {
        echo "\n🔐 SUGGERIMENTO: Errore 401 = permesso negato.\n";
        echo "   Vai su Firebase Console > Realtime Database > Regole\n";
        echo "   Assicurati che 'eurospin' abbia permessi di scrittura:\n";
        echo "   {\n";
        echo "     \"rules\": {\n";
        echo "       \"promozioni\": {\n";
        echo "         \"eurospin\": {\n";
        echo "           \".read\": true,\n";
        echo "           \".write\": true\n";
        echo "         }\n";
        echo "       }\n";
        echo "     }\n";
        echo "   }\n";
    } elseif ($codice_http === 0) {
        echo "\n🌐 SUGGERIMENTO: Codice 0 = errore di connessione/SSL.\n";
        echo "   1. Verifica che il file cacert.pem esista nella cartella\n";
        echo "   2. Controlla la connessione a Internet\n";
        echo "   3. Prova a disabilitare temporaneamente firewall/antivirus\n";
    }
    exit(1);
}

// 6. Opzionale: pulisci file JSON dopo l'invio
// unlink($file_json);
// echo "\n🗑️ File JSON temporaneo cancellato.\n";

echo "\n=== OPERAZIONE COMPLETATA CON SUCCESSO ===\n";
?>