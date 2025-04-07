from http.server import SimpleHTTPRequestHandler, HTTPServer

# Define a porta que vamos usar
PORT = 3000

# Cria um handler HTTP simples
handler = SimpleHTTPRequestHandler

# Cria e inicia o servidor
with HTTPServer(("", PORT), handler) as httpd:
    print(f"Servidor rodando na porta {PORT}")
    print(f"Acesse: http://localhost:{PORT}")
    # Mantém o servidor em execução
    httpd.serve_forever() 