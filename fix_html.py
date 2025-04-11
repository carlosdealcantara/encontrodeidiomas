with open('equipe.html', 'r', encoding='utf-8', errors='ignore') as f:
    content = f.read()

# Remove any spaces after the closing HTML tag
content = content.rstrip()
if not content.endswith('</html>'):
    content = content[:content.rfind('</html>')] + '</html>'

# Write back the file
with open('equipe.html', 'w', encoding='utf-8') as f:
    f.write(content)

print('File updated') 