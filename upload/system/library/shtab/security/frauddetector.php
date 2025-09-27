<?php
class ShtabFraudDetector {
    protected $registry;
    protected $db;
    protected $log;

    public function __construct($registry) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->log = $registry->get('log');
    }

    /**
     * Проверка заказа на мошенничество
     */
    public function analyzeOrder($order_data) {
        $risk_score = 0;
        $red_flags = [];

        // 1. Проверка IP адреса
        $ip_risk = $this->checkIPRisk($order_data['ip_address']);
        if ($ip_risk > 0) {
            $risk_score += $ip_risk;
            $red_flags[] = 'Подозрительный IP адрес';
        }

        // 2. Проверка поведения пользователя
        $behavior_risk = $this->checkUserBehavior($order_data);
        if ($behavior_risk > 0) {
            $risk_score += $behavior_risk;
            $red_flags[] = 'Подозрительное поведение';
        }

        // 3. Проверка суммы заказа
        $amount_risk = $this->checkOrderAmount($order_data['total_amount']);
        if ($amount_risk > 0) {
            $risk_score += $amount_risk;
            $red_flags[] = 'Необычная сумма заказа';
        }

        // 4. Проверка адреса доставки
        $address_risk = $this->checkDeliveryAddress($order_data['shipping_address']);
        if ($address_risk > 0) {
            $risk_score += $address_risk;
            $red_flags[] = 'Подозрительный адрес доставки';
        }

        // 5. Проверка скорости заказов
        $velocity_risk = $this->checkOrderVelocity($order_data);
        if ($velocity_risk > 0) {
            $risk_score += $velocity_risk;
            $red_flags[] = 'Высокая скорость заказов';
        }

        $result = [
            'risk_score' => $risk_score,
            'risk_level' => $this->getRiskLevel($risk_score),
            'red_flags' => $red_flags,
            'recommendation' => $this->getRecommendation($risk_score),
            'is_high_risk' => $risk_score >= 70
        ];

        // Логируем результат проверки
        $this->logSecurityEvent('order_fraud_check', $result, $order_data);

        return $result;
    }

    /**
     * Проверка риска IP адреса
     */
    protected function checkIPRisk($ip_address) {
        // Проверка VPN/Proxy
        if ($this->isVPNorProxy($ip_address)) {
            return 30;
        }

        // Проверка геолокации
        $country = $this->getIPCountry($ip_address);
        if (in_array($country, ['CN', 'RU', 'UA', 'TR'])) { // Рисковые страны
            return 20;
        }

        // Проверка истории IP
        $ip_history = $this->getIPHistory($ip_address);
        if ($ip_history['order_count'] > 10) {
            return 15;
        }

        return 0;
    }

    /**
     * Проверка поведения пользователя
     */
    protected function checkUserBehavior($order_data) {
        $risk = 0;

        // Быстрое заполнение формы (< 30 секунд)
        if (isset($order_data['form_fill_time']) && $order_data['form_fill_time'] < 30) {
            $risk += 20;
        }

        // Использование временной почты
        if ($this->isTemporaryEmail($order_data['customer_email'])) {
            $risk += 25;
        }

        // Несоответствие региона IP и адреса доставки
        if ($this->checkRegionMismatch($order_data)) {
            $risk += 15;
        }

        return $risk;
    }

    /**
     * Проверка суммы заказа
     */
    protected function checkOrderAmount($amount) {
        // Сравнение со средним чеком
        $avg_order_value = $this->getAverageOrderValue();
        
        if ($amount > $avg_order_value * 3) {
            return 20;
        }

        if ($amount < 500) { // Слишком маленький заказ
            return 10;
        }

        return 0;
    }

    /**
     * Проверка адреса доставки
     */
    protected function checkDeliveryAddress($address) {
        // Проверка на адрес склада или почтового отделения
        $suspicious_patterns = [
            '/почтомат/i', '/пункт выдачи/i', '/склад/i', '/до востребования/i'
        ];

        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $address)) {
                return 15;
            }
        }

        return 0;
    }

    /**
     * Проверка скорости заказов
     */
    protected function checkOrderVelocity($order_data) {
        // Проверка количества заказов за последний час
        $recent_orders = $this->getRecentOrders($order_data['customer_email'], 60);
        
        if ($recent_orders >= 3) {
            return 25;
        }

        return 0;
    }

    /**
     * Определение уровня риска
     */
    protected function getRiskLevel($score) {
        if ($score >= 80) return 'critical';
        if ($score >= 60) return 'high';
        if ($score >= 40) return 'medium';
        if ($score >= 20) return 'low';
        return 'very_low';
    }

    /**
     * Рекомендация по результатам проверки
     */
    protected function getRecommendation($score) {
        if ($score >= 80) return 'Блокировать заказ и уведомить администратора';
        if ($score >= 60) return 'Требовать дополнительную верификацию';
        if ($score >= 40) return 'Вручную проверить заказ';
        if ($score >= 20) return 'Обычная проверка';
        return 'Автоматическое подтверждение';
    }

    /**
     * Логирование события безопасности
     */
    protected function logSecurityEvent($event_type, $result, $context = []) {
        $this->db->query("INSERT INTO " . DB_PREFIX . "shtab_security_log 
            SET event_type = '" . $this->db->escape($event_type) . "',
                severity = '" . $this->db->escape($result['risk_level']) . "',
                ip_address = '" . $this->db->escape($context['ip_address'] ?? '') . "',
                user_agent = '" . $this->db->escape($context['user_agent'] ?? '') . "',
                request_data = '" . $this->db->escape(json_encode($context)) . "',
                description = '" . $this->db->escape('Проверка заказа на мошенничество') . "',
                suspected_fraud = '" . (int)$result['is_high_risk'] . "',
                action_taken = '" . $this->db->escape($result['recommendation']) . "',
                date_added = NOW()");
    }

    // Вспомогательные методы
    protected function isVPNorProxy($ip) {
        // Упрощенная проверка - в реальности использовать API
        $vpn_ranges = ['185.','192.','193.']; // Примеры диапазонов
        foreach ($vpn_ranges as $range) {
            if (strpos($ip, $range) === 0) {
                return true;
            }
        }
        return false;
    }

    protected function isTemporaryEmail($email) {
        $temp_domains = ['tempmail.com', '10minutemail.com', 'guerrillamail.com'];
        $domain = explode('@', $email)[1] ?? '';
        return in_array($domain, $temp_domains);
    }

    protected function getAverageOrderValue() {
        $query = $this->db->query("SELECT AVG(total_amount) as avg_value 
                                  FROM " . DB_PREFIX . "shtab_order 
                                  WHERE order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        return $query->num_rows ? $query->row['avg_value'] : 5000;
    }

    protected function getRecentOrders($email, $minutes = 60) {
        $query = $this->db->query("SELECT COUNT(*) as order_count 
                                  FROM " . DB_PREFIX . "shtab_order 
                                  WHERE customer_email = '" . $this->db->escape($email) . "'
                                  AND order_date >= DATE_SUB(NOW(), INTERVAL $minutes MINUTE)");
        return $query->num_rows ? $query->row['order_count'] : 0;
    }
}
?>