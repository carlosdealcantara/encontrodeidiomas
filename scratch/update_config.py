import json

with open('scratch/mentoria_config.json', 'r', encoding='utf-8') as f:
    config = json.load(f)

if 'templates' not in config:
    config['templates'] = {}

# Update the Class Aviso template
config['templates']['class_aviso'] = """📅 {date}

We have a session scheduled for {horario}.
If you want to participate, please reply with !attend.

⏳ Deadline to confirm your attendance: {deadline}."""

with open('scratch/mentoria_config.json', 'w', encoding='utf-8') as f:
    json.dump(config, f, indent=4, ensure_ascii=False)
