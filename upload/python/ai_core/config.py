import os
from datetime import timedelta

class Config:
    # Database configuration
    DB_HOST = os.getenv('DB_HOST', 'localhost')
    DB_USER = os.getenv('DB_USER', 'opencart_user')
    DB_PASSWORD = os.getenv('DB_PASSWORD', 'password')
    DB_NAME = os.getenv('DB_NAME', 'opencart_db')
    DB_PORT = os.getenv('DB_PORT', 3306)
    
    # AI Model settings
    MODEL_PATH = os.getenv('MODEL_PATH', './models/')
    TRAINING_DATA_DAYS = 180
    PREDICTION_HORIZON = 30
    
    # Feature engineering
    FEATURE_WINDOW = 30
    SEASONALITY_PERIODS = [7, 30, 365]  # weekly, monthly, yearly
    
    # Model hyperparameters
    RANDOM_FOREST_PARAMS = {
        'n_estimators': 100,
        'max_depth': 10,
        'min_samples_split': 2,
        'min_samples_leaf': 1,
        'random_state': 42
    }
    
    # API settings
    API_HOST = os.getenv('API_HOST', '0.0.0.0')
    API_PORT = int(os.getenv('API_PORT', 8000))
    DEBUG = os.getenv('DEBUG', 'False').lower() == 'true'
    
    # Logging
    LOG_LEVEL = os.getenv('LOG_LEVEL', 'INFO')
    LOG_FORMAT = '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    
    # Cache settings
    REDIS_HOST = os.getenv('REDIS_HOST', 'localhost')
    REDIS_PORT = int(os.getenv('REDIS_PORT', 6379))
    REDIS_DB = int(os.getenv('REDIS_DB', 0))
    CACHE_TTL = timedelta(hours=1)
    
    # External APIs
    DADATA_API_KEY = os.getenv('DADATA_API_KEY', '')
    DADATA_SECRET_KEY = os.getenv('DADATA_SECRET_KEY', '')
    
    # Monitoring
    PROMETHEUS_PORT = int(os.getenv('PROMETHEUS_PORT', 8001))
    HEALTH_CHECK_INTERVAL = 300  # 5 minutes

config = Config()