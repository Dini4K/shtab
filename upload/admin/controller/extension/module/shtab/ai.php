<?php
class ControllerExtensionModuleShtabAI extends Controller {
    private $error = array();
    private $module_path = 'extension/module/shtab/';

    public function index() {
        $this->load->language($this->module_path . 'ai');
        $this->document->setTitle($this->language->get('heading_title'));

        $data = $this->prepareTemplateData();

        // Статус AI сервисов
        $data['ai_status'] = $this->getAIStatus();
        $data['recommendations'] = $this->getAIRecommendations();
        $data['forecasts'] = $this->getRecentForecasts();

        $this->response->setOutput($this->load->view($this->module_path . 'ai/dashboard', $data));
    }

    /**
     * Получить рекомендации по ценам
     */
    public function getPriceRecommendations() {
        $this->load->language($this->module_path . 'ai');
        
        $marketplace = $this->request->get['marketplace'] ?? 'all';
        $limit = $this->request->get['limit'] ?? 10;

        try {
            require_once(DIR_SYSTEM . 'library/shtab/service/servicemanager.php');
            $serviceManager = new ShtabServiceManager($this->registry);
            $aiService = $serviceManager->ai();

            $recommendations = $aiService->getPricingRecommendations([
                'marketplace' => $marketplace,
                'limit' => $limit
            ]);

            $this->response->setOutput(json_encode([
                'success' => true,
                'recommendations' => $recommendations
            ]));

        } catch (Exception $e) {
            $this->response->setOutput(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
        }
    }

    /**
     * Запустить прогноз спроса
     */
    public function runDemandForecast() {
        $this->load->language($this->module_path . 'ai');
        
        $product_ids = $this->request->post['product_ids'] ?? [];
        $marketplace = $this->request->post['marketplace'] ?? 'all';
        $days_ahead = $this->request->post['days_ahead'] ?? 30;

        try {
            require_once(DIR_SYSTEM . 'library/shtab/service/servicemanager.php');
            $serviceManager = new ShtabServiceManager($this->registry);
            $aiService = $serviceManager->ai();

            $result = $aiService->getDemandForecast($product_ids, $marketplace, $days_ahead);

            $this->response->setOutput(json_encode($result));

        } catch (Exception $e) {
            $this->response->setOutput(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
        }
    }

    /**
     * Переобучить AI модели
     */
    public function retrainModels() {
        $this->load->language($this->module_path . 'ai');
        
        $model_type = $this->request->post['model_type'] ?? null;

        try {
            require_once(DIR_SYSTEM . 'library/shtab/service/servicemanager.php');
            $serviceManager = new ShtabServiceManager($this->registry);
            $aiService = $serviceManager->ai();

            $result = $aiService->retrainModels($model_type);

            $this->response->setOutput(json_encode($result));

        } catch (Exception $e) {
            $this->response->setOutput(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
        }
    }

    /**
     * Применить рекомендацию AI
     */
    public function applyRecommendation() {
        $this->load->language($this->module_path . 'ai');
        
        $product_id = $this->request->post['product_id'];
        $recommended_price = $this->request->post['recommended_price'];
        $marketplace = $this->request->post['marketplace'];

        try {
            // Обновляем цену в БД
            $this->db->query("UPDATE " . DB_PREFIX . "shtab_product_to_marketplace 
                             SET price = '" . (float)$recommended_price . "',
                                 old_price = price,
                                 last_sync = NOW()
                             WHERE product_id = '" . (int)$product_id . "' 
                             AND marketplace = '" . $this->db->escape($marketplace) . "'");

            // Логируем изменение
            $this->db->query("INSERT INTO " . DB_PREFIX . "shtab_price_history 
                             SET product_id = '" . (int)$product_id . "',
                                 marketplace = '" . $this->db->escape($marketplace) . "',
                                 old_price = '" . (float)$this->request->post['current_price'] . "',
                                 new_price = '" . (float)$recommended_price . "',
                                 change_type = 'ai_recommendation',
                                 reason = 'AI рекомендация: " . $this->db->escape($this->request->post['reason'] ?? '') . "',
                                 date_added = NOW()");

            $this->response->setOutput(json_encode([
                'success' => true,
                'message' => 'Цена успешно обновлена по рекомендации AI'
            ]));

        } catch (Exception $e) {
            $this->response->setOutput(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
        }
    }

    private function getAIStatus() {
        try {
            require_once(DIR_SYSTEM . 'library/shtab/service/servicemanager.php');
            $serviceManager = new ShtabServiceManager($this->registry);
            $aiService = $serviceManager->ai();

            // Проверяем доступность AI сервиса
            $status_url = 'http://localhost:8000/api/ai/models/status';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $status_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return [
                'service_available' => $http_code === 200,
                'models' => $http_code === 200 ? json_decode($response, true) : []
            ];

        } catch (Exception $e) {
            return [
                'service_available' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getAIRecommendations() {
        try {
            require_once(DIR_SYSTEM . 'library/shtab/service/servicemanager.php');
            $serviceManager = new ShtabServiceManager($this->registry);
            $aiService = $serviceManager->ai();

            return $aiService->getPricingRecommendations(['limit' => 5]);

        } catch (Exception $e) {
            return [];
        }
    }

    private function getRecentForecasts() {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "shtab_ai_prediction 
                                  WHERE model_type = 'demand' 
                                  ORDER BY date_added DESC LIMIT 10");
        
        $forecasts = [];
        foreach ($query->rows as $row) {
            $row['prediction_data'] = json_decode($row['prediction_data'], true);
            $forecasts[] = $row;
        }
        
        return $forecasts;
    }

    private function prepareTemplateData() {
        $data['user_token'] = $this->session->data['user_token'];
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['menu'] = $this->load->controller($this->module_path . 'shtab_menu');

        // URLs
        $data['get_recommendations_url'] = $this->url->link($this->module_path . 'ai/getPriceRecommendations', 'user_token=' . $data['user_token'], true);
        $data['run_forecast_url'] = $this->url->link($this->module_path . 'ai/runDemandForecast', 'user_token=' . $data['user_token'], true);
        $data['retrain_models_url'] = $this->url->link($this->module_path . 'ai/retrainModels', 'user_token=' . $data['user_token'], true);
        $data['apply_recommendation_url'] = $this->url->link($this->module_path . 'ai/applyRecommendation', 'user_token=' . $data['user_token'], true);

        // Хлебные крошки
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link($this->module_path . 'ai', 'user_token=' . $this->session->data['user_token'], true)
        );

        return $data;
    }
}
?>