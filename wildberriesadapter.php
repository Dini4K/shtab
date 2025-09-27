<?php
require_once(DIR_SYSTEM . 'library/shtab/marketplace/adapter/abstractadapter.php');

class ShtabWildberriesAdapter extends ShtabMarketplaceAdapter {
    private $base_url = 'https://suppliers-api.wildberries.ru/';
    private $statistics_url = 'https://statistics-api.wildberries.ru/';
    private $api_key;
    private $supplier_id;
    private $warehouse_id;

    protected function loadSettings() {
        $this->load->model('extension/module/shtab/settings');
        
        $this->api_key = $this->decrypt($this->model_extension_module_shtab_settings->getSetting('shtab_wildberries_api_key'));
        $this->supplier_id = $this->decrypt($this->model_extension_module_shtab_settings->getSetting('shtab_wildberries_supplier_id'));
        $this->warehouse_id = $this->decrypt($this->model_extension_module_shtab_settings->getSetting('shtab_wildberries_warehouse_id'));
        $this->enabled = (bool)$this->model_extension_module_shtab_settings->getSetting('shtab_wildberries_enabled');
    }

    public function testConnection() {
        if (!$this->enabled) {
            throw new Exception('Wildberries интеграция отключена в настройках');
        }

        $url = $this->base_url . 'public/api/v1/info';
        
        try {
            $response = $this->makeRequest($url, 'GET', [], $this->getAuthHeaders());
            
            return [
                'success' => true,
                'supplier_info' => $response ?? [],
                'timestamp' => date('Y-m-d H:i:s'),
                'message' => 'Успешное подключение к Wildberries API'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    public function getOrders($params = []) {
        if (!$this->enabled) {
            throw new Exception('Wildberries интеграция отключена');
        }

        $url = $this->base_url . 'api/v1/supplier/orders';
        
        $default_params = [
            'date_start' => date('Y-m-d\TH:i:s\Z', strtotime($params['date_from'] ?? '-7 days')),
            'date_end' => date('Y-m-d\TH:i:s\Z', strtotime($params['date_to'] ?? 'now')),
            'status' => $params['status'] ?? 0, // 0-все, 1-новые и т.д.
            'take' => 1000,
            'skip' => 0
        ];

        $request_params = array_merge($default_params, $params);
        
        try {
            $response = $this->makeRequest($url, 'GET', $request_params, $this->getAuthHeaders());
            $orders = $this->normalizeOrders($response['orders'] ?? []);
            
            return [
                'success' => true,
                'orders' => $orders,
                'total' => count($orders),
                'marketplace' => 'wildberries'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'marketplace' => 'wildberries'
            ];
        }
    }

    public function updatePrices($prices) {
        if (!$this->enabled) {
            throw new Exception('Wildberries интеграция отключена');
        }

        $url = $this->base_url . 'public/api/v1/prices';
        
        $items = [];
        foreach ($prices as $product) {
            $items[] = [
                'nmId' => (int)$product['nm_id'], // Wildberries ID
                'price' => (int)round($product['price']),
            ];
        }

        // WB ограничение: не более 1000 товаров за запрос
        $chunks = array_chunk($items, 1000);
        $results = [];

        foreach ($chunks as $chunk) {
            try {
                $response = $this->makeRequest($url, 'POST', $chunk, $this->getAuthHeaders());
                $results[] = [
                    'success' => true,
                    'processed' => count($chunk),
                    'response' => $response
                ];
            } catch (Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'processed' => count($chunk)
                ];
            }

            // Задержка для соблюдения лимитов API (1000 запросов в минуту)
            usleep(60000); // 60ms
        }

        return [
            'success' => true,
            'results' => $results,
            'total_processed' => array_sum(array_column($results, 'processed'))
        ];
    }

    public function updateStocks($stocks) {
        if (!$this->enabled) {
            throw new Exception('Wildberries интеграция отключена');
        }

        $url = $this->base_url . 'api/v1/stocks';
        
        $items = [];
        foreach ($stocks as $stock) {
            $items[] = [
                'nmId' => (int)$stock['nm_id'],
                'stock' => (int)$stock['quantity'],
                'warehouseId' => (int)$stock['warehouse_id'] ?? $this->warehouse_id
            ];
        }

        $chunks = array_chunk($items, 1000);
        $results = [];

        foreach ($chunks as $chunk) {
            try {
                $response = $this->makeRequest($url, 'POST', $chunk, $this->getAuthHeaders());
                $results[] = [
                    'success' => true,
                    'processed' => count($chunk),
                    'response' => $response
                ];
            } catch (Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'processed' => count($chunk)
                ];
            }

            usleep(60000);
        }

        return [
            'success' => true,
            'results' => $results,
            'total_processed' => array_sum(array_column($results, 'processed'))
        ];
    }

    public function getProducts($params = []) {
        if (!$this->enabled) {
            throw new Exception('Wildberries интеграция отключена');
        }

        $url = $this->base_url . 'content/v1/cards/cursor/list';
        
        $default_params = [
            'sort' => [
                'cursor' => [
                    'limit' => 1000
                ],
                'filter' => [
                    'withPhoto' => -1 // -1 все, 0 без фото, 1 с фото
                ]
            ]
        ];

        $request_params = array_merge($default_params, $params);
        $all_products = [];
        $has_more = true;
        $cursor = null;

        while ($has_more) {
            if ($cursor) {
                $request_params['sort']['cursor']['updatedAt'] = $cursor;
            }

            $response = $this->makeRequest($url, 'POST', $request_params, $this->getAuthHeaders());
            
            if (isset($response['data']['cards'])) {
                $products = $this->normalizeProducts($response['data']['cards']);
                $all_products = array_merge($all_products, $products);
                
                $has_more = $response['data']['cursor']['total'] > count($all_products);
                $cursor = $response['data']['cursor']['updatedAt'] ?? null;
            } else {
                $has_more = false;
            }

            usleep(50000); // 50ms
        }

        return [
            'success' => true,
            'products' => $all_products,
            'total' => count($all_products)
        ];
    }

    /**
     * Получение статистики по продажам
     */
    public function getSalesStatistics($params = []) {
        $url = $this->statistics_url . 'api/v1/supplier/sales';
        
        $default_params = [
            'dateFrom' => date('Y-m-d', strtotime($params['date_from'] ?? '-7 days')),
            'dateTo' => date('Y-m-d', strtotime($params['date_to'] ?? 'now')),
            'limit' => 10000
        ];

        $request_params = array_merge($default_params, $params);
        
        try {
            $response = $this->makeRequest($url, 'GET', $request_params, $this->getAuthHeaders());
            return [
                'success' => true,
                'statistics' => $response,
                'total' => count($response)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Получение информации по складам
     */
    public function getWarehouses() {
        $url = $this->base_url . 'api/v1/warehouses';
        
        try {
            $response = $this->makeRequest($url, 'GET', [], $this->getAuthHeaders());
            return [
                'success' => true,
                'warehouses' => $response
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Нормализация заказов Wildberries в унифицированный формат
     */
    private function normalizeOrders($wb_orders) {
        $normalized = [];

        foreach ($wb_orders as $wb_order) {
            $items = [];
            $total_amount = 0;

            // Wildberries передает каждый товар отдельным заказом
            $price = $wb_order['convertedPrice'] ?? $wb_order['price'] ?? 0;
            $quantity = $wb_order['convertedQuantity'] ?? $wb_order['quantity'] ?? 1;
            
            $items[] = [
                'product_id' => $wb_order['nmId'] ?? 0,
                'nm_id' => $wb_order['nmId'] ?? 0,
                'barcode' => $wb_order['barcode'] ?? '',
                'name' => $wb_order['subject'] ?? 'Товар Wildberries',
                'quantity' => $quantity,
                'price' => $price,
                'total' => $price * $quantity
            ];

            $total_amount = $price * $quantity;

            $normalized[] = [
                'external_order_id' => $wb_order['orderId'] ?? '',
                'external_order_number' => $wb_order['srid'] ?? '',
                'order_date' => date('Y-m-d H:i:s', strtotime($wb_order['date'] ?? 'now')),
                'customer_name' => 'Покупатель Wildberries', // WB не передает имя
                'customer_phone' => '', // WB не передает телефон
                'customer_email' => '', // WB не передает email
                'shipping_address' => $this->formatAddress($wb_order['address'] ?? []),
                'shipping_city' => $wb_order['oblast'] ?? '',
                'shipping_region' => $wb_order['region'] ?? '',
                'shipping_postcode' => $wb_order['index'] ?? '',
                'total_amount' => $total_amount,
                'shipping_cost' => 0, // WB включает доставку в цену
                'commission' => $this->calculateCommission($total_amount),
                'items' => $items,
                'status' => $this->mapStatus($wb_order['status'] ?? 0),
                'additional_data' => $wb_order
            ];
        }

        return $normalized;
    }

    /**
     * Нормализация товаров Wildberries
     */
    private function normalizeProducts($wb_products) {
        $normalized = [];

        foreach ($wb_products as $product) {
            $characteristics = [];
            foreach ($product['characteristics'] ?? [] as $char) {
                $characteristics[$char['name'] ?? ''] = $char['value'] ?? '';
            }

            $normalized[] = [
                'product_id' => $product['nmID'] ?? 0,
                'nm_id' => $product['nmID'] ?? 0,
                'sku' => $product['vendorCode'] ?? '',
                'barcode' => $product['barcodes'][0] ?? '',
                'name' => $product['title'] ?? '',
                'brand' => $product['brand'] ?? '',
                'price' => $product['price'] ?? 0,
                'old_price' => $product['oldPrice'] ?? 0,
                'stock' => $product['stock'] ?? 0,
                'status' => $this->mapProductStatus($product['status'] ?? 0),
                'images' => $product['mediaFiles'] ?? [],
                'characteristics' => $characteristics,
                'created_at' => $product['createdAt'] ?? '',
                'updated_at' => $product['updatedAt'] ?? ''
            ];
        }

        return $normalized;
    }

    /**
     * Формирование заголовков авторизации Wildberries
     */
    private function getAuthHeaders() {
        return [
            'Authorization: ' . $this->api_key,
            'Content-Type: application/json'
        ];
    }

    /**
     * Форматирование адреса доставки
     */
    private function formatAddress($address) {
        if (is_array($address)) {
            $parts = [
                $address['address'] ?? '',
                $address['city'] ?? '',
                $address['oblast'] ?? '',
                $address['region'] ?? ''
            ];
            return trim(implode(', ', array_filter($parts)));
        }
        
        return (string)$address;
    }

    /**
     * Расчет комиссии Wildberries (примерные значения)
     */
    private function calculateCommission($amount) {
        // Комиссия WB: 5-20% в зависимости от категории
        return $amount * 0.15; // Средняя комиссия 15%
    }

    /**
     * Маппинг статусов Wildberries на внутренние
     */
    private function mapStatus($wb_status) {
        $status_map = [
            0 => 'new',           // Новый заказ
            1 => 'confirmed',     // Подтвержден
            2 => 'assembling',    // Сборка
            3 => 'ready_to_ship', // Готов к отгрузке
            4 => 'shipped',       // Отправлен
            5 => 'delivered',     // Доставлен
            6 => 'returned',      // Возврат
            7 => 'cancelled'      // Отменен
        ];

        return $status_map[$wb_status] ?? 'unknown';
    }

    /**
     * Маппинг статусов товаров
     */
    private function mapProductStatus($status) {
        $status_map = [
            0 => 'draft',         // Черновик
            1 => 'moderation',    // На модерации
            2 => 'moderated',     // Прошел модерацию
            3 => 'moderation_failed', // Не прошел модерацию
            4 => 'active',        // Активный
            5 => 'inactive',      // Неактивный
            6 => 'banned'         // Заблокирован
        ];

        return $status_map[$status] ?? 'unknown';
    }
}
?>