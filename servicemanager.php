<?php
class ShtabServiceManager {
    protected $registry;
    protected $services = [];

    public function __construct($registry) {
        $this->registry = $registry;
    }

    public function get($serviceName) {
        if (!isset($this->services[$serviceName])) {
            $className = 'Shtab' . $serviceName;
            
            if (!class_exists($className)) {
                $serviceMap = [
                    'ProductService' => 'service/productservice.php',
                    'MarketplaceService' => 'service/marketplaceservice.php',
                    'OrderService' => 'service/orderservice.php',
                    'LogisticsService' => 'service/logisticsservice.php',
                    'RepricingService' => 'service/repricingservice.php',
                    'AnalyticsService' => 'service/analyticsservice.php',
                    'AIService' => 'service/aiservice.php'
                ];
                
                if (isset($serviceMap[$serviceName])) {
                    $file = DIR_SYSTEM . 'library/shtab/' . $serviceMap[$serviceName];
                    if (file_exists($file)) {
                        require_once($file);
                    } else {
                        throw new Exception('Service file not found: ' . $file);
                    }
                } else {
                    // Попробуем автоматически найти сервис
                    $file = DIR_SYSTEM . 'library/shtab/service/' . strtolower($serviceName) . '.php';
                    if (file_exists($file)) {
                        require_once($file);
                    } else {
                        throw new Exception('Service not found: ' . $serviceName);
                    }
                }
            }

            $this->services[$serviceName] = new $className($this->registry);
        }

        return $this->services[$serviceName];
    }

    // Методы-хелперы для часто используемых сервисов
    public function product() {
        return $this->get('ProductService');
    }

    public function marketplace() {
        return $this->get('MarketplaceService');
    }

    public function order() {
        return $this->get('OrderService');
    }

    public function logistics() {
        return $this->get('LogisticsService');
    }

    public function repricing() {
        return $this->get('RepricingService');
    }

    public function analytics() {
        return $this->get('AnalyticsService');
    }

    public function ai() {
        return $this->get('AIService');
    }
}
?>