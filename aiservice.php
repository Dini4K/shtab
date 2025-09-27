<?php
class ShtabAIService {
    protected $registry;
    protected $db;
    protected $log;
    protected $ai_base_url;

    public function __construct($registry) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->log = $registry->get('log');
        $this->ai_base_url = 'http://localhost:8000/api/ai'; // URL Python микросервиса
    }

    /**
     * Получить прогноз спроса
     */
    public function getDemandForecast($product_ids, $marketplace, $days_ahead = 30) {
        $data = [
            'product_ids' => $product_ids,
            'marketplace' => $marketplace,
            'days_ahead' => $days_ahead,
            'include_seasonality' => true
        ];

        $response = $this->makeAIRequest('/forecast-demand', $data);
        
        if ($response['success']) {
            // Сохраняем прогноз в БД
            $this->savePrediction('demand', $product_ids, $marketplace, $response['forecasts']);
        }

        return $response;
    }

    /**
     * Оптимизировать цену товара
     */
    public function optimizePrice($product_id, $marketplace, $current_price, $competitor_prices = []) {
        // Получаем дополнительные данные
        $product_data = $this->getProductData($product_id);
        $demand_trend = $this->getDemandTrend($product_id, $marketplace);
        $stock_level = $this->getStockLevel($product_id, $marketplace);

        $data = [
            'product_id' => $product_id,
            'marketplace' => $marketplace,
            'current_price' => $current_price,
            'competitor_prices' => $competitor_prices,
            'stock_level' => $stock_level,
            'demand_trend' => $demand_trend
        ];

        $response = $this->makeAIRequest('/optimize-price', $data);
        
        if ($response['success']) {
            $this->savePrediction('price', $product_id, $marketplace, $response);
        }

        return $response;
    }

    /**
     * Получить рекомендации по ценообразованию
     */
    public function getPricingRecommendations($filters = []) {
        $products = $this->getProductsForAnalysis($filters);
        $recommendations = [];

        foreach ($products as $product) {
            $competitor_prices = $this->getCompetitorPrices($product['product_id']);
            
            $recommendation = $this->optimizePrice(
                $product['product_id'],
                $filters['marketplace'] ?? 'all',
                $product['current_price'],
                $competitor_prices
            );

            if ($recommendation['success']) {
                $recommendations[] = [
                    'product_id' => $product['product_id'],
                    'product_name' => $product['name'],
                    'current_price' => $product['current_price'],
                    'recommended_price' => $recommendation['recommended_price'],
                    'expected_profit_change' => $recommendation['expected_profit_change'],
                    'confidence' => $recommendation['confidence']
                ];
            }
        }

        // Сортируем по потенциальной выгоде
        usort($recommendations, function($a, $b) {
            return $b['expected_profit_change'] <=> $a['expected_profit_change'];
        });

        return $recommendations;
    }

    /**
     * Прогноз логистики
     */
    public function predictLogistics($from_city, $to_city, $weight, $carrier = null, $delivery_type = 'standard') {
        $data = [
            'from_city' => $from_city,
            'to_city' => $to_city,
            'weight' => $weight,
            'carrier' => $carrier,
            'delivery_type' => $delivery_type
        ];

        return $this->makeAIRequest('/predict-logistics', $data);
    }

    /**
     * Обучение AI моделей на новых данных
     */
    public function retrainModels($model_type = null) {
        $data = ['retrain_all' => $model_type === null];
        
        if ($model_type) {
            $data['model_type'] = $model_type;
        }

        $response = $this->makeAIRequest('/retrain-models', $data, 'POST');
        
        if ($response['success']) {
            $this->log->write("SHTAB AI: Модели успешно переобучены");
        }

        return $response;
    }

    /**
     * Запрос к AI микросервису
     */
    protected function makeAIRequest($endpoint, $data, $method = 'POST') {
        $url = $this->ai_base_url . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($data))
            ]);
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            return json_decode($response, true);
        } else {
            $this->log->write("SHTAB AI ERROR: HTTP $http_code - $endpoint");
            return [
                'success' => false,
                'error' => 'AI service unavailable',
                'http_code' => $http_code
            ];
        }
    }

    /**
     * Сохранение предсказания в БД
     */
    protected function savePrediction($model_type, $product_id, $marketplace, $prediction_data) {
        $this->db->query("INSERT INTO " . DB_PREFIX . "shtab_ai_prediction 
            SET model_type = '" . $this->db->escape($model_type) . "',
                product_id = '" . (int)$product_id . "',
                marketplace = '" . $this->db->escape($marketplace) . "',
                prediction_date = CURDATE(),
                prediction_data = '" . $this->db->escape(json_encode($prediction_data)) . "',
                confidence = '" . (float)($prediction_data['confidence'] ?? 0) . "',
                date_added = NOW()");
    }

    /**
     * Получить данные товара
     */
    protected function getProductData($product_id) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "shtab_product 
                                  WHERE product_id = '" . (int)$product_id . "'");
        return $query->num_rows ? $query->row : null;
    }

    /**
     * Получить тренд спроса
     */
    protected function getDemandTrend($product_id, $marketplace) {
        $query = $this->db->query("
            SELECT AVG(quantity) as avg_demand
            FROM oc_shtab_order so
            JOIN JSON_TABLE(so.items, '$[*]' COLUMNS(
                product_id INT PATH '$.product_id',
                quantity INT PATH '$.quantity'
            )) items ON 1=1
            WHERE items.product_id = '" . (int)$product_id . "'
            AND so.marketplace = '" . $this->db->escape($marketplace) . "'
            AND so.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        return $query->num_rows ? $query->row['avg_demand'] : 0;
    }

    /**
     * Получить уровень запасов
     */
    protected function getStockLevel($product_id, $marketplace) {
        $query = $this->db->query("SELECT stock FROM " . DB_PREFIX . "shtab_product_to_marketplace 
                                  WHERE product_id = '" . (int)$product_id . "' 
                                  AND marketplace = '" . $this->db->escape($marketplace) . "'");
        return $query->num_rows ? $query->row['stock'] : 0;
    }

    /**
     * Получить цены конкурентов
     */
    protected function getCompetitorPrices($product_id) {
        $query = $this->db->query("SELECT competitor_name, competitor_price 
                                  FROM " . DB_PREFIX . "shtab_competitor_price 
                                  WHERE product_id = '" . (int)$product_id . "' 
                                  AND is_available = 1");
        
        $prices = [];
        foreach ($query->rows as $row) {
            $prices[$row['competitor_name']] = $row['competitor_price'];
        }
        
        return $prices;
    }

    /**
     * Получить товары для анализа
     */
    protected function getProductsForAnalysis($filters) {
        $sql = "SELECT p.product_id, pd.name, pm.price as current_price
                FROM " . DB_PREFIX . "shtab_product p
                LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
                LEFT JOIN " . DB_PREFIX . "shtab_product_to_marketplace pm ON (p.product_id = pm.product_id)
                WHERE p.repricing_enabled = 1";

        if (isset($filters['marketplace'])) {
            $sql .= " AND pm.marketplace = '" . $this->db->escape($filters['marketplace']) . "'";
        }

        $query = $this->db->query($sql);
        return $query->rows;
    }
}
?>