# Eurospin Offers Scraper & Firebase Sync

Automazione per estrarre offerte Eurospin e sincronizzarle su Firebase Realtime Database.

## 📋 Funzionalità
- Web scraping automatico offerte Eurospin
- Estrazione 20+ categorie e 200+ prodotti
- Sincronizzazione con Firebase Realtime Database
- Automazione GitHub Actions ogni 4 ore
- Notifiche in caso di errore

## 🛠️ Installazione Locale

### Prerequisiti
- Python 3.11+
- PHP 8.1+
- Google Chrome
- Database Secret Firebase

### Setup
1. Clona repository
2. Crea `firebase_secret.txt` con il Database Secret
3. Installa dipendenze: `pip install -r requirements.txt`
4. Scarica certificati SSL: https://curl.se/ca/cacert.pem
5. Esegui: `php trigger_eurospin.php`

## 🔧 Configurazione GitHub Actions
1. Nel repository GitHub: Settings → Secrets and variables → Actions
2. Aggiungi segreto: `FIREBASE_SECRET` = (tuo Database Secret)
3. Il workflow si avvierà automaticamente ogni 4 ore

## 📁 Struttura File
- `trigger_eurospin.php` - Script principale PHP
- `estrai_eurospin.py` - Script scraping Python
- `.github/workflows/update_offers.yml` - Automazione GitHub
- `requirements.txt` - Dipendenze Python

## ⚠️ File .gitignore
I seguenti file NON vengono committati su GitHub:
- `firebase_secret.txt`
- `cacert.pem`
- `promozioni_eurospin.json`
- `chromedriver.exe`

## 🔗 Link Utili
- Firebase Console: https://console.firebase.google.com/
- GitHub Actions: https://github.com/(tuo-user)/(repo)/actions