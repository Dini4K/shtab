<?php
class ShtabMarketplaceService {
    protected $registry;
    protected $db;
    protected $log;
    protected $adapters = [];

    // ВСЕ МАРКЕТПЛЕЙСЫ ИЗ ТЗ
    private $supported_marketplaces = [
        'ozon' => [
            'class' => 'ShtabOzonAdapter',
            'name' => 'OZON',
            'enabled' => false
        ],
        'wildberries' => [
            'class' => 'ShtabWildberriesAdapter', 
            'name' => 'Wildberries',
            'enabled' => false
        ],
        'yandex_market' => [
            'class' => 'ShtabYandexMarketAdapter',
            'name' => 'Яндекс.Маркет',
            'enabled' => false
        ],
        'avito' => [
            'class' => 'ShtabAvitoAdapter',
            'name' => 'Avito',
            'enabled' => false
        ],
        'aliexpress' => [
            'class' => 'ShtabAliexpressAdapter',
            'name' => 'AliExpress Russia',
            'enabled' => false
        ],
        'sbermegamarket' => [
            'class' => 'ShtabSbermegamarketAdapter',
            'name' => 'СберМегаМаркет',
            'enabled' => false
        ],
        'citilink' => [
            'class' => 'ShtabCitilinkAdapter',
            'name' => 'Ситилинк',
            'enabled' => false
        ],
        'mvideo' => [
            'class' => 'ShtabMvideoAdapter',
            'name' => 'М.Видео',
            'enabled' => false
        ],
        'eldorado' => [
            'class' => 'ShtabEldoradoAdapter',
            'name' => 'Эльдорадо',
            'enabled' => false
        ],
        'dns' => [
            'class' => 'ShtabDnsAdapter',
            'name' => 'DNS',
            'enabled' => false
        ],
        'leroymerlin' => [
            'class' => 'ShtabLeroymerlinAdapter',
            'name' => 'Леруа Мерлен',
            'enabled' => false
        ],
        'petrovich' => [
            'class' => 'ShtabPetrovichAdapter',
            'name' => 'Петрович',
            'enabled' => false
        ],
        'maxidom' => [
            'class' => 'ShtabMaxidomAdapter',
            'name' => 'Максидом',
            'enabled' => false
        ],
        'vseinstrumenty' => [
            'class' => 'ShtabVseinstrumentyAdapter',
            'name' => 'ВсеИнструменты.ру',
            'enabled' => false
        ],
        '220volt' => [
            'class' => 'Shtab220voltAdapter',
            'name' => '220 Вольт',
            'enabled' => false
        ],
        'mirtekhniki' => [
            'class' => 'ShtabMirtekhnikiAdapter',
            'name' => 'Мир техники',
            'enabled' => false
        ],
        'ulmart' => [
            'class' => 'ShtabUlmartAdapter',
            'name' => 'Юлмарт',
            'enabled' => false
        ]
    ];

