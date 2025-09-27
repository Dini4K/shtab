<?php
class ShtabSupplierService {
    protected $registry;
    protected $db;
    protected $log;

    public function __construct($registry) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->log = $registry->get('log');
    }

    /**
     * Получить список поставщиков
     */
    public function getSuppliers($filters = []) {
        $sql = "SELECT * FROM " . DB_PREFIX . "shtab_supplier WHERE 1=1";
        
        if (isset($filters['is_active'])) {
            $sql .= " AND is_active = '" . (int)$filters['is_active'] . "'";
        }
        
        if (isset($filters['search'])) {
            $sql .= " AND name LIKE '%" . $this->db->escape($filters['search']) . "%'";
        }

        $sql .= " ORDER BY name ASC";

        if (isset($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }

        $query = $this->db->query($sql);
        $suppliers = [];

        foreach ($query->rows as $row) {
            if ($row['bank_details']) {
                $row['bank_details'] = json_decode($row['bank_details'], true);
            }
            $row['rating'] = $this->getSupplierRating($row['supplier_id']);
            $suppliers[] = $row;
        }

        return $suppliers;
    }

    /**
     * Получить рейтинг поставщика
     */
    public function getSupplierRating($supplier_id) {
        $query = $this->db->query("SELECT AVG(rating) as avg_rating, COUNT(*) as rating_count 
                                  FROM " . DB_PREFIX . "shtab_supplier_rating 
                                  WHERE supplier_id = '" . (int)$supplier_id . "'");
        
        return [
            'average' => $query->num_rows ? round($query->row['avg_rating'], 2) : 0,
            'count' => $query->row['rating_count'] ?? 0
        ];
    }

    /**
     * Добавить оценку поставщику
     */
    public function addRating($supplier_id, $rating_data) {
        $this->db->query("INSERT INTO " . DB_PREFIX . "shtab_supplier_rating 
            SET supplier_id = '" . (int)$supplier_id . "',
                order_id = '" . (isset($rating_data['order_id']) ? (int)$rating_data['order_id'] : 0) . "',
                rating = '" . (int)$rating_data['rating'] . "',
                comment = '" . $this->db->escape($rating_data['comment']) . "',
                rating_type = '" . $this->db->escape($rating_data['rating_type']) . "',
                rated_by = '" . $this->db->escape($rating_data['rated_by']) . "',
                date_added = NOW()");

        // Обновляем общий рейтинг поставщика
        $this->updateSupplierReliability($supplier_id);

        return $this->db->getLastId();
    }

    /**
     * Обновить надежность поставщика
     */
    protected function updateSupplierReliability($supplier_id) {
        $rating = $this->getSupplierRating($supplier_id);
        $this->db->query("UPDATE " . DB_PREFIX . "shtab_supplier 
                         SET reliability_score = '" . (float)$rating['average'] . "'
                         WHERE supplier_id = '" . (int)$supplier_id . "'");
    }
}
?>