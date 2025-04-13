import os
import http.server
import socketserver
import threading
import time
import webbrowser
import sys
from urllib.request import urlopen
from urllib.error import URLError, HTTPError
from html.parser import HTMLParser

class LinkParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.links = []

    def handle_starttag(self, tag, attrs):
        if tag in ['a', 'link', 'script', 'img']:
            for attr in attrs:
                if attr[0] in ['href', 'src']:
                    self.links.append(attr[1])

def start_server(port=8000):
    Handler = http.server.SimpleHTTPRequestHandler
    httpd = socketserver.TCPServer(("", port), Handler)
    
    print(f"Servidor iniciado na porta {port}")
    server_thread = threading.Thread(target=httpd.serve_forever)
    server_thread.daemon = True
    server_thread.start()
    
    return httpd

def check_link(base_url, link):
    if link.startswith('http'):
        url = link
    elif link.startswith('#') or link.startswith('javascript:') or link == '':
        return True, f"Ignorando link interno: {link}"
    else:
        url = f"{base_url}/{link.lstrip('/')}"
    
    try:
        response = urlopen(url)
        return True, f"✅ {url} - OK ({response.status})"
    except HTTPError as e:
        return False, f"❌ {url} - Erro HTTP: {e.code}"
    except URLError as e:
        return False, f"❌ {url} - Erro de URL: {e.reason}"
    except Exception as e:
        return False, f"❌ {url} - Erro desconhecido: {str(e)}"

def get_links_from_page(url):
    try:
        response = urlopen(url)
        content = response.read().decode('utf-8')
        parser = LinkParser()
        parser.feed(content)
        return parser.links
    except Exception as e:
        print(f"Erro ao obter links da página {url}: {str(e)}")
        return []

def check_site(base_url, pages):
    all_ok = True
    checked_links = set()
    
    for page in pages:
        page_url = f"{base_url}/{page.lstrip('/')}"
        print(f"\nVerificando página: {page_url}")
        
        try:
            links = get_links_from_page(page_url)
            print(f"Encontrados {len(links)} links")
            
            for link in links:
                if link in checked_links:
                    continue
                
                checked_links.add(link)
                success, message = check_link(base_url, link)
                print(message)
                
                if not success:
                    all_ok = False
        except Exception as e:
            print(f"❌ Erro ao verificar página {page_url}: {str(e)}")
            all_ok = False
    
    return all_ok

def main():
    port = 8000
    base_url = f"http://localhost:{port}"
    
    # Páginas principais a verificar
    main_pages = [
        "",  # Raiz (index.html)
        "index.html",
        "online.html",
        "equipe.html",
        "contato.html",
        "links.html"
    ]
    
    # Páginas do dlaurojoias
    dlauro_pages = [
        "dlaurojoias/",
        "dlaurojoias/index.html",
        "dlaurojoias/aneis.html",
    ]
    
    # Inicia o servidor local
    httpd = start_server(port)
    time.sleep(1)  # Espera o servidor iniciar
    
    try:
        print("\n==== Verificando site principal ====")
        main_ok = check_site(base_url, main_pages)
        
        print("\n==== Verificando D'Lauro Joias ====")
        dlauro_ok = check_site(base_url, dlauro_pages)
        
        # Abre o navegador para inspeção visual
        webbrowser.open(base_url)
        webbrowser.open(f"{base_url}/dlaurojoias/")
        
        print("\n==== Relatório Final ====")
        print(f"Site principal: {'✅ OK' if main_ok else '❌ Problemas encontrados'}")
        print(f"D'Lauro Joias: {'✅ OK' if dlauro_ok else '❌ Problemas encontrados'}")
        
        input("Pressione Enter para encerrar o servidor...")
    finally:
        httpd.shutdown()
        print("Servidor encerrado")

if __name__ == "__main__":
    main() 