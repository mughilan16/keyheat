from pynput import keyboard 
import sqlite3


def on_press(key):
    try:
        k = key.char.lower() if key.char else str(key).replace("Key.", "")
    except:
        k = str(key).replace("Key.", "")
    log_in_db(k)

def log_in_db(k):
    if (k == 'cmd'):
        k = 'win'
    conn = sqlite3.connect('..\keyheat.db', check_same_thread=False)
    cursor = conn.cursor()
    cursor.execute("""CREATE TABLE IF NOT EXISTS keyheat (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT NOT NULL,
    count INTEGER NOT NULL DEFAULT 0
    )
    """)
    keycount = cursor.execute(
        "SELECT * FROM keyheat WHERE key=?", (k,))
    keycount = keycount.fetchone()
    if (keycount is None):
        cursor.execute(
            "INSERT INTO keyheat (key, count) VALUES (?, ?)", (k, 1,))
    else:
        cursor.execute(
            "UPDATE keyheat SET count = count + 1 WHERE key=?", (k,)
        )
    conn.commit()
    conn.close()
    print(keycount)

with keyboard.Listener(on_press=on_press) as listener:
    listener.join()