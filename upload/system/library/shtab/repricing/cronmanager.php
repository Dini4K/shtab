<?php
require_once(DIR_SYSTEM . 'library/shtab/repricing/ruleengine.php');

class ShtabCronManager {
    protected $registry;
    protected $ruleEngine;

    public function __construct($registry) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->log = $registry->get('log');
        $this->ruleEngine = new ShtabRuleEngine($registry);
    }

    /**
     * Запуск запланированных задач
     */
    public function runScheduledTasks() {
        $tasks = $this->getDueTasks();
        
        foreach ($tasks as $task) {
            $this->runTask($task);
        }
        
        return count($tasks);
    }

    /**
     * Получить задачи для выполнения
     */
    protected function getDueTasks() {
        $now = date('Y-m-d H:i:s');
        
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "shtab_repricing_task 
                                  WHERE is_active = 1 AND next_run <= '" . $this->db->escape($now) . "'");
        
        return $query->rows;
    }

    /**
     * Выполнить задачу
     */
    protected function runTask($task) {
        $start_time = microtime(true);
        
        try {
            $this->db->query("UPDATE " . DB_PREFIX . "shtab_repricing_task 
                             SET last_run = NOW(), last_error = NULL 
                             WHERE task_id = '" . (int)$task['task_id'] . "'");

            // Получаем правила для задачи
            $rule_ids = explode(',', $task['rule_ids']);
            $products_processed = 0;
            $price_changes = 0;

            // Обрабатываем товары для каждого правила
            foreach ($rule_ids as $rule_id) {
                $result = $this->processRule($rule_id);
                $products_processed += $result['products_processed'];
                $price_changes += $result['price_changes'];
            }

            // Обновляем статистику задачи
            $duration = round(microtime(true) - $start_time, 2);
            $next_run = $this->calculateNextRun($task['cron_expression']);
            
            $this->db->query("UPDATE " . DB_PREFIX . "shtab_repricing_task 
                             SET next_run = '" . $this->db->escape($next_run) . "',
                                 run_count = run_count + 1,
                                 last_duration = '" . (float)$duration . "'
                             WHERE task_id = '" . (int)$task['task_id'] . "'");

            $this->log->write("SHTAB CRON: Задача '{$task['name']}' выполнена. Обработано: $products_processed товаров, изменений: $price_changes, время: {$duration}с");

        } catch (Exception $e) {
            $this->db->query("UPDATE " . DB_PREFIX . "shtab_repricing_task 
                             SET last_error = '" . $this->db->escape($e->getMessage()) . "'
                             WHERE task_id = '" . (int)$task['task_id'] . "'");
            
            $this->log->write("SHTAB CRON ERROR: Задача '{$task['name']}' - " . $e->getMessage());
        }
    }

    /**
     * Обработать правило для всех товаров
     */
    protected function processRule($rule_id) {
        $serviceManager = new ShtabServiceManager($this->registry);
        $productService = $serviceManager->product();
        
        $products = $productService->getProductsForRepricing();
        $price_changes = 0;
        
        foreach ($products as $product) {
            $changes = $this->ruleEngine->applyRulesToProduct($product['product_id']);
            
            if ($changes) {
                foreach ($changes as $change) {
                    $this->applyPriceChange($product['product_id'], $change);
                    $price_changes++;
                }
            }
        }
        
        return [
            'products_processed' => count($products),
            'price_changes' => $price_changes
        ];
    }

    /**
     * Применить изменение цены
     */
    protected function applyPriceChange($product_id, $change) {
        // Обновляем цену в БД
        $this->db->query("UPDATE " . DB_PREFIX . "shtab_product_to_marketplace 
                         SET price = '" . (float)$change['new_price'] . "',
                             old_price = '" . (float)$change['old_price'] . "',
                             last_sync = NOW()
                         WHERE product_id = '" . (int)$product_id . "' 
                         AND marketplace = '" . $this->db->escape($change['marketplace']) . "'");

        // Записываем в историю
        $this->db->query("INSERT INTO " . DB_PREFIX . "shtab_price_history 
                         SET product_id = '" . (int)$product_id . "',
                             marketplace = '" . $this->db->escape($change['marketplace']) . "',
                             old_price = '" . (float)$change['old_price'] . "',
                             new_price = '" . (float)$change['new_price'] . "',
                             change_type = 'rule',
                             rule_id = '" . (int)$change['rule_id'] . "',
                             reason = '" . $this->db->escape($change['reason']) . "',
                             ip = '" . $this->db->escape($this->registry->get('request')->server['REMOTE_ADDR']) . "',
                             date_added = NOW()");

        // Отправляем на маркетплейс (асинхронно)
        $this->queueMarketplaceUpdate($product_id, $change['marketplace'], $change['new_price']);
    }

    /**
     * Поставить в очередь обновление на маркетплейсе
     */
    protected function queueMarketplaceUpdate($product_id, $marketplace, $new_price) {
        // Здесь будет интеграция с очередями Redis
        // Пока просто логируем
        $this->log->write("SHTAB: В очередь на обновление - товар $product_id, $marketplace: $new_price");
    }

    /**
     * Рассчитать следующее выполнение по cron expression
     */
    protected function calculateNextRun($cron_expression) {
        // Простая реализация без внешних библиотек
        $parts = explode(' ', $cron_expression);
        if (count($parts) !== 5) {
            return date('Y-m-d H:i:s', strtotime('+1 hour'));
        }
        
        list($min, $hour, $day, $month, $weekday) = $parts;
        
        $next = strtotime('+1 hour');
        for ($i = 0; $i < 1000; $i++) {
            $time = strtotime("+$i hours", $next);
            $time_parts = getdate($time);
            
            if ($this->matchCronPart($min, $time_parts['minutes']) &&
                $this->matchCronPart($hour, $time_parts['hours']) &&
                $this->matchCronPart($day, $time_parts['mday']) &&
                $this->matchCronPart($month, $time_parts['mon']) &&
                $this->matchCronPart($weekday, $time_parts['wday'])) {
                return date('Y-m-d H:i:s', $time);
            }
        }
        
        return date('Y-m-d H:i:s', strtotime('+1 hour'));
    }

    protected function matchCronPart($pattern, $value) {
        if ($pattern === '*') return true;
        if ($pattern === (string)$value) return true;
        if (strpos($pattern, ',') !== false) {
            $values = explode(',', $pattern);
            return in_array($value, $values);
        }
        if (strpos($pattern, '-') !== false) {
            list($start, $end) = explode('-', $pattern);
            return $value >= $start && $value <= $end;
        }
        return false;
    }
}
?>