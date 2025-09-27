<?php
abstract class ShtabMarketplaceAdapter {
    protected $registry;
    protected $db;
    protected $log;
    protected $config;
    protected $settings = [];
    protected $enabled = false;

    public function __construct($registry) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->log = $registry->get('log');
        $this->config = $registry->get('config');
        $this->loadSettings();
    }

    /**
     * Загрузка настроек из базы
     */
    abstract protected function loadSettings();

    /**
     * Проверка включен ли адаптер
     */
    public function isEnabled() {
        return $this->enabled;
    }

    /**
     * Установка тестовых настроек
     */
    public function setTestSettings($settings) {
        $this->settings = array_merge($this->settings, $settings);
    }

    /**
     * Тестирование подключения
     */
    public function testConnection() {
        if (!$this->enabled) {
            return [
                'success' => false,
                'error' => 'Адаптер отключен в настройках'
            ];
        }

        try {
            // Базовая проверка наличия обязательных настроек
            $missing_fields = $this->validateRequiredSettings();
            if (!empty($missing_fields)) {
                return [
                    'success' => false,
                    'error' => 'Отсутствуют обязательные настройки: ' . implode(', ', $missing_fields)
                ];
            }

            // Конкретная реализация в дочерних классах
            return $this->performConnectionTest();
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Получение заказов
     */
    public function getOrders($params = []) {
        if (!$this->enabled) {
            throw new Exception('Адаптер отключен');
        }

        try {
            return $this->performGetOrders($params);
        } catch (Exception $e) {
            $this->log->write("SHTAB ERROR [{$this->getMarketplaceCode()}] getOrders: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Обновление цен
     */
    public function updatePrices($prices) {
        if (!$this->enabled) {
            throw new Exception('Адаптер отключен');
        }

        try {
            return $this->performUpdatePrices($prices);
        } catch (Exception $e) {
            $this->log->write("SHTAB ERROR [{$this->getMarketplaceCode()}] updatePrices: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Обновление остатков
     */
    public function updateStocks($stocks) {
        if (!$this->enabled) {
            throw new Exception('Адаптер отключен');
        }

        try {
            return $this->performUpdateStocks($stocks);
        } catch (Exception $e) {
            $this->log->write("SHTAB ERROR [{$this->getMarketplaceCode()}] updateStocks: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Получение информации о товарах
     */
    public function getProducts($params = []) {
        if (!$this->enabled) {
            throw new Exception('Адаптер отключен');
        }

        try {
            return $this->performGetProducts($params);
        } catch (Exception $e) {
            $this->log->write("SHTAB ERROR [{$this->getMarketplaceCode()}] getProducts: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * АБСТРАКТНЫЕ МЕТОДЫ - должны быть реализованы в дочерних классах
     */
    abstract protected function performConnectionTest();
    abstract protected function performGetOrders($params);
    abstract protected function performUpdatePrices($prices);
    abstract protected function performUpdateStocks($stocks);
    abstract protected function performGetProducts($params);
    abstract protected function getMarketplaceCode();
    abstract protected function getMarketplaceName();

    /**
     * Валидация обязательных настроек
     */
    protected function validateRequiredSettings() {
        $required_fields = $this->getRequiredSettings();
        $missing = [];

        foreach ($required_fields as $field) {
            if (empty($this->settings[$field])) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Получение списка обязательных настроек
     */
    abstract protected function getRequiredSettings();

    /**
     * Базовый HTTP запрос с обработкой ошибок
     */
    protected function makeRequest($url, $method = 'GET', $data = [], $headers = []) {
        $ch = curl_init();
        
        $default_headers = [
            'Content-Type: application/json',
            'User-Agent: SHTAB-Module/1.0 (+https://github.com/shtab-module)'
        ];

        $headers = array_merge($default_headers, $headers);
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Логирование запроса (без чувствительных данных)
        $this->logApiRequest($url, $method, $data, $http_code, $error);

        if ($http_code >= 400) {
            throw new Exception("API Error {$http_code}: {$error}");
        }

        if ($response === false) {
            throw new Exception("CURL Error: {$error}");
        }

        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON decode error: " . json_last_error_msg());
        }

        return $result;
    }

    /**
     * Логирование API запросов
     */
    protected function logApiRequest($url, $method, $data, $http_code, $error) {
        // Очищаем чувствительные данные из логов
        $log_data = $data;
        $this->sanitizeLogData($log_data);

        $log_entry = [
            'marketplace' => $this->getMarketplaceCode(),
            'url' => $url,
            'method' => $method,
            'http_code' => $http_code,
            'error' => $error,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        if ($http_code >= 400) {
            $this->log->write("SHTAB API ERROR: " . json_encode($log_entry, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Очистка чувствительных данных для логов
     */
    protected function sanitizeLogData(&$data) {
        if (is_array($data)) {
            foreach ($data as $key => &$value) {
                if (is_array($value)) {
                    $this->sanitizeLogData($value);
                } elseif (in_array(strtolower($key), ['api_key', 'password', 'token', 'secret', 'key'])) {
                    $value = '***HIDDEN***';
                }
            }
        }
    }

    /**
     * Шифрование чувствительных данных
     */
    protected function encrypt($data) {
        if (empty($data)) return $data;
        
        $key = $this->config->get('shtab_encryption_key') ?: 'default_key_ChangeInProduction123!';
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-GCM', $key, 0, $iv, $tag);
        return base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Дешифрование данных
     */
    protected function decrypt($data) {
        if (empty($data)) return $data;
        
        $key = $this->config->get('shtab_encryption_key') ?: 'default_key_ChangeInProduction123!';
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $encrypted = substr($data, 32);
        return openssl_decrypt($encrypted, 'AES-256-GCM', $key, 0, $iv, $tag) ?: '';
    }

    public function __get($key) {
        return $this->registry->get($key);
    }
}
?>