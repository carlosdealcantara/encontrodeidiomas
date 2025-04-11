import re

with open('equipe.html', 'r', encoding='utf-8', errors='ignore') as f:
    content = f.read()

# Fix common encoding issues with accented characters
replacements = {
    'Sobre NÃ³s': 'Sobre Nós',
    'Links RÃ¡pidos': 'Links Rápidos',
    'experiÃªncia': 'experiência',
    'Ãºnica': 'única',
    'informaÃ§Ã£o': 'informação',
    'InformaÃ§Ãµes': 'Informações',
    'nÃ£o': 'não',
    'Ã©': 'é'
}

for old, new in replacements.items():
    content = content.replace(old, new)

with open('equipe.html', 'w', encoding='utf-8') as f:
    f.write(content)

print('Encoding fixed') 