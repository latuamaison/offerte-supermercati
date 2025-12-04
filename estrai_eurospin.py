# estrai_eurospin.py - Versione universale (Windows + Linux/GitHub)
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

def main():
    print("Avvio estrazione Eurospin...")
    print(f"Sistema operativo: {platform.system()}")
    
    # Configura Chrome
    chrome_options = Options()
    chrome_options.add_argument("--headless")
    chrome_options.add_argument("--disable-notifications")
    chrome_options.add_argument("--disable-popup-blocking")
    chrome_options.add_argument("--no-sandbox")
    chrome_options.add_argument("--disable-dev-shm-usage")
    chrome_options.add_argument("--window-size=1920,1080")
    
    # Configurazione per ambiente
    if platform.system() == "Windows":
        # Windows: usa chromedriver.exe locale
        driver_path = os.path.join(os.getcwd(), 'chromedriver.exe')
        service = Service(driver_path)
        driver = webdriver.Chrome(service=service, options=chrome_options)
    else:
        # Linux (GitHub Actions): usa ChromeDriverManager
        try:
            from webdriver_manager.chrome import ChromeDriverManager
            from selenium.webdriver.chrome.service import Service as ChromeService
            service = ChromeService(ChromeDriverManager().install())
            driver = webdriver.Chrome(service=service, options=chrome_options)
        except ImportError:
            print("ERRORE: webdriver-manager non installato. Usa 'pip install webdriver-manager'")
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
        return False
    finally:
        driver.quit()

if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)