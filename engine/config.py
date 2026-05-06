import os
from dotenv import load_dotenv

#----( from .env file )----
# the secured variables

load_dotenv()

GEMINI_API_KEY = os.environ.get('GEMINI_API_KEY')
DATA_DIR_env = os.environ.get('DATA_DIR')
MODEL_DIR_env = os.environ.get('MODEL_DIR')