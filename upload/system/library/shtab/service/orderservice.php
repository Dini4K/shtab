<?php
class ShtabOrderService {
    protected $registry;
    protected $db;
    protected $log;

    public function __construct($registry) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->log = $registry->get('log');
    }

    /**
     * Импорт заказов с маркетплейса
     */
    public function importOrders($marketplace, $params = []) {
        $serviceManager = new ShtabServiceManager($this->registry);
        $marketplaceService = $serviceManager->marketplace();
        
        try {
            $orders = $marketplaceService->getOrders($marketplace, $params);
            $imported = 0;
            $updated = 0;

            foreach ($orders as $order_data) {
                $result = $this->saveOrder($marketplace, $order_data);
                if ($result === 'inserted') {
                    $imported++;
                } elseif ($result === 'updated') {
                    $updated++;
                }
            }

            $this->log->write("SHTAB ORDERS: Импорт с $marketplace - новых: $imported, обновлено: $updated");

            return [
                'success' => true,
                'imported' => $imported,
                'updated' => $updated,
                'total' => count($orders)
            ];

        } catch (Exception $e) {
            $this->log->write("SHTAB ORDERS ERROR: Импорт с $marketplace - " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Сохранить заказ в унифицированном формате
     */
    public function saveOrder($marketplace, $order_data) {
        // Нормализуем данные заказа
        $normalized = $this->normalizeOrderData($marketplace, $order_data);
        
        // Проверяем существование заказа
        $existing = $this->db->query("SELECT shtab_order_id FROM " . DB_PREFIX . "shtab_order 
                                    WHERE marketplace = '" . $this->db->escape($marketplace) . "' 
                                    AND external_order_id = '" . $this->db->escape($normalized['external_order_id']) . "'");

        if ($existing->num_rows) {
            // Обновляем существующий заказ
            $this->updateOrder($existing->row['shtab_order_id'], $normalized);
            return 'updated';
        } else {
            // Создаем новый заказ
            $this->insertOrder($normalized);
            return 'inserted';
        }
    }

    /**
     * Нормализовать данные заказа из разных маркетплейсов
     */
    protected function normalizeOrderData($marketplace, $order_data) {
        $normalizer = $this->getOrderNormalizer($marketplace);
        return $normalizer->normalize($order_data);
    }

    /**
     * Создать заказ в OpenCart
     */
    public function createOpencartOrder($shtab_order_id) {
        $order_data = $this->getOrder($shtab_order_id);
        if (!$order_data) {
            throw new Exception('Заказ не найден');
        }

        // Подготавливаем данные для OpenCart
        $opencart_data = $this->prepareOpencartOrderData($order_data);

        // Создаем заказ через модель OpenCart
        $this->load->model('checkout/order');
        $opencart_order_id = $this->model_checkout_order->addOrder($opencart_data);

        if ($opencart_order_id) {
            // Связываем заказы
            $this->db->query("UPDATE " . DB_PREFIX . "shtab_order 
                            SET opencart_order_id = '" . (int)$opencart_order_id . "',
                                is_processed = 1 
                            WHERE shtab_order_id = '" . (int)$shtab_order_id . "'");

            $this->log->write("SHTAB: Создан заказ OpenCart #$opencart_order_id для заказа #$shtab_order_id");

            return $opencart_order_id;
        }

        throw new Exception('Ошибка создания заказа в OpenCart');
    }

    /**
     * Получить заказ по ID
     */
    public function getOrder($shtab_order_id) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "shtab_order 
                                  WHERE shtab_order_id = '" . (int)$shtab_order_id . "'");

        if ($query->num_rows) {
            $order = $query->row;
            $order['items'] = json_decode($order['items'], true);
            return $order;
        }

        return null;
    }

    /**
     * Получить список заказов с фильтрами
     */
    public function getOrders($filters = []) {
        $sql = "SELECT * FROM " . DB_PREFIX . "shtab_order WHERE 1=1";
        
        if (isset($filters['marketplace'])) {
            $sql .= " AND marketplace = '" . $this->db->escape($filters['marketplace']) . "'";
        }
        
        if (isset($filters['status'])) {
            $sql .= " AND order_status = '" . $this->db->escape($filters['status']) . "'";
        }
        
        if (isset($filters['date_from'])) {
            $sql .= " AND order_date >= '" . $this->db->escape($filters['date_from']) . "'";
        }
        
        if (isset($filters['date_to'])) {
            $sql .= " AND order_date <= '" . $this->db->escape($filters['date_to']) . "'";
        }
        
        if (isset($filters['is_processed'])) {
            $sql .= " AND is_processed = '" . (int)$filters['is_processed'] . "'";
        }

        $sql .= " ORDER BY order_date DESC";

        if (isset($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }

        $query = $this->db->query($sql);
        $orders = [];

        foreach ($query->rows as $row) {
            $row['items'] = json_decode($row['items'], true);
            $orders[] = $row;
        }

        return $orders;
    }

    /**
     * Вставить новый заказ
     */
    protected function insertOrder($order_data) {
        $this->db->query("INSERT INTO " . DB_PREFIX . "shtab_order 
            SET marketplace = '" . $this->db->escape($order_data['marketplace']) . "',
                external_order_id = '" . $this->db->escape($order_data['external_order_id']) . "',
                external_order_number = '" . $this->db->escape($order_data['external_order_number']) . "',
                order_status = '" . $this->db->escape($order_data['order_status']) . "',
                order_date = '" . $this->db->escape($order_data['order_date']) . "',
                customer_name = '" . $this->db->escape($order_data['customer_name']) . "',
                customer_phone = '" . $this->db->escape($order_data['customer_phone']) . "',
                customer_email = '" . $this->db->escape($order_data['customer_email']) . "',
                shipping_address = '" . $this->db->escape($order_data['shipping_address']) . "',
                shipping_city = '" . $this->db->escape($order_data['shipping_city']) . "',
                shipping_region = '" . $this->db->escape($order_data['shipping_region']) . "',
                shipping_postcode = '" . $this->db->escape($order_data['shipping_postcode']) . "',
                shipping_country = '" . $this->db->escape($order_data['shipping_country']) . "',
                shipping_method = '" . $this->db->escape($order_data['shipping_method']) . "',
                payment_method = '" . $this->db->escape($order_data['payment_method']) . "',
                total_amount = '" . (float)$order_data['total_amount'] . "',
                shipping_cost = '" . (float)$order_data['shipping_cost'] . "',
                commission = '" . (float)$order_data['commission'] . "',
                items = '" . $this->db->escape(json_encode($order_data['items'])) . "',
                last_sync = NOW(),
                date_added = NOW()");
    }

    /**
     * Обновить существующий заказ
     */
    protected function updateOrder($shtab_order_id, $order_data) {
        $this->db->query("UPDATE " . DB_PREFIX . "shtab_order 
            SET order_status = '" . $this->db->escape($order_data['order_status']) . "',
                customer_name = '" . $this->db->escape($order_data['customer_name']) . "',
                customer_phone = '" . $this->db->escape($order_data['customer_phone']) . "',
                shipping_address = '" . $this->db->escape($order_data['shipping_address']) . "',
                total_amount = '" . (float)$order_data['total_amount'] . "',
                items = '" . $this->db->escape(json_encode($order_data['items'])) . "',
                last_sync = NOW(),
                date_modified = NOW()
            WHERE shtab_order_id = '" . (int)$shtab_order_id . "'");
    }

    /**
     * Получить нормалайзер для маркетплейса
     */
    protected function getOrderNormalizer($marketplace) {
        $normalizerClass = 'Shtab' . ucfirst($marketplace) . 'OrderNormalizer';
        $normalizerFile = DIR_SYSTEM . 'library/shtab/order/normalizer/' . strtolower($normalizerClass) . '.php';

        if (!file_exists($normalizerFile)) {
            // Используем базовый нормалайзер
            require_once(DIR_SYSTEM . 'library/shtab/order/normalizer/baseordernormalizer.php');
            return new ShtabBaseOrderNormalizer($this->registry);
        }

        require_once($normalizerFile);
        return new $normalizerClass($this->registry);
    }

    /**
     * Подготовить данные для создания заказа в OpenCart
     */
    protected function prepareOpencartOrderData($order_data) {
        // Эта функция будет реализована в полной версии
        // Пока возвращаем заглушку
        return [
            'invoice_prefix' => 'SH',
            'store_id' => 0,
            'store_name' => $this->config->get('config_name'),
            'customer_id' => 0,
            'customer_group_id' => 1,
            'firstname' => $order_data['customer_name'],
            'email' => $order_data['customer_email'],
            'telephone' => $order_data['customer_phone'],
            'payment_firstname' => $order_data['customer_name'],
            'payment_address_1' => $order_data['shipping_address'],
            'payment_city' => $order_data['shipping_city'],
            'payment_country' => $order_data['shipping_country'],
            'shipping_firstname' => $order_data['customer_name'],
            'shipping_address_1' => $order_data['shipping_address'],
            'shipping_city' => $order_data['shipping_city'],
            'shipping_country' => $order_data['shipping_country'],
            'total' => $order_data['total_amount'],
            'order_status_id' => 1
        ];
    }
}
?>