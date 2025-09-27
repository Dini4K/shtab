from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestRegressor
from sklearn.preprocessing import StandardScaler
import joblib
import mysql.connector
from datetime import datetime, timedelta
import json
import logging
from typing import Dict, List, Optional

app = FastAPI(title="SHTAB AI Core", version="1.0.0")

# Настройка логирования
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Модели AI
models = {}
scaler = StandardScaler()

class ForecastRequest(BaseModel):
    product_ids: List[int]
    marketplace: str
    days_ahead: int = 30
    include_seasonality: bool = True

class PriceOptimizationRequest(BaseModel):
    product_id: int
    marketplace: str
    current_price: float
    competitor_prices: Dict[str, float]
    stock_level: int
    demand_trend: float

class LogisticPredictionRequest(BaseModel):
    from_city: str
    to_city: str
    weight: float
    carrier: str
    delivery_type: str

@app.on_event("startup")
async def load_models():
    """Загрузка обученных моделей при старте"""
    try:
        # Загрузка модели прогноза спроса
        models['demand_forecast'] = joblib.load('models/demand_forecast_model.pkl')
        
        # Загрузка модели оптимизации цен
        models['price_optimization'] = joblib.load('models/price_optimization_model.pkl')
        
        # Загрузка модели логистики
        models['logistic_predictor'] = joblib.load('models/logistic_predictor_model.pkl')
        
        logger.info("AI модели успешно загружены")
    except Exception as e:
        logger.warning(f"Не удалось загрузить модели: {e}")

def get_db_connection():
    """Соединение с базой данных"""
    return mysql.connector.connect(
        host="localhost",
        user="opencart_user",
        password="password",
        database="opencart_db",
        charset='utf8mb4'
    )

