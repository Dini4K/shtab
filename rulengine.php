<?php
class ShtabRuleEngine {
    protected $registry;
    protected $productService;
    protected $marketplaceService;

    public function __construct($registry) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->log = $registry->get('log');
        
        // Загружаем сервисы через ServiceManager
        $serviceManager = new ShtabServiceManager($registry);
        $this->productService = $serviceManager->product();
        $this->marketplaceService = $serviceManager->marketplace();
    }

    /**
     * Применить правила репрайсинга к товару
     */
    public function applyRulesToProduct($product_id, $marketplace = null) {
        $product_data = $this->productService->getProductData($product_id);
        if (!$product_data || !$product_data['repricing_enabled']) {
            return false;
        }

        // Получаем активные правила
        $rules = $this->getActiveRules();
        $marketplace_data = $this->productService->getProductMarketplaces($product_id);
        
        $results = [];
        
        foreach ($rules as $rule) {
            // Проверяем применение к маркетплейсам
            if (!$rule['apply_to_all_marketplaces'] && $rule['specific_marketplaces']) {
                $allowed_marketplaces = json_decode($rule['specific_marketplaces'], true);
                if ($marketplace && !in_array($marketplace, $allowed_marketplaces)) {
                    continue;
                }
            }

            foreach ($marketplace_data as $mp_name => $mp_data) {
                if ($marketplace && $mp_name !== $marketplace) {
                    continue;
                }

                // Подготавливаем контекст для оценки правил
                $context = $this->prepareContext($product_data, $mp_data, $mp_name);
                
                // Проверяем условия правила
                if ($this->evaluateConditions($rule['conditions'], $context)) {
                    // Применяем действия
                    $new_price = $this->applyActions($rule['actions'], $context);
                    
                    if ($new_price !== null && $new_price != $mp_data['price']) {
                        $results[] = [
                            'rule_id' => $rule['rule_id'],
                            'marketplace' => $mp_name,
                            'old_price' => $mp_data['price'],
                            'new_price' => $new_price,
                            'reason' => $rule['actions']['reason'] ?? 'Правило: ' . $rule['name']
                        ];
                        
                        // Останавливаемся если правило требует этого
                        if ($rule['stop_on_match']) {
                            break 2;
                        }
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Подготовить контекст для оценки правил
     */
    protected function prepareContext($product_data, $marketplace_data, $marketplace) {
        $context = [
            'product_id' => $product_data['product_id'],
            'cost_price' => (float)$product_data['cost_price'],
            'min_price' => (float)$product_data['min_price'],
            'max_price' => (float)$product_data['max_price'],
            'rrp_price' => (float)$product_data['rrp_price'],
            'current_price' => (float)$marketplace_data['price'],
            'marketplace' => $marketplace,
            'stock' => (int)$marketplace_data['stock']
        ];

        // Добавляем данные конкурентов
        $context['competitor_price'] = $this->getCompetitorPrice($product_data['product_id']);
        
        return $context;
    }

    /**
     * Оценить условия правила
     */
    protected function evaluateConditions($conditions, $context) {
        if (!isset($conditions['condition'])) {
            return false;
        }

        $results = [];
        foreach ($conditions['rules'] as $rule) {
            $field_value = $context[$rule['field']] ?? null;
            $compare_value = $this->resolveValue($rule['value'], $context);
            
            $results[] = $this->compareValues($field_value, $rule['operator'], $compare_value);
        }

        if ($conditions['condition'] === 'and') {
            return !in_array(false, $results, true);
        } else {
            return in_array(true, $results, true);
        }
    }

    /**
     * Сравнить значения по оператору
     */
    protected function compareValues($a, $operator, $b) {
        switch ($operator) {
            case 'equal': return $a == $b;
            case 'not_equal': return $a != $b;
            case 'less': return $a < $b;
            case 'less_equal': return $a <= $b;
            case 'greater': return $a > $b;
            case 'greater_equal': return $a >= $b;
            case 'contains': return stripos((string)$a, (string)$b) !== false;
            case 'not_contains': return stripos((string)$a, (string)$b) === false;
            default: return false;
        }
    }

    /**
     * Применить действия правила
     */
    protected function applyActions($actions, $context) {
        if ($actions['action'] === 'set_price') {
            return $this->calculatePrice($actions['value'], $context);
        }
        
        return null;
    }

    /**
     * Вычислить новую цену по формуле
     */
    protected function calculatePrice($formula, $context) {
        // Заменяем переменные в формуле
        foreach ($context as $key => $value) {
            if (is_numeric($value)) {
                $formula = str_replace($key, (string)$value, $formula);
            }
        }

        // Безопасное вычисление формулы
        try {
            // Убираем опасные конструкции
            $formula = preg_replace('/[^0-9+\-*\/().]/', '', $formula);
            $result = eval("return $formula;");
            return max(0, round($result, 2));
        } catch (Exception $e) {
            $this->log->write('SHTAB ERROR: Ошибка вычисления формулы: ' . $formula);
            return null;
        }
    }

    /**
     * Получить активные правила (с кешированием)
     */
    protected function getActiveRules() {
        $cache_key = 'shtab_active_rules';
        
        // Попробуем получить из кеша
        if (function_exists('cache')) {
            $rules = cache($cache_key);
            if ($rules !== false) {
                return $rules;
            }
        }

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "shtab_repricing_rule 
                                  WHERE is_active = 1 ORDER BY priority DESC");
        
        $rules = [];
        foreach ($query->rows as $row) {
            $row['conditions'] = json_decode($row['conditions'], true);
            $row['actions'] = json_decode($row['actions'], true);
            $rules[] = $row;
        }

        // Сохраняем в кеш на 1 час
        if (function_exists('cache')) {
            cache($cache_key, $rules, 3600);
        }

        return $rules;
    }

    /**
     * Получить среднюю цену конкурентов
     */
    protected function getCompetitorPrice($product_id) {
        $query = $this->db->query("SELECT AVG(competitor_price) as avg_price 
                                  FROM " . DB_PREFIX . "shtab_competitor_price 
                                  WHERE product_id = '" . (int)$product_id . "' 
                                  AND is_available = 1");
        
        return $query->num_rows ? (float)$query->row['avg_price'] : 0;
    }

    /**
     * Разрешить значение (может быть переменной или константой)
     */
    protected function resolveValue($value, $context) {
        if (is_string($value) && isset($context[$value])) {
            return $context[$value];
        }
        return $value;
    }
}
?>