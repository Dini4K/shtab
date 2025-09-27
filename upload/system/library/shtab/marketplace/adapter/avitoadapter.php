<?php
require_once(DIR_SYSTEM . 'library/shtab/marketplace/adapter/abstractadapter.php');

class ShtabAvitoAdapter extends ShtabMarketplaceAdapter {
    private $base_url = 'https://api.avito.ru/';
    private $client_id;
    private $client_secret;
    private $access_token;

    protected function loadSettings() {
        $this->load->model('extension/module/shtab/settings');
        
        $this->client_id = $this->decrypt($this->model_extension_module_shtab_settings->getSetting('shtab_avito_client_id'));
        $this->client_secret = $this->decrypt($this->model_extension_module_shtab_settings->getSetting('shtab_avito_client_secret'));
        $this->access_token = $this->decrypt($this->model_extension_module_shtab_settings->getSetting('shtab_avito_token'));
        $this->enabled = (bool)$this->model_extension_module_shtab_settings->getSetting('shtab_avito_enabled');
        
        $this->settings = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'access_token' => $this->access_token
        ];
    }

    protected function performConnectionTest() {
        // Avito использует OAuth 2.0, нужно обновлять токен
        $token_valid = $this->validateToken();
        
        if (!$token_valid) {
            $new_token = $this->refreshToken();
            if (!$new_token) {
                throw new Exception('Не удалось обновить access token');
            }
        }

        $url = $this->base_url . 'core/v1/accounts/self';
        $response = $this->makeRequest($url, 'GET', [], $this->getAuthHeaders());
        
        return [
            'success' => true,
            'account_info' => $response,
            'message' => 'Успешное подключение к Avito API'
        ];
    }

    protected function performGetOrders($params = []) {
        $url = $this->base_url . 'core/v1/items/values';
        
        // Avito не имеет стандартной системы заказов как маркетплейсы
        // Здесь будет логика для работы с объявлениями
        return [
            'success' => true,
            'orders' => [],
            'total' => 0,
            'message' => 'Avito не поддерживает заказы в традиционном понимании'
        ];
    }

    protected function performUpdatePrices($prices) {
        // Avito - обновление цен объявлений
        $results = [];
        
        foreach ($prices as $product) {
            $url = $this->base_url . 'core/v1/items/' . $product['avito_id'] . '/price';
            $data = ['price' => (int)$product['price']];
            
            try {
                $response = $this->makeRequest($url, 'POST', $data, $this->getAuthHeaders());
                $results[] = [
                    'success' => true,
                    'item_id' => $product['avito_id'],
                    'response' => $response
                ];
            } catch (Exception $e) {
                $results[] = [
                    'success' => false,
                    'item_id' => $product['avito_id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'success' => true,
            'results' => $results,
            'processed' => count($results)
        ];
    }

    protected function performUpdateStocks($stocks) {
        // Avito - обновление статуса объявлений (активно/неактивно)
        $results = [];
        
        foreach ($stocks as $stock) {
            $url = $this->base_url . 'core/v1/items/' . $stock['avito_id'] . '/status';
            $status = $stock['quantity'] > 0 ? 'active' : 'removed';
            $data = ['status' => $status];
            
            try {
                $response = $this->makeRequest($url, 'POST', $data, $this->getAuthHeaders());
                $results[] = [
                    'success' => true,
                    'item_id' => $stock['avito_id'],
                    'status' => $status,
                    'response' => $response
                ];
            } catch (Exception $e) {
                $results[] = [
                    'success' => false,
                    'item_id' => $stock['avito_id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'success' => true,
            'results' => $results,
            'processed' => count($results)
        ];
    }

    protected function performGetProducts($params = []) {
        $url = $this->base_url . 'core/v1/items';
        
        $default_params = [
            'per_page' => 100,
            'page' => 1
        ];

        $request_params = array_merge($default_params, $params);
        $response = $this->makeRequest($url, 'GET', $request_params, $this->getAuthHeaders());
        
        $products = $this->normalizeProducts($response['items'] ?? []);
        
        return [
            'success' => true,
            'products' => $products,
            'total' => $response['total'] ?? count($products)
        ];
    }

    protected function getMarketplaceCode() {
        return 'avito';
    }

    protected function getMarketplaceName() {
        return 'Avito';
    }

    protected function getRequiredSettings() {
        return ['client_id', 'client_secret', 'access_token'];
    }

    private function getAuthHeaders() {
        return [
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: ' . 'application/json'
        ];
    }

    private function validateToken() {
        $url = $this->base_url . 'token/check';
        
        try {
            $this->makeRequest($url, 'GET', [], $this->getAuthHeaders());
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function refreshToken() {
        $url = $this->base_url . 'token';
        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret
        ];

        try {
            $response = $this->makeRequest($url, 'POST', $data, [
                'Content-Type: application/x-www-form-urlencoded'
            ]);
            
            $this->access_token = $response['access_token'];
            // Сохраняем новый токен в базу
            $this->saveNewToken($this->access_token);
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function saveNewToken($token) {
        $this->load->model('extension/module/shtab/settings');
        $this->model_extension_module_shtab_settings->setSetting('shtab_avito_token', $this->encrypt($token));
    }

    private function normalizeProducts($avito_items) {
        $normalized = [];
        // Реализация нормализации будет добавлена позже
        return $normalized;
    }
}
?>