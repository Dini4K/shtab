<?php
class ShtabOrderCreator {
    protected $registry;
    protected $db;
    protected $log;

    public function __construct($registry) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->log = $registry->get('log');
        $this->load->model('checkout/order');
        $this->load->model('catalog/product');
        $this->load->model('customer/customer');
    }

    /**
     * ПОЛНАЯ РЕАЛИЗАЦИЯ: Создание заказа в OpenCart
     */
    public function createOpencartOrder($shtab_order_id) {
        $order_data = $this->getOrderData($shtab_order_id);
        if (!$order_data) {
            throw new Exception('Заказ не найден');
        }

        // Подготавливаем полные данные для OpenCart
        $opencart_data = $this->prepareFullOpencartData($order_data);

        // Создаем заказ
        $opencart_order_id = $this->model_checkout_order->addOrder($opencart_data);

        if ($opencart_order_id) {
            // Связываем заказы
            $this->linkOrders($shtab_order_id, $opencart_order_id);
            
            // Обновляем статус
            $this->updateOrderStatus($shtab_order_id, 'processed');

            $this->log->write("SHTAB: Создан заказ OpenCart #$opencart_order_id для заказа #$shtab_order_id");

            return $opencart_order_id;
        }

        throw new Exception('Ошибка создания заказа в OpenCart');
    }

    /**
     * Подготовка полных данных для OpenCart
     */
    protected function prepareFullOpencartData($order_data) {
        // 1. Находим или создаем клиента
        $customer_id = $this->findOrCreateCustomer($order_data);
        
        // 2. Подготавливаем товары
        $products = $this->prepareOrderProducts($order_data['items']);
        
        // 3. Подготавливаем итоги
        $totals = $this->prepareOrderTotals($order_data);
        
        // 4. Настраиваем доставку и оплату
        $shipping_method = $this->mapShippingMethod($order_data['shipping_method']);
        $payment_method = $this->mapPaymentMethod($order_data['payment_method']);

        // 5. Полные данные заказа
        return [
            'invoice_prefix' => $this->config->get('config_invoice_prefix'),
            'store_id' => $this->config->get('config_store_id'),
            'store_name' => $this->config->get('config_name'),
            'store_url' => $this->config->get('config_url'),
            
            'customer_id' => $customer_id,
            'customer_group_id' => $this->getCustomerGroupId($customer_id),
            'firstname' => $this->extractFirstName($order_data['customer_name']),
            'lastname' => $this->extractLastName($order_data['customer_name']),
            'email' => $order_data['customer_email'] ?: 'no-email@example.com',
            'telephone' => $order_data['customer_phone'] ?: '0000000000',
            'custom_field' => [],
            
            'payment_firstname' => $this->extractFirstName($order_data['customer_name']),
            'payment_lastname' => $this->extractLastName($order_data['customer_name']),
            'payment_company' => '',
            'payment_address_1' => $order_data['shipping_address'],
            'payment_address_2' => '',
            'payment_city' => $order_data['shipping_city'] ?: 'Москва',
            'payment_postcode' => $order_data['shipping_postcode'] ?: '000000',
            'payment_country' => $this->getCountryId($order_data['shipping_country']),
            'payment_country_id' => $this->getCountryId($order_data['shipping_country']),
            'payment_zone' => $this->getZoneName($order_data['shipping_region']),
            'payment_zone_id' => $this->getZoneId($order_data['shipping_region']),
            'payment_address_format' => '',
            'payment_custom_field' => [],
            'payment_method' => $payment_method['title'],
            'payment_code' => $payment_method['code'],
            
            'shipping_firstname' => $this->extractFirstName($order_data['customer_name']),
            'shipping_lastname' => $this->extractLastName($order_data['customer_name']),
            'shipping_company' => '',
            'shipping_address_1' => $order_data['shipping_address'],
            'shipping_address_2' => '',
            'shipping_city' => $order_data['shipping_city'] ?: 'Москва',
            'shipping_postcode' => $order_data['shipping_postcode'] ?: '000000',
            'shipping_country' => $this->getCountryId($order_data['shipping_country']),
            'shipping_country_id' => $this->getCountryId($order_data['shipping_country']),
            'shipping_zone' => $this->getZoneName($order_data['shipping_region']),
            'shipping_zone_id' => $this->getZoneId($order_data['shipping_region']),
            'shipping_address_format' => '',
            'shipping_custom_field' => [],
            'shipping_method' => $shipping_method['title'],
            'shipping_code' => $shipping_method['code'],
            
            'products' => $products,
            'totals' => $totals,
            
            'comment' => $order_data['notes'] ?? "Заказ импортирован из {$order_data['marketplace']}. Номер: {$order_data['external_order_number']}",
            'total' => $order_data['total_amount'],
            
            'order_status_id' => $this->getDefaultOrderStatusId(),
            'affiliate_id' => 0,
            'commission' => 0,
            'marketing_id' => 0,
            'tracking' => '',
            'language_id' => $this->config->get('config_language_id'),
            'currency_id' => $this->currency->getId(),
            'currency_code' => $this->currency->getCode(),
            'currency_value' => $this->currency->getValue($this->currency->getCode()),
            'ip' => $this->request->server['REMOTE_ADDR'],
            'forwarded_ip' => $this->request->server['HTTP_X_FORWARDED_FOR'] ?? '',
            'user_agent' => $this->request->server['HTTP_USER_AGENT'],
            'accept_language' => $this->request->server['HTTP_ACCEPT_LANGUAGE'] ?? ''
        ];
    }

    /**
     * Найти или создать клиента
     */
    protected function findOrCreateCustomer($order_data) {
        $email = $order_data['customer_email'] ?: 'customer' . time() . '@example.com';
        
        // Пытаемся найти существующего клиента
        $customer_query = $this->db->query("SELECT customer_id FROM " . DB_PREFIX . "customer 
                                          WHERE email = '" . $this->db->escape($email) . "'");
        
        if ($customer_query->num_rows) {
            return $customer_query->row['customer_id'];
        }

        // Создаем нового клиента
        $customer_data = [
            'customer_group_id' => $this->config->get('config_customer_group_id'),
            'firstname' => $this->extractFirstName($order_data['customer_name']),
            'lastname' => $this->extractLastName($order_data['customer_name']),
            'email' => $email,
            'telephone' => $order_data['customer_phone'] ?: '0000000000',
            'fax' => '',
            'password' => bin2hex(openssl_random_pseudo_bytes(8)), // Случайный пароль
            'newsletter' => 0,
            'address' => [
                [
                    'firstname' => $this->extractFirstName($order_data['customer_name']),
                    'lastname' => $this->extractLastName($order_data['customer_name']),
                    'company' => '',
                    'address_1' => $order_data['shipping_address'],
                    'address_2' => '',
                    'city' => $order_data['shipping_city'] ?: 'Москва',
                    'postcode' => $order_data['shipping_postcode'] ?: '000000',
                    'country_id' => $this->getCountryId($order_data['shipping_country']),
                    'zone_id' => $this->getZoneId($order_data['shipping_region']),
                    'default' => true
                ]
            ],
            'custom_field' => []
        ];

        $customer_id = $this->model_customer_customer->addCustomer($customer_data);
        
        return $customer_id;
    }

    /**
     * Подготовить товары заказа
     */
    protected function prepareOrderProducts($items) {
        $products = [];
        
        foreach ($items as $item) {
            $product_id = $this->findProduct($item);
            
            $products[] = [
                'product_id' => $product_id,
                'name' => $item['name'],
                'model' => $item['sku'] ?? '',
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'total' => $item['price'] * $item['quantity'],
                'tax' => 0,
                'reward' => 0
            ];
        }
        
        return $products;
    }

    /**
     * Найти товар по SKU или названию
     */
    protected function findProduct($item) {
        // Сначала ищем по SKU
        if (!empty($item['sku'])) {
            $query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product 
                                      WHERE sku = '" . $this->db->escape($item['sku']) . "' 
                                      OR model = '" . $this->db->escape($item['sku']) . "'");
            if ($query->num_rows) {
                return $query->row['product_id'];
            }
        }

        // Ищем по названию
        $query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product_description 
                                  WHERE name LIKE '%" . $this->db->escape($item['name']) . "%'");
        if ($query->num_rows) {
            return $query->row['product_id'];
        }

        // Если товар не найден, создаем заглушку
        return $this->createDummyProduct($item);
    }

    /**
     * Создать товар-заглушку
     */
    protected function createDummyProduct($item) {
        $product_data = [
            'model' => $item['sku'] ?? 'IMP' . time(),
            'sku' => $item['sku'] ?? '',
            'upc' => '',
            'ean' => '',
            'jan' => '',
            'isbn' => '',
            'mpn' => '',
            'location' => '',
            'quantity' => 999,
            'minimum' => 1,
            'subtract' => 1,
            'stock_status_id' => $this->config->get('config_stock_status_id'),
            'date_available' => date('Y-m-d'),
            'manufacturer_id' => 0,
            'shipping' => 1,
            'price' => $item['price'],
            'points' => 0,
            'weight' => 0,
            'weight_class_id' => 1,
            'length' => 0,
            'width' => 0,
            'height' => 0,
            'length_class_id' => 1,
            'status' => 1,
            'tax_class_id' => 0,
            'sort_order' => 0,
            'product_description' => [
                $this->config->get('config_language_id') => [
                    'name' => $item['name'],
                    'description' => 'Товар импортирован автоматически',
                    'tag' => '',
                    'meta_title' => $item['name'],
                    'meta_description' => '',
                    'meta_keyword' => ''
                ]
            ]
        ];

        $this->load->model('catalog/product');
        $product_id = $this->model_catalog_product->addProduct($product_data);
        
        return $product_id;
    }

    /**
     * Вспомогательные методы (извлечение имени, работа со странами и т.д.)
     */
    protected function extractFirstName($full_name) {
        $parts = explode(' ', $full_name);
        return $parts[0] ?: 'Customer';
    }

    protected function extractLastName($full_name) {
        $parts = explode(' ', $full_name);
        return count($parts) > 1 ? $parts[1] : 'Unknown';
    }

    protected function getCountryId($country_name) {
        if (!$country_name) return $this->config->get('config_country_id');
        
        $query = $this->db->query("SELECT country_id FROM " . DB_PREFIX . "country 
                                  WHERE name LIKE '%" . $this->db->escape($country_name) . "%'");
        return $query->num_rows ? $query->row['country_id'] : $this->config->get('config_country_id');
    }

    protected function getZoneId($zone_name) {
        if (!$zone_name) return $this->config->get('config_zone_id');
        
        $query = $this->db->query("SELECT zone_id FROM " . DB_PREFIX . "zone 
                                  WHERE name LIKE '%" . $this->db->escape($zone_name) . "%'");
        return $query->num_rows ? $query->row['zone_id'] : $this->config->get('config_zone_id');
    }

    protected function getZoneName($zone_name) {
        if (!$zone_name) return '';
        
        $query = $this->db->query("SELECT name FROM " . DB_PREFIX . "zone 
                                  WHERE name LIKE '%" . $this->db->escape($zone_name) . "%'");
        return $query->num_rows ? $query->row['name'] : $zone_name;
    }

    protected function getCustomerGroupId($customer_id) {
        if ($customer_id) {
            $query = $this->db->query("SELECT customer_group_id FROM " . DB_PREFIX . "customer 
                                      WHERE customer_id = '" . (int)$customer_id . "'");
            return $query->num_rows ? $query->row['customer_group_id'] : $this->config->get('config_customer_group_id');
        }
        return $this->config->get('config_customer_group_id');
    }

    protected function getDefaultOrderStatusId() {
        return $this->config->get('config_order_status_id') ?: 1;
    }

    protected function mapShippingMethod($method) {
        $mapping = [
            'courier' => ['title' => 'Курьерская доставка', 'code' => 'courier'],
            'pickup' => ['title' => 'Самовывоз', 'code' => 'pickup'],
            'post' => ['title' => 'Почта России', 'code' => 'russianpost']
        ];
        return $mapping[$method] ?? ['title' => 'Доставка', 'code' => 'flat'];
    }

    protected function mapPaymentMethod($method) {
        $mapping = [
            'card' => ['title' => 'Онлайн оплата', 'code' => 'bank_transfer'],
            'cash' => ['title' => 'Наличные', 'code' => 'cod'],
            'prepaid' => ['title' => 'Предоплата', 'code' => 'prepayment']
        ];
        return $mapping[$method] ?? ['title' => 'Оплата', 'code' => 'bank_transfer'];
    }

    protected function prepareOrderTotals($order_data) {
        return [
            [
                'code' => 'sub_total',
                'title' => 'Сумма',
                'value' => $order_data['total_amount'] - $order_data['shipping_cost'],
                'sort_order' => 1
            ],
            [
                'code' => 'shipping',
                'title' => 'Доставка',
                'value' => $order_data['shipping_cost'],
                'sort_order' => 2
            ],
            [
                'code' => 'total',
                'title' => 'Итого',
                'value' => $order_data['total_amount'],
                'sort_order' => 3
            ]
        ];
    }

    protected function linkOrders($shtab_order_id, $opencart_order_id) {
        $this->db->query("UPDATE " . DB_PREFIX . "shtab_order 
                         SET opencart_order_id = '" . (int)$opencart_order_id . "',
                             is_processed = 1 
                         WHERE shtab_order_id = '" . (int)$shtab_order_id . "'");
    }

    protected function updateOrderStatus($shtab_order_id, $status) {
        $this->db->query("UPDATE " . DB_PREFIX . "shtab_order 
                         SET order_status = '" . $this->db->escape($status) . "'
                         WHERE shtab_order_id = '" . (int)$shtab_order_id . "'");
    }

    protected function getOrderData($shtab_order_id) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "shtab_order 
                                  WHERE shtab_order_id = '" . (int)$shtab_order_id . "'");

        if ($query->num_rows) {
            $order = $query->row;
            $order['items'] = json_decode($order['items'], true);
            return $order;
        }
        return null;
    }

    // Магический метод для загрузки моделей
    public function __get($key) {
        return $this->registry->get($key);
    }
}
?>