<?php
require_once(DIR_SYSTEM . 'library/shtab/marketplace/adapter/abstractadapter.php');

class ShtabYandexMarketAdapter extends ShtabMarketplaceAdapter {
    private $base_url = 'https://api.partner.market.yandex.ru/';
    private $oauth_token;
    private $campaign_id;
    private $client_id;

    protected function loadSettings() {
        $this->load->model('extension/module/shtab/settings');
        
        $this->oauth_token = $this->decrypt($this->model_extension_module_shtab_settings->getSetting('shtab_yandex_market_oauth_token'));
        $this->campaign_id = $this->decrypt($this->model_extension_module_shtab_settings->getSetting('shtab_yandex_market_campaign_id'));
        $this->client_id = $this->decrypt($this->model_extension_module_shtab_settings->getSetting('shtab_yandex_market_client_id'));
        $this->enabled = (bool)$this->model_extension_module_shtab_settings->getSetting('shtab_yandex_market_enabled');
        
        $this->settings = [
            'oauth_token' => $this->oauth_token,
            'campaign_id' => $this->campaign_id,
            'client_id' => $this->client_id
        ];
    }

    protected function performConnectionTest() {
        $url = $this->base_url . 'campaigns';
        
        $response = $this->makeRequest($url, 'GET', [], $this->getAuthHeaders());
        
        return [
            'success' => true,
            'campaigns' => $response['campaigns'] ?? [],
            'message' => 'Успешное подключение к Яндекс.Маркет API'
        ];
    }

    protected function performGetOrders($params = []) {
        $url = $this->base_url . 'campaigns/' . $this->campaign_id . '/orders';
        
        $default_params = [
            'status' => $params['status'] ?? 'PROCESSING',
            'fromDate' => date('Y-m-d\TH:i:s\Z', strtotime($params['date_from'] ?? '-7 days')),
            'toDate' => date('Y-m-d\TH:i:s\Z', strtotime($params['date_to'] ?? 'now')),
            'pageSize' => 50
        ];

        $request_params = array_merge($default_params, $params);
        $response = $this->makeRequest($url, 'GET', $request_params, $this->getAuthHeaders());
        
        $orders = $this->normalizeOrders($response['orders'] ?? []);
        
        return [
            'success' => true,
            'orders' => $orders,
            'total' => count($orders),
            'pager' => $response['pager'] ?? []
        ];
    }

    protected function performUpdatePrices($prices) {
        $url = $this->base_url . 'campaigns/' . $this->campaign_id . '/offer-prices/updates';
        
        $offers = [];
        foreach ($prices as $product) {
            $offers[] = [
                'offerId' => $product['sku'],
                'price' => [
                    'value' => (float)$product['price'],
                    'currencyId' => 'RUR'
                ]
            ];
        }

        $data = ['offers' => $offers];
        $response = $this->makeRequest($url, 'POST', $data, $this->getAuthHeaders());
        
        return [
            'success' => true,
            'response' => $response,
            'processed' => count($offers)
        ];
    }

    protected function performUpdateStocks($stocks) {
        $url = $this->base_url . 'campaigns/' . $this->campaign_id . '/offers/stocks';
        
        $skus = [];
        foreach ($stocks as $stock) {
            $skus[] = [
                'sku' => $stock['sku'],
                'warehouseId' => $stock['warehouse_id'] ?? 0,
                'items' => [
                    [
                        'count' => (int)$stock['quantity'],
                        'type' => 'FIT'
                    ]
                ]
            ];
        }

        $data = ['skus' => $skus];
        $response = $this->makeRequest($url, 'PUT', $data, $this->getAuthHeaders());
        
        return [
            'success' => true,
            'response' => $response,
            'processed' => count($skus)
        ];
    }

    protected function performGetProducts($params = []) {
        $url = $this->base_url . 'campaigns/' . $this->campaign_id . '/offer-mapping-entries';
        
        $default_params = [
            'limit' => 100,
            'page_token' => $params['page_token'] ?? ''
        ];

        $request_params = array_merge($default_params, $params);
        $response = $this->makeRequest($url, 'GET', $request_params, $this->getAuthHeaders());
        
        $products = $this->normalizeProducts($response['result'] ?? []);
        
        return [
            'success' => true,
            'products' => $products,
            'total' => count($products),
            'next_page_token' => $response['paging'] ?? null
        ];
    }

    protected function getMarketplaceCode() {
        return 'yandex_market';
    }

    protected function getMarketplaceName() {
        return 'Яндекс.Маркет';
    }

    protected function getRequiredSettings() {
        return ['oauth_token', 'campaign_id', 'client_id'];
    }

    private function getAuthHeaders() {
        return [
            'Authorization: OAuth oauth_token="' . $this->oauth_token . '", oauth_client_id="' . $this->client_id . '"',
            'Content-Type: application/json'
        ];
    }

    private function normalizeOrders($ym_orders) {
        $normalized = [];
        // Реализация нормализации будет добавлена позже
        return $normalized;
    }

    private function normalizeProducts($ym_products) {
        $normalized = [];
        // Реализация нормализации будет добавлена позже
        return $normalized;
    }
}
?>