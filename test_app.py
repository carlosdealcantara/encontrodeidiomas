from fastapi import FastAPI, Request
from fastapi.responses import HTMLResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
import uvicorn
from pathlib import Path

# Crie a aplicação FastAPI
app = FastAPI(title="Encontro de Idiomas - Teste")

# Configure os diretórios estáticos e templates
BASE_DIR = Path(__file__).parent
static_path = BASE_DIR / "static"
templates_path = BASE_DIR / "templates"

app.mount("/static", StaticFiles(directory=str(static_path)), name="static")
templates = Jinja2Templates(directory=str(templates_path))

@app.get("/", response_class=HTMLResponse)
async def home(request: Request):
    """Página inicial."""
    return templates.TemplateResponse(
        "index.html",
        {"request": request}
    )

@app.get("/test")
async def test():
    """Endpoint de teste."""
    return {"status": "ok", "message": "API funcionando!"}

if __name__ == "__main__":
    # Iniciar o servidor
    uvicorn.run("test_app:app", host="0.0.0.0", port=8000, reload=True) 