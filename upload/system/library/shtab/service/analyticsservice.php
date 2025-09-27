<?php
class ShtabAnalyticsService {
    protected $registry;
    protected $db;
    protected $log;

    public function __construct($registry) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->log = $registry->get('log');
    }

    /**
     * Расчет ключевых метрик за период
     */
    public function calculateMetrics($date_from, $date_to, $marketplace = null) {
        $metrics = [];
        
        // Продажи
        $metrics['sales'] = $this->calculateSales($date_from, $date_to, $marketplace);
        
        // Прибыль
        $metrics['profit'] = $this->calculateProfit($date_from, $date_to, $marketplace);
        
        // ROI
        $metrics['roi'] = $this->calculateROI($date_from, $date_to, $marketplace);
        
        // Количество заказов
        $metrics['orders'] = $this->calculateOrders($date_from, $date_to, $marketplace);
        
        // Средний чек
        $metrics['average_order_value'] = $metrics['orders']['count'] > 0 
            ? $metrics['sales']['total'] / $metrics['orders']['count'] 
            : 0;

        return $metrics;
    }

    /**
     * Расчет продаж
     */
    protected function calculateSales($date_from, $date_to, $marketplace = null) {
        $sql = "SELECT SUM(total_amount) as total_sales, COUNT(*) as order_count
                FROM " . DB_PREFIX . "shtab_order 
                WHERE order_date BETWEEN '" . $this->db->escape($date_from) . "' 
                AND '" . $this->db->escape($date_to) . "'";
        
        if ($marketplace) {
            $sql .= " AND marketplace = '" . $this->db->escape($marketplace) . "'";
        }

        $query = $this->db->query($sql);
        
        return [
            'total' => $query->row['total_sales'] ?? 0,
            'count' => $query->row['order_count'] ?? 0
        ];
    }

    /**
     * Расчет прибыли
     */
    protected function calculateProfit($date_from, $date_to, $marketplace = null) {
        // Сначала получаем заказы за период
        $sql = "SELECT so.shtab_order_id, so.total_amount, so.commission, so.shipping_cost, soi.items
                FROM " . DB_PREFIX . "shtab_order so
                WHERE so.order_date BETWEEN '" . $this->db->escape($date_from) . "' 
                AND '" . $this->db->escape($date_to) . "'";
        
        if ($marketplace) {
            $sql .= " AND so.marketplace = '" . $this->db->escape($marketplace) . "'";
        }

        $query = $this->db->query($sql);
        $total_revenue = 0;
        $total_cost = 0;

        foreach ($query->rows as $order) {
            $items = json_decode($order['items'], true);
            $revenue = $order['total_amount'];
            $cost = 0;

            // Рассчитываем себестоимость товаров в заказе
            foreach ($items as $item) {
                $product_cost = $this->getProductCost($item['product_id'] ?? 0);
                $cost += $product_cost * $item['quantity'];
            }

            // Добавляем комиссию и стоимость доставки
            $cost += $order['commission'] + $order['shipping_cost'];

            $total_revenue += $revenue;
            $total_cost += $cost;
        }

        return [
            'revenue' => $total_revenue,
            'cost' => $total_cost,
            'profit' => $total_revenue - $total_cost,
            'margin' => $total_revenue > 0 ? (($total_revenue - $total_cost) / $total_revenue) * 100 : 0
        ];
    }

    /**
     * Расчет ROI
     */
    protected function calculateROI($date_from, $date_to, $marketplace = null) {
        $profit_data = $this->calculateProfit($date_from, $date_to, $marketplace);
        
        // Предполагаем, что инвестиции = себестоимость + дополнительные расходы
        $investment = $profit_data['cost'];
        
        $roi = $investment > 0 ? ($profit_data['profit'] / $investment) * 100 : 0;

        return [
            'investment' => $investment,
            'profit' => $profit_data['profit'],
            'roi_percent' => $roi
        ];
    }

    /**
     * Расчет количества заказов
     */
    protected function calculateOrders($date_from, $date_to, $marketplace = null) {
        $sql = "SELECT COUNT(*) as order_count 
                FROM " . DB_PREFIX . "shtab_order 
                WHERE order_date BETWEEN '" . $this->db->escape($date_from) . "' 
                AND '" . $this->db->escape($date_to) . "'";
        
        if ($marketplace) {
            $sql .= " AND marketplace = '" . $this->db->escape($marketplace) . "'";
        }

        $query = $this->db->query($sql);
        
        return [
            'count' => $query->row['order_count'] ?? 0
        ];
    }

    /**
     * RFM-анализ клиентов
     */
    public function performRFMAnalysis($analysis_date = null) {
        if (!$analysis_date) {
            $analysis_date = date('Y-m-d');
        }

        // Удаляем старый анализ для этой даты
        $this->db->query("DELETE FROM " . DB_PREFIX . "shtab_customer_rfm 
                         WHERE analysis_date = '" . $this->db->escape($analysis_date) . "'");

        // Получаем данные по клиентам
        $sql = "SELECT 
                    customer_email,
                    customer_name,
                    MAX(order_date) as last_order_date,
                    COUNT(*) as order_count,
                    SUM(total_amount) as total_spent
                FROM " . DB_PREFIX . "shtab_order 
                WHERE order_date <= '" . $this->db->escape($analysis_date) . "'
                GROUP BY customer_email, customer_name";

        $query = $this->db->query($sql);

        foreach ($query->rows as $customer) {
            $recency = $this->calculateRecency($customer['last_order_date'], $analysis_date);
            $frequency = $this->calculateFrequency($customer['order_count']);
            $monetary = $this->calculateMonetary($customer['total_spent']);
            
            $rfm_score = $recency['score'] . $frequency['score'] . $monetary['score'];
            $rfm_segment = $this->getRFMSegment($rfm_score);

            $this->db->query("INSERT INTO " . DB_PREFIX . "shtab_customer_rfm 
                SET customer_name = '" . $this->db->escape($customer['customer_name']) . "',
                    customer_email = '" . $this->db->escape($customer['customer_email']) . "',
                    recency = '" . (int)$recency['days'] . "',
                    frequency = '" . (int)$customer['order_count'] . "',
                    monetary = '" . (float)$customer['total_spent'] . "',
                    rfm_score = '" . $this->db->escape($rfm_score) . "',
                    rfm_segment = '" . $this->db->escape($rfm_segment) . "',
                    last_order_date = '" . $this->db->escape($customer['last_order_date']) . "',
                    analysis_date = '" . $this->db->escape($analysis_date) . "'");
        }

        return $query->num_rows;
    }

    /**
     * Расчет Recency (давность)
     */
    protected function calculateRecency($last_order_date, $analysis_date) {
        $days = round((strtotime($analysis_date) - strtotime($last_order_date)) / (60 * 60 * 24));
        
        // Оценка от 1 до 5 (5 - самые активные)
        if ($days <= 30) $score = 5;
        elseif ($days <= 60) $score = 4;
        elseif ($days <= 90) $score = 3;
        elseif ($days <= 180) $score = 2;
        else $score = 1;

        return ['days' => $days, 'score' => $score];
    }

    /**
     * Расчет Frequency (частота)
     */
    protected function calculateFrequency($order_count) {
        if ($order_count >= 10) $score = 5;
        elseif ($order_count >= 5) $score = 4;
        elseif ($order_count >= 3) $score = 3;
        elseif ($order_count >= 2) $score = 2;
        else $score = 1;

        return ['count' => $order_count, 'score' => $score];
    }

    /**
     * Расчет Monetary (деньги)
     */
    protected function calculateMonetary($total_spent) {
        if ($total_spent >= 50000) $score = 5;
        elseif ($total_spent >= 25000) $score = 4;
        elseif ($total_spent >= 10000) $score = 3;
        elseif ($total_spent >= 5000) $score = 2;
        else $score = 1;

        return ['amount' => $total_spent, 'score' => $score];
    }

    /**
     * Определение RFM-сегмента
     */
    protected function getRFMSegment($rfm_score) {
        $segments = [
            '555' => 'Champions', '554' => 'Champions', '545' => 'Champions', '455' => 'Champions',
            '555' => 'Loyal Customers', '554' => 'Loyal Customers', '544' => 'Loyal Customers',
            '555' => 'Potential Loyalist', '455' => 'Potential Loyalist', '445' => 'Potential Loyalist',
            '555' => 'New Customers', '255' => 'New Customers', '155' => 'New Customers',
            '555' => 'At Risk', '551' => 'At Risk', '552' => 'At Risk',
            '555' => 'Cannot Lose Them', '115' => 'Cannot Lose Them', '125' => 'Cannot Lose Them'
        ];

        return $segments[$rfm_score] ?? 'Other';
    }

    /**
     * Получить себестоимость товара
     */
    protected function getProductCost($product_id) {
        $query = $this->db->query("SELECT cost_price FROM " . DB_PREFIX . "shtab_product 
                                  WHERE product_id = '" . (int)$product_id . "'");
        
        return $query->num_rows ? (float)$query->row['cost_price'] : 0;
    }

    /**
     * Генерация отчета по ROI
     */
    public function generateROIReport($date_from, $date_to) {
        $marketplaces = $this->getActiveMarketplaces();
        $report = [];

        foreach ($marketplaces as $marketplace) {
            $roi_data = $this->calculateROI($date_from, $date_to, $marketplace);
            $sales_data = $this->calculateSales($date_from, $date_to, $marketplace);
            
            $report[$marketplace] = [
                'marketplace' => $marketplace,
                'revenue' => $sales_data['total'],
                'orders' => $sales_data['count'],
                'investment' => $roi_data['investment'],
                'profit' => $roi_data['profit'],
                'roi_percent' => $roi_data['roi_percent'],
                'average_order_value' => $sales_data['count'] > 0 ? $sales_data['total'] / $sales_data['count'] : 0
            ];
        }

        return $report;
    }

    /**
     * Получить активные маркетплейсы
     */
    protected function getActiveMarketplaces() {
        $query = $this->db->query("SELECT DISTINCT marketplace FROM " . DB_PREFIX . "shtab_order");
        return array_column($query->rows, 'marketplace');
    }
}
?>