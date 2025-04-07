from fastapi import FastAPI, Request
from fastapi.responses import HTMLResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
import uvicorn
import os
from pathlib import Path
from dotenv import load_dotenv

# Load environment variables from .env file if it exists
load_dotenv()

# Crie a aplicação FastAPI
app = FastAPI(title="Encontro de Idiomas")

# Configure os diretórios estáticos e templates
BASE_DIR = Path(__file__).parent
static_path = BASE_DIR / "static"
templates_path = BASE_DIR / "templates"

app.mount("/static", StaticFiles(directory=str(static_path)), name="static")
templates = Jinja2Templates(directory=str(templates_path))

@app.get("/", response_class=HTMLResponse)
async def get_home(request: Request):
    """Render the main page."""
    return templates.TemplateResponse(
        "index.html",
        {"request": request}
    )

@app.get("/health", status_code=200)
async def health_check():
    """Health check endpoint for monitoring."""
    return {"status": "healthy"}

if __name__ == "__main__":
    # Execute diretamente com uvicorn para garantir que funcione
    import uvicorn
    uvicorn.run("app:app", host="0.0.0.0", port=8000, reload=True)

# For production with SSL
# uvicorn.run(app, host="0.0.0.0", port=443, ssl_keyfile="key.pem", ssl_certfile="cert.pem") 