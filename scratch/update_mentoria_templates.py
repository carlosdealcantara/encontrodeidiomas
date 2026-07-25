import json

config = {
  "admin_jid": "556192666148@s.whatsapp.net",
  "groups": {
    "our_classes": {
      "jid": "120363228807801778@g.us",
      "automations": ["lembrete_aula"]
    },
    "desafio": {
      "jid": "120363246518434750@g.us",
      "automations": ["auto_kick", "aviso"]
    },
    "the_lounge": {
      "jid": "120363248224789462@g.us",
      "automations": ["welcome", "ranking_geral"]
    },
    "pronunciation": {
      "jid": "120363252165108369@g.us",
      "automations": ["ranking"]
    },
    "music": {
      "jid": "120363230596474380@g.us",
      "automations": ["ranking"]
    },
    "vocabulary": {
      "jid": "120363322452180439@g.us",
      "automations": ["ranking"]
    },
    "games": {
      "jid": "120363417627129972@g.us",
      "automations": ["ranking"]
    }
  },
  "templates": {
    "welcome": "Hey, @{name}! \U0001f44b\nWelcome to *The Lounge*! \U0001f389\nIntroduce yourself to the group!",
    "lembrete_aula": "",
    "aviso_desafio": "\u26a0\ufe0f *Challenge Alert!*\n\n{pendentes} You have until midnight to post an image of your activity! \u23f3",
    "kick_desafio": "\u26a0\ufe0f @{name} has been removed for missing the daily activity.",
    "ranking_social": "",
    "ranking_dedicados": "\U0001f4c5 {date}\n\n\u2b50 *Student of the Day*\n{student_of_the_day}\n\n*Honorable Mentions:*\n{other_participants}\n\n\U0001f4d6 *Legend:*\n{legend}\n\n\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\n\n\U0001f5e3\ufe0f *Word Slingers of the day:*\n{word_slingers_list}\n\U0001f525 *Emoji Gang:*\n{emoji_gang_list}",
    "class_aviso": "\U0001f468\u200d\U0001f3eb *Teacher Class \u2014 {date}*\n\nWe have a class with the teacher scheduled for *{horario}*.\nIf you want to participate, please reply with `!attend`.\n\n\u23f3 Deadline to confirm: *{deadline}*.",
    "class_cancel": "\u274c *Teacher Class Cancelled*\n\nUnfortunately, we didn't get any confirmations for the {horario} class today. See you next time! \U0001f44b",
    "class_kickoff": "\U0001f468\u200d\U0001f3eb *Teacher Class is starting NOW!*\n\nJoin the room here: {link}\n\nHave a great session! \U0001f4aa",
    "practice_aviso": "\U0001f5e3\ufe0f *Students Practice \u2014 {date}*\n\nA students-only conversation session is scheduled for *{horario}*.\n_No teacher \u2014 just you practicing together!_\n\nIf you want to join, reply with `!attend`.\n\n\u23f3 Deadline to confirm: *{deadline}*. _(Minimum 2 students required)_",
    "practice_cancel": "\u274c *Practice Session Cancelled*\n\nUnfortunately, we didn't reach the minimum of 2 students for the {horario} practice session today. See you next time! \U0001f44b",
    "practice_kickoff": "\U0001f5e3\ufe0f *Practice Session is starting NOW!*\n\nJoin the room here: {link}\n\nHave a great conversation! \U0001f680",
    "daily_summary_header": "\u2705 Attendance confirmed for @{name}!\n\n\U0001f4c5 *Today\u2019s Schedule \u2014 {date}*\n{sessionsBlock}",
    "attend_confirm": "",
    "attend_late_good": "",
    "attend_late_bad": "",
    "unattend_confirm": "",
    "unattend_cancelled_now": "",
    "class_status": "\U0001f4cb *Today's Schedule \u2014 {date}*\n{attendees}",
    "streak_confirm": "\u2705 Image computed, @{name}! You are on a {streak}-day streak! \U0001f525",
    "streak_milestone": "\U0001f389 CONGRATULATIONS! @{name} just hit a {streak}-day streak! Legend! \U0001f3c6",
    "streak_leaderboard": "\U0001f3c6 *All-Time Streak Records*\n\n{allTimeList}\n\U0001f525 *Active Streaks Today*\n\n{activeList}"
  }
}

config_path = '/app/data/mentoria_config.json'
with open(config_path, 'w', encoding='utf-8') as f:
    json.dump(config, f, ensure_ascii=False, indent=2)

print("Config restored and updated successfully!")
print(f"class_aviso starts: {config['templates']['class_aviso'][:60]}")
print(f"practice_aviso starts: {config['templates']['practice_aviso'][:60]}")
print(f"daily_summary_header: {config['templates']['daily_summary_header'][:60]}")
