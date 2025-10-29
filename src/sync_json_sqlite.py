import json
import sqlite3
from datetime import datetime
import os
import sys

USERS_JSON_FILE = 'users.json'
STATES_JSON_FILE = 'states.json'
DB_PATH = ''
LOCK_FILE = ''

class JSONSQLiteSync:
    def __init__(self):
        self.conn = None
    
    def acquire_lock(self):
        """Prevent multiple instances running simultaneously"""
        if os.path.exists(LOCK_FILE):
            return False
        open(LOCK_FILE, 'w').close()
        return True
    
    def release_lock(self):
        """Release lock file"""
        if os.path.exists(LOCK_FILE):
            os.remove(LOCK_FILE)
    
    def load_json_file(self, filename):
        """Load JSON file safely"""
        if not os.path.exists(filename):
            return {}
        try:
            with open(filename, 'r', encoding='utf-8') as f:
                return json.load(f)
        except:
            return {}
    
    def init_database(self):
        """Initialize database connection"""
        try:
            self.conn = sqlite3.connect(DB_PATH)
            cursor = self.conn.cursor()
            
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS users (
                    chat_id INTEGER PRIMARY KEY,
                    state TEXT,
                    name TEXT,
                    company TEXT,
                    expertise TEXT,
                    email TEXT,
                    motivation TEXT,
                    verification_type TEXT,
                    verification_value TEXT,
                    verification_ref_name TEXT,
                    status TEXT DEFAULT 'در انتظار بررسی',
                    rejection_reason TEXT,
                    reviewed_by_user_id TEXT,
                    reviewed_by_username TEXT,
                    review_decision TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            """)
            
            self.conn.commit()
            return True
        except:
            return False
    
    def sync_user(self, chat_id, user_data):
        """Sync single user to database"""
        try:
            cursor = self.conn.cursor()
            
            cursor.execute("SELECT chat_id FROM users WHERE chat_id = ?", (chat_id,))
            exists = cursor.fetchone()
            
            if exists:
                cursor.execute("""
                    UPDATE users SET 
                        name = ?, company = ?, expertise = ?, email = ?,
                        motivation = ?, verification_type = ?, verification_value = ?,
                        verification_ref_name = ?, status = ?, rejection_reason = ?,
                        reviewed_by_user_id = ?, reviewed_by_username = ?,
                        review_decision = ?, updated_at = ?
                    WHERE chat_id = ?
                """, (
                    user_data.get('name'),
                    user_data.get('company'),
                    user_data.get('expertise'),
                    user_data.get('email'),
                    user_data.get('motivation'),
                    user_data.get('verification_type'),
                    user_data.get('verification_value'),
                    user_data.get('verification_ref_name'),
                    user_data.get('status', 'در انتظار بررسی'),
                    user_data.get('rejection_reason'),
                    user_data.get('reviewed_by_user_id'),
                    user_data.get('reviewed_by_username'),
                    user_data.get('review_decision'),
                    user_data.get('updated_at', datetime.now().strftime('%Y-%m-%d %H:%M:%S')),
                    chat_id
                ))
            else:
                cursor.execute("""
                    INSERT INTO users (
                        chat_id, name, company, expertise, email, motivation,
                        verification_type, verification_value, verification_ref_name,
                        status, rejection_reason, reviewed_by_user_id,
                        reviewed_by_username, review_decision, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                """, (
                    chat_id,
                    user_data.get('name'),
                    user_data.get('company'),
                    user_data.get('expertise'),
                    user_data.get('email'),
                    user_data.get('motivation'),
                    user_data.get('verification_type'),
                    user_data.get('verification_value'),
                    user_data.get('verification_ref_name'),
                    user_data.get('status', 'در انتظار بررسی'),
                    user_data.get('rejection_reason'),
                    user_data.get('reviewed_by_user_id'),
                    user_data.get('reviewed_by_username'),
                    user_data.get('review_decision'),
                    user_data.get('created_at', datetime.now().strftime('%Y-%m-%d %H:%M:%S')),
                    user_data.get('updated_at', datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
                ))
            
            self.conn.commit()
            return True
        except:
            self.conn.rollback()
            return False
    
    def sync_state(self, chat_id, state):
        """Sync user state to database"""
        try:
            cursor = self.conn.cursor()
            
            cursor.execute("SELECT chat_id FROM users WHERE chat_id = ?", (chat_id,))
            exists = cursor.fetchone()
            
            if exists:
                cursor.execute(
                    "UPDATE users SET state = ?, updated_at = ? WHERE chat_id = ?",
                    (state, datetime.now().strftime('%Y-%m-%d %H:%M:%S'), chat_id)
                )
            else:
                cursor.execute(
                    "INSERT INTO users (chat_id, state, created_at, updated_at) VALUES (?, ?, ?, ?)",
                    (chat_id, state, datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                     datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
                )
            
            self.conn.commit()
            return True
        except:
            self.conn.rollback()
            return False
    
    def sync(self):
        """Perform synchronization"""
        users = self.load_json_file(USERS_JSON_FILE)
        states = self.load_json_file(STATES_JSON_FILE)
        
        if not users and not states:
            return True
        
        if not self.init_database():
            return False
        
        try:
            for chat_id, user_data in users.items():
                self.sync_user(chat_id, user_data)
            
            for chat_id, state in states.items():
                self.sync_state(chat_id, state)
            
            return True
        except:
            return False
        finally:
            if self.conn:
                self.conn.close()
    
    def run(self):
        """Main execution"""
        if not self.acquire_lock():
            sys.exit(0)
        
        try:
            success = self.sync()
            sys.exit(0 if success else 1)
        finally:
            self.release_lock()

def main():
    syncer = JSONSQLiteSync()
    syncer.run()

if __name__ == "__main__":
    main()
