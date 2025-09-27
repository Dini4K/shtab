<?php
class ShtabProductService {
    protected $db;
    protected $log;
    protected $table_product;
    protected $table_product_to_mp;

    public function __construct($registry) {
        $this->db = $registry->get('db');
        $this->log = $registry->get('log');
        $this->table_product = DB_PREFIX . 'shtab_product';
        $this->table_product_to_mp = DB_PREFIX . 'shtab_product_to_marketplace';
    }

    /**
     * Получить данные товара для репрайсинга
     */
    public function getProductData($product_id) {
        $query = $this->db->query("SELECT * FROM " . $this->table_product . " WHERE product_id = '" . (int)$product_id . "'");
        
        if ($query->num_rows) {
            $data = $query->row;
            if ($data['custom_data']) {
                $data['custom_data'] = json_decode($data['custom_data'], true);
            }
            return $data;
        }

        return null;
    }

    /**
     * Сохранить данные товара
     */
    public function saveProductData($product_id, $data) {
        $fields = [
            'cost_price' => isset($data['cost_price']) ? (float)$data['cost_price'] : 0,
            'min_price' => isset($data['min_price']) ? (float)$data['min_price'] : 0,
            'max_price' => isset($data['max_price']) ? (float)$data['max_price'] : 0,
            'rrp_price' => isset($data['rrp_price']) ? (float)$data['rrp_price'] : 0,
            'repricing_enabled' => isset($data['repricing_enabled']) ? (int)$data['repricing_enabled'] : 1,
            'custom_data' => isset($data['custom_data']) ? json_encode($data['custom_data']) : null
        ];

        $existing = $this->getProductData($product_id);
        
        if ($existing) {
            // Обновляем существующую запись
            $set_parts = [];
            foreach ($fields as $key => $value) {
                $set_parts[] = "`$key` = '" . $this->db->escape($value) . "'";
            }
            
            $this->db->query("UPDATE " . $this->table_product . " SET " . implode(', ', $set_parts) . " WHERE product_id = '" . (int)$product_id . "'");
        } else {
            // Создаем новую запись
            $fields['product_id'] = (int)$product_id;
            $columns = array_keys($fields);
            $values = array_values($fields);
            
            $this->db->query("INSERT INTO " . $this->table_product . " (`" . implode('`, `', $columns) . "`) VALUES ('" . implode("', '", $values) . "')");
        }

        return true;
    }

    /**
     * Получить привязки товара к маркетплейсам
     */
    public function getProductMarketplaces($product_id) {
        $query = $this->db->query("SELECT * FROM " . $this->table_product_to_mp . " WHERE product_id = '" . (int)$product_id . "'");
        
        $result = [];
        foreach ($query->rows as $row) {
            if ($row['custom_data']) {
                $row['custom_data'] = json_decode($row['custom_data'], true);
            }
            $result[$row['marketplace']] = $row;
        }

        return $result;
    }

    /**
     * Сохранить привязку к маркетплейсу
     */
    public function saveProductMarketplace($product_id, $marketplace, $data) {
        $fields = [
            'product_id' => (int)$product_id,
            'marketplace' => $this->db->escape($marketplace),
            'external_id' => isset($data['external_id']) ? $this->db->escape($data['external_id']) : '',
            'external_sku' => isset($data['external_sku']) ? $this->db->escape($data['external_sku']) : '',
            'price' => isset($data['price']) ? (float)$data['price'] : 0,
            'old_price' => isset($data['old_price']) ? (float)$data['old_price'] : 0,
            'stock' => isset($data['stock']) ? (int)$data['stock'] : 0,
            'status' => isset($data['status']) ? $this->db->escape($data['status']) : 'active',
            'custom_data' => isset($data['custom_data']) ? json_encode($data['custom_data']) : null
        ];

        $existing = $this->db->query("SELECT id FROM " . $this->table_product_to_mp . " WHERE product_id = '" . (int)$product_id . "' AND marketplace = '" . $fields['marketplace'] . "'");

        if ($existing->num_rows) {
            // Обновляем
            $set_parts = [];
            foreach ($fields as $key => $value) {
                if ($key !== 'product_id' && $key !== 'marketplace') {
                    $set_parts[] = "`$key` = '" . $this->db->escape($value) . "'";
                }
            }
            $set_parts[] = "last_sync = NOW()";
            
            $this->db->query("UPDATE " . $this->table_product_to_mp . " SET " . implode(', ', $set_parts) . " WHERE product_id = '" . (int)$product_id . "' AND marketplace = '" . $fields['marketplace'] . "'");
        } else {
            // Создаем
            $columns = array_keys($fields);
            $values = array_values($fields);
            $columns[] = 'last_sync';
            $values[] = 'NOW()';
            
            $this->db->query("INSERT INTO " . $this->table_product_to_mp . " (`" . implode('`, `', $columns) . "`) VALUES ('" . implode("', '", $values) . "')");
        }

        return true;
    }

    /**
     * Получить товары для репрайсинга
     */
    public function getProductsForRepricing($filters = []) {
        $sql = "SELECT p.*, pd.name, mp.marketplace, mp.external_id, mp.price as marketplace_price 
                FROM " . $this->table_product . " p 
                LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "')
                LEFT JOIN " . $this->table_product_to_mp . " mp ON (p.product_id = mp.product_id) 
                WHERE p.repricing_enabled = 1";

        if (isset($filters['marketplace'])) {
            $sql .= " AND mp.marketplace = '" . $this->db->escape($filters['marketplace']) . "'";
        }

        $sql .= " ORDER BY p.last_updated DESC";

        if (isset($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }

        $query = $this->db->query($sql);
        return $query->rows;
    }
}
?>