    public function __construct($registry) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->log = $registry->get('log');
        $this->loadAdapters();
    }

    /**
     * Загрузка всех адаптеров маркетплейсов
     */
    protected function loadAdapters() {
        foreach ($this->supported_marketplaces as $code => $marketplace) {
            $adapterFile = DIR_SYSTEM . 'library/shtab/marketplace/adapter/' . strtolower($marketplace['class']) . '.php';
            
            if (file_exists($adapterFile)) {
                try {
                    require_once($adapterFile);
                    
                    if (class_exists($marketplace['class'])) {
                        $adapter = new $marketplace['class']($this->registry);
                        $this->adapters[$code] = $adapter;
                        $this->supported_marketplaces[$code]['enabled'] = $adapter->isEnabled();
                    } else {
                        $this->log->write("SHTAB ERROR: Class {$marketplace['class']} not found for marketplace {$code}");
                    }
                } catch (Exception $e) {
                    $this->log->write("SHTAB ERROR: Cannot load adapter {$code} - " . $e->getMessage());
                }
            } else {
                // Файл адаптера еще не создан - это нормально на этапе разработки
                $this->supported_marketplaces[$code]['adapter_missing'] = true;
            }
        }

        $this->log->write("SHTAB: Loaded " . count($this->adapters) . " marketplace adapters");
    }

    /**
     * Получить адаптер маркетплейса
     */
    public function getAdapter($marketplace) {
        if (!isset($this->adapters[$marketplace])) {
            throw new Exception("Адаптер для маркетплейса '{$marketplace}' не найден или не загружен");
        }
        return $this->adapters[$marketplace];
    }

    /**
     * Проверить поддержку маркетплейса
     */
    public function isSupported($marketplace) {
        return isset($this->supported_marketplaces[$marketplace]);
    }

    /**
     * Проверить доступность адаптера
     */
    public function isAdapterAvailable($marketplace) {
        return isset($this->adapters[$marketplace]);
    }

    /**
     * Получить список всех поддерживаемых маркетплейсов
     */
    public function getSupportedMarketplaces() {
        return $this->supported_marketplaces;
    }

    /**
     * Получить список доступных адаптеров (с загруженными классами)
     */
    public function getAvailableAdapters() {
        return array_keys($this->adapters);
    }

    /**
     * Тестирование подключения к маркетплейсу
     */
    public function testConnection($marketplace, $settings = null) {
        if (!$this->isSupported($marketplace)) {
            return [
                'success' => false,
                'error' => "Маркетплейс '{$marketplace}' не поддерживается"
            ];
        }

        if (!$this->isAdapterAvailable($marketplace)) {
            return [
                'success' => false,
                'error' => "Адаптер для '{$marketplace}' еще не реализован"
            ];
        }

        try {
            $adapter = $this->getAdapter($marketplace);
            
            if ($settings) {
                $adapter->setTestSettings($settings);
            }
            
            $result = $adapter->testConnection();
            $result['marketplace'] = $marketplace;
            $result['adapter_available'] = true;
            
            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'marketplace' => $marketplace,
                'adapter_available' => true
            ];
        }
    }

    /**
     * Массовое тестирование всех подключений
     */
    public function testAllConnections() {
        $results = [];
        
        foreach ($this->supported_marketplaces as $code => $marketplace) {
            if ($this->isAdapterAvailable($code)) {
                try {
                    $results[$code] = $this->testConnection($code);
                } catch (Exception $e) {
                    $results[$code] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'marketplace' => $code,
                        'adapter_available' => true
                    ];
                }
            } else {
                $results[$code] = [
                    'success' => false,
                    'error' => 'Адаптер не реализован',
                    'marketplace' => $code,
                    'adapter_available' => false
                ];
            }
        }
        
        return $results;
    }

    /**
     * Импорт заказов с маркетплейса
     */
    public function importOrders($marketplace, $params = []) {
        if (!$this->isAdapterAvailable($marketplace)) {
            throw new Exception("Адаптер для маркетплейса '{$marketplace}' не реализован");
        }

        $adapter = $this->getAdapter($marketplace);
        return $adapter->getOrders($params);
    }

    /**
     * Массовый импорт заказов со всех маркетплейсов
     */
    public function importOrdersFromAll($params = []) {
        $results = [];
        
        foreach ($this->adapters as $code => $adapter) {
            if ($adapter->isEnabled()) {
                try {
                    $results[$code] = $adapter->getOrders($params);
                } catch (Exception $e) {
                    $results[$code] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'marketplace' => $code
                    ];
                }
            }
        }
        
        return $results;
    }

    /**
     * Обновление цен на маркетплейсе
     */
    public function updatePrices($marketplace, $prices) {
        if (!$this->isAdapterAvailable($marketplace)) {
            throw new Exception("Адаптер для маркетплейса '{$marketplace}' не реализован");
        }

        $adapter = $this->getAdapter($marketplace);
        return $adapter->updatePrices($prices);
    }

    /**
     * Массовое обновление цен на всех маркетплейсах
     */
    public function updatePricesOnAll($prices) {
        $results = [];
        
        foreach ($this->adapters as $code => $adapter) {
            if ($adapter->isEnabled()) {
                try {
                    $results[$code] = $adapter->updatePrices($prices);
                } catch (Exception $e) {
                    $results[$code] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'marketplace' => $code
                    ];
                }
            }
        }
        
        return $results;
    }

    /**
     * Обновление остатков на маркетплейсе
     */
    public function updateStocks($marketplace, $stocks) {
        if (!$this->isAdapterAvailable($marketplace)) {
            throw new Exception("Адаптер для маркетплейса '{$marketplace}' не реализован");
        }

        $adapter = $this->getAdapter($marketplace);
        return $adapter->updateStocks($stocks);
    }

    /**
     * Получение товаров с маркетплейса
     */
    public function getProducts($marketplace, $params = []) {
        if (!$this->isAdapterAvailable($marketplace)) {
            throw new Exception("Адаптер для маркетплейса '{$marketplace}' не реализован");
        }

        $adapter = $this->getAdapter($marketplace);
        return $adapter->getProducts($params);
    }

    /**
     * Получить статистику по маркетплейсам
     */
    public function getMarketplacesStats() {
        $stats = [];
        
        foreach ($this->supported_marketplaces as $code => $marketplace) {
            $stats[$code] = [
                'name' => $marketplace['name'],
                'supported' => true,
                'adapter_available' => $this->isAdapterAvailable($code),
                'enabled' => $marketplace['enabled'] ?? false,
                'last_sync' => $this->getLastSyncTime($code),
                'orders_today' => $this->getTodayOrdersCount($code),
                'products_count' => $this->getProductsCount($code),
                'total_orders' => $this->getTotalOrdersCount($code)
            ];
        }
        
        return $stats;
    }

    /**
     * Получить сводную статистику
     */
    public function getSummaryStats() {
        $stats = $this->getMarketplacesStats();
        
        $summary = [
            'total_marketplaces' => count($this->supported_marketplaces),
            'available_adapters' => count($this->adapters),
            'enabled_marketplaces' => 0,
            'total_orders_today' => 0,
            'total_products' => 0,
            'total_orders' => 0
        ];

        foreach ($stats as $marketplace) {
            if ($marketplace['enabled']) {
                $summary['enabled_marketplaces']++;
            }
            $summary['total_orders_today'] += $marketplace['orders_today'];
            $summary['total_products'] += $marketplace['products_count'];
            $summary['total_orders'] += $marketplace['total_orders'];
        }

        return $summary;
    }

    /**
     * Вспомогательные методы для статистики
     */
    protected function getLastSyncTime($marketplace) {
        $query = $this->db->query("SELECT MAX(last_sync) as last_sync 
                                  FROM " . DB_PREFIX . "shtab_product_to_marketplace 
                                  WHERE marketplace = '" . $this->db->escape($marketplace) . "'");
        return $query->row['last_sync'] ?? null;
    }

    protected function getTodayOrdersCount($marketplace) {
        $query = $this->db->query("SELECT COUNT(*) as count 
                                  FROM " . DB_PREFIX . "shtab_order 
                                  WHERE marketplace = '" . $this->db->escape($marketplace) . "'
                                  AND DATE(order_date) = CURDATE()");
        return $query->row['count'] ?? 0;
    }

    protected function getProductsCount($marketplace) {
        $query = $this->db->query("SELECT COUNT(*) as count 
                                  FROM " . DB_PREFIX . "shtab_product_to_marketplace 
                                  WHERE marketplace = '" . $this->db->escape($marketplace) . "'
                                  AND status = 'active'");
        return $query->row['count'] ?? 0;
    }

    protected function getTotalOrdersCount($marketplace) {
        $query = $this->db->query("SELECT COUNT(*) as count 
                                  FROM " . DB_PREFIX . "shtab_order 
                                  WHERE marketplace = '" . $this->db->escape($marketplace) . "'");
        return $query->row['count'] ?? 0;
    }
}
?>