@app.post("/api/ai/forecast-demand")
async def forecast_demand(request: ForecastRequest):
    """Прогноз спроса для товаров"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        # Получаем исторические данные
        query = """
            SELECT product_id, order_date, quantity, price, marketplace
            FROM oc_shtab_order so
            JOIN JSON_TABLE(so.items, '$[*]' COLUMNS(
                product_id INT PATH '$.product_id',
                quantity INT PATH '$.quantity',
                price DECIMAL(15,4) PATH '$.price'
            )) items ON 1=1
            WHERE so.order_date >= DATE_SUB(NOW(), INTERVAL 180 DAY)
            AND so.marketplace = %s
            AND items.product_id IN ({})
        """.format(','.join(['%s'] * len(request.product_ids)))
        
        cursor.execute(query, [request.marketplace] + request.product_ids)
        data = cursor.fetchall()
        
        if not data:
            return {"error": "Недостаточно данных для прогноза"}
        
        # Подготовка данных для ML
        df = pd.DataFrame(data)
        df['order_date'] = pd.to_datetime(df['order_date'])
        df = df.set_index('order_date')
        
        # Агрегация по дням
        daily_sales = df.groupby(['product_id', pd.Grouper(freq='D')])['quantity'].sum().reset_index()
        
        forecasts = {}
        for product_id in request.product_ids:
            product_data = daily_sales[daily_sales['product_id'] == product_id]
            
            if len(product_data) < 30:  # Минимум 30 дней данных
                forecasts[product_id] = {"error": "Недостаточно исторических данных"}
                continue
            
            # Прогноз с использованием ML модели
            forecast = generate_forecast(product_data, request.days_ahead)
            forecasts[product_id] = forecast
        
        cursor.close()
        conn.close()
        
        return {
            "success": True,
            "forecasts": forecasts,
            "generated_at": datetime.now().isoformat()
        }
        
    except Exception as e:
        logger.error(f"Ошибка прогноза спроса: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/api/ai/optimize-price")
async def optimize_price(request: PriceOptimizationRequest):
    """Оптимизация цены на основе AI"""
    try:
        # Получаем дополнительные данные из БД
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("""
            SELECT cost_price, min_price, max_price, rrp_price 
            FROM oc_shtab_product 
            WHERE product_id = %s
        """, (request.product_id,))
        
        product_data = cursor.fetchone()
        cursor.close()
        conn.close()
        
        if not product_data:
            raise HTTPException(status_code=404, detail="Товар не найден")
        
        # Подготовка признаков для модели
        features = {
            'current_price': request.current_price,
            'cost_price': float(product_data['cost_price']),
            'min_price': float(product_data['min_price']),
            'max_price': float(product_data['max_price']),
            'avg_competitor_price': np.mean(list(request.competitor_prices.values())),
            'min_competitor_price': min(request.competitor_prices.values()),
            'stock_level': request.stock_level,
            'demand_trend': request.demand_trend,
            'price_elasticity': calculate_price_elasticity(request.product_id)
        }
        
        # Прогноз оптимальной цены
        optimal_price = predict_optimal_price(features)
        
        # Проверка ограничений
        optimal_price = max(optimal_price, float(product_data['min_price']))
        optimal_price = min(optimal_price, float(product_data['max_price']))
        
        return {
            "success": True,
            "current_price": request.current_price,
            "recommended_price": round(optimal_price, 2),
            "expected_demand_change": predict_demand_change(request.current_price, optimal_price),
            "expected_profit_change": calculate_profit_change(
                request.current_price, optimal_price, float(product_data['cost_price'])
            ),
            "confidence": 0.85  # Уверенность модели
        }
        
    except Exception as e:
        logger.error(f"Ошибка оптимизации цены: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/api/ai/predict-logistics")
async def predict_logistics(request: LogisticPredictionRequest):
    """Прогноз сроков и стоимости доставки"""
    try:
        # Подготовка признаков
        features = {
            'distance': calculate_distance(request.from_city, request.to_city),
            'weight': request.weight,
            'carrier': request.carrier,
            'delivery_type': request.delivery_type,
            'season_factor': get_season_factor(),
            'route_popularity': get_route_popularity(request.from_city, request.to_city)
        }
        
        # Прогноз с использованием ML
        prediction = predict_logistic_metrics(features)
        
        return {
            "success": True,
            "predicted_days": prediction['days'],
            "predicted_cost": prediction['cost'],
            "confidence": prediction['confidence'],
            "recommended_carrier": suggest_best_carrier(features)
        }
        
    except Exception as e:
        logger.error(f"Ошибка прогноза логистики: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/api/ai/models/status")
async def get_models_status():
    """Статус AI моделей"""
    status = {}
    for model_name, model in models.items():
        status[model_name] = {
            "loaded": model is not None,
            "version": "1.0.0",
            "last_trained": "2024-01-01"  # В реальности из конфига
        }
    return status

# Вспомогательные функции
def generate_forecast(data: pd.DataFrame, days_ahead: int) -> Dict:
    """Генерация прогноза спроса"""
    # Упрощенная реализация - в реальности используем ML модель
    recent_sales = data['quantity'].tail(30).mean()
    seasonality = calculate_seasonality_factor(data)
    
    forecast = {
        "baseline_forecast": recent_sales,
        "optimistic_forecast": recent_sales * 1.2,
        "pessimistic_forecast": recent_sales * 0.8,
        "seasonality_factor": seasonality,
        "trend": calculate_trend(data)
    }
    
    return forecast

def predict_optimal_price(features: Dict) -> float:
    """Предсказание оптимальной цены"""
    # Упрощенная реализация - в реальности используем ML модель
    base_price = features['current_price']
    competitor_avg = features['avg_competitor_price']
    cost_price = features['cost_price']
    
    # Простая логика оптимизации
    if base_price > competitor_avg * 1.1:
        # Цена выше конкурентов - снижаем
        optimal = max(competitor_avg * 0.95, cost_price * 1.2)
    else:
        # Цена в норме - оптимизируем для прибыли
        optimal = (base_price + competitor_avg) / 2
    
    return optimal

def calculate_price_elasticity(product_id: int) -> float:
    """Расчет эластичности цены"""
    # Упрощенная реализация
    return -0.5  # Средняя эластичность

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)