import http.server
import socketserver
import os
import sys

PORT = 5000

class MyHandler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=os.path.dirname(os.path.abspath(__file__)), **kwargs)

def run_server():
    try:
        print(f"Iniciando servidor na porta {PORT}...")
        print(f"Acesse: http://localhost:{PORT}")
        print(f"Diretório atual: {os.path.dirname(os.path.abspath(__file__))}")
        print("Pressione Ctrl+C para encerrar o servidor")
        
        with socketserver.TCPServer(("", PORT), MyHandler) as httpd:
            print("Servidor rodando...")
            httpd.serve_forever()
    except KeyboardInterrupt:
        print("\nServidor encerrado.")
        sys.exit(0)
    except OSError as e:
        if e.errno == 10048:
            print(f"ERRO: Porta {PORT} já está em uso. Tente outra porta.")
            sys.exit(1)
        else:
            raise

if __name__ == "__main__":
    run_server() 