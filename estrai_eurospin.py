# estrai_eurospin.py - Versione universale (Windows + Linux/GitHub) FIXED
import time, json, sys, os, platform
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from urllib.parse import urljoin

BASE_URL = "https://www.eurospin.it/promozioni/"
OUTPUT_FILE = "promozioni_eurospin.json"
SCROLL_PAUSE = 1.5
CATEGORY_WAIT_SECONDS = 2
DELAY_BETWEEN_CATEGORIES = 3

def setup_driver():
    """Configura ChromeDriver automaticamente per Windows e Linux"""
    print("   Configurazione ChromeDriver...")
    
    # Configura Chrome
    chrome_options = Options()
    chrome_options.add_argument("--headless")
    chrome_options.add_argument("--disable-notifications")
    chrome_options.add_argument("--disable-popup-blocking")
    chrome_options.add_argument("--no-sandbox")
    chrome_options.add_argument("--disable-dev-shm-usage")
    chrome_options.add_argument("--window-size=1920,1080")
    chrome_options.add_argument("--disable-blink-features=AutomationControlled")
    chrome_options.add_experimental_option("excludeSwitches", ["enable-automation"])
    chrome_options.add_experimental_option('useAutomationExtension', False)
    
    # User agent realistico
    if platform.system() == "Windows":
        chrome_options.add_argument('user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
    else:
        chrome_options.add_argument('user-agent=Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36')
    
    try:
        # TENTATIVO 1: Usa webdriver-manager per entrambi i sistemi
        from webdriver_manager.chrome import ChromeDriverManager
        from selenium.webdriver.chrome.service import Service as ChromeService
        
        print("   Usando webdriver-manager (ChromeDriver automatico)...")
        service = ChromeService(ChromeDriverManager().install())
        driver = webdriver.Chrome(service=service, options=chrome_options)
        return driver
        
    except ImportError:
        print("   ⚠️  webdriver-manager non installato. Installalo con: pip install webdriver-manager")
        
    except Exception as e:
        print(f"   ⚠️  Errore webdriver-manager: {str(e)[:100]}")
    
    # TENTATIVO 2: Fallback per Windows (chromedriver.exe locale)
    try:
        if platform.system() == "Windows":
            print("   Tentativo fallback Windows...")
            driver_path = os.path.join(os.getcwd(), 'chromedriver.exe')
            if os.path.exists(driver_path):
                service = Service(driver_path)
                driver = webdriver.Chrome(service=service, options=chrome_options)
                return driver
            else:
                print("   ❌ chromedriver.exe non trovato nella cartella")
    except Exception as e:
        print(f"   ⚠️  Errore fallback Windows: {str(e)[:100]}")
    
    # TENTATIVO 3: ChromeDriver nella PATH di sistema
    try:
        print("   Tentativo ChromeDriver system PATH...")
        driver = webdriver.Chrome(options=chrome_options)
        return driver
    except Exception as e:
        print(f"   ❌ Tutti i metodi falliti: {str(e)[:100]}")
        return None

def main():
    print("Avvio estrazione Eurospin...")
    print(f"Sistema operativo: {platform.system()}")
    
    # Setup driver
    driver = setup_driver()
    if not driver:
        print("❌ Impossibile configurare ChromeDriver")
        print("   Soluzioni:")
        print("   1. Installa webdriver-manager: pip install webdriver-manager")
        print("   2. Scarica chromedriver.exe e mettilo in questa cartella")
        return False
    
    try:
        driver.get(BASE_URL)
        time.sleep(3)
        
        # Accetta cookie
        try:
            accetta_btn = WebDriverWait(driver, 10).until(
                EC.element_to_be_clickable((By.XPATH, "//button[contains(.,'Accetta tutto')]"))
            )
            accetta_btn.click()
            print("Cookie accettati")
        except:
            print("Nessun popup cookie")
        
        # Scroll iniziale
        for _ in range(4):
            driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
            time.sleep(SCROLL_PAUSE)
        
        # Trova categorie
        js = """
        const links = [];
        document.querySelectorAll('a').forEach(a => {
            const href = a.href || a.getAttribute('href');
            if (href && href.includes('category_filter=')) {
                links.push({text: a.innerText.trim(), href: href});
            }
        });
        return links;
        """
        raw_links = driver.execute_script(js)
        
        # Rimuovi duplicati
        seen = set()
        category_links = []
        for item in raw_links:
            href = item.get("href", "").strip()
            if href and href not in seen:
                seen.add(href)
                href = urljoin(BASE_URL, href)
                text = item.get("text", "").strip()
                category_links.append({"nome": text or "Categoria", "url": href})
        
        print(f"Trovate {len(category_links)} categorie")
        
        # Funzioni helper
        def full_scroll():
            last = driver.execute_script("return document.body.scrollHeight")
            while True:
                driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
                time.sleep(SCROLL_PAUSE)
                new = driver.execute_script("return document.body.scrollHeight")
                if new == last: break
                last = new
        
        def estrai_prodotti():
            prodotti = []
            items = driver.find_elements(By.CSS_SELECTOR, "a.sn_promo_grid_item")
            for p in items:
                try:
                    nome = p.find_element(By.CSS_SELECTOR, ".i_title").text.strip()
                except:
                    continue
                
                try: brand = p.find_element(By.CSS_SELECTOR, ".i_brand").text.strip()
                except: brand = ""
                try: prezzo = p.find_element(By.CSS_SELECTOR, ".i_price i[itemprop='price']").text.strip()
                except: prezzo = ""
                try: immagine = p.find_element(By.CSS_SELECTOR, "img.i_image").get_attribute("src")
                except: immagine = ""
                try: periodo = p.find_element(By.CSS_SELECTOR, ".date_current_promo").text.strip()
                except: periodo = ""
                try: link = p.get_attribute("href") or ""
                except: link = ""
                
                prodotti.append({
                    "nome": nome, "brand": brand, "prezzo": prezzo,
                    "immagine": immagine, "periodo": periodo, "link": link,
                    "supermercato": "Eurospin"
                })
            return prodotti
        
        # Estrazione
        output = []
        for idx, cat in enumerate(category_links, 1):
            print(f"Categoria {idx}: {cat['nome']}")
            try:
                driver.get(cat['url'])
                time.sleep(CATEGORY_WAIT_SECONDS)
                full_scroll()
                prodotti = estrai_prodotti()
                output.append({"nome": cat['nome'], "url": cat['url'], "prodotti": prodotti})
                print(f"  {len(prodotti)} prodotti")
            except Exception as e:
                print(f"  Errore: {e}")
                output.append({"nome": cat['nome'], "url": cat['url'], "prodotti": []})
            
            if idx < len(category_links):
                time.sleep(DELAY_BETWEEN_CATEGORIES)
        
        # Salva JSON
        with open(OUTPUT_FILE, "w", encoding="utf-8") as f:
            json.dump(output, f, ensure_ascii=False, indent=2)
        
        tot_prod = sum(len(c["prodotti"]) for c in output)
        print(f"\nEstrazione completata: {len(output)} categorie, {tot_prod} prodotti")
        return True
        
    except Exception as e:
        print(f"Errore durante l'estrazione: {e}")
        import traceback
        traceback.print_exc()
        return False
    finally:
        driver.quit()

if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)
