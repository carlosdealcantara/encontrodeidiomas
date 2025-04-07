from http.server import HTTPServer, SimpleHTTPRequestHandler
import os
import sys

PORT = 8000

class CustomHandler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=".", **kwargs)
    
    def log_message(self, format, *args):
        print(format % args)

def run_server():
    server_address = ('', PORT)
    httpd = HTTPServer(server_address, CustomHandler)
    print(f"Servidor iniciado em http://localhost:{PORT}")
    print(f"Diretório atual: {os.getcwd()}")
    print("Pressione Ctrl+C para encerrar o servidor")
    
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\nServidor encerrado.")
        httpd.server_close()
        sys.exit(0)

if __name__ == "__main__":
    run_server() 