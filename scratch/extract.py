import re
html = open('/app/error_page.html', encoding='utf-8', errors='ignore').read()
inputs = re.findall(r'<input[^>]*>', html)
print("--- INPUTS FOUND ---")
for inp in inputs:
    print(inp)
print("--- TEXTAREAS FOUND ---")
textareas = re.findall(r'<textarea[^>]*>', html)
for ta in textareas:
    print(ta)
print("--- BUTTONS FOUND ---")
buttons = re.findall(r'<button[^>]*>.*?</button>', html, re.DOTALL)
for btn in buttons:
    print(re.sub(r'\s+', ' ', btn[:200])) # print up to 200 chars
