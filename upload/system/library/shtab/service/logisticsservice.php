<?php
class ShtabLogisticsService {
    protected $registry;
    protected $db;
    protected $log;

    public function __construct($registry) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->log = $registry->get('log');
    }

    /**
     * Рассчитать стоимость доставки
     */
    public function calculateShipping($from_address, $to_address, $package, $carriers = []) {
        $results = [];
        
        // Если не указаны перевозчики, используем всех доступных
        if (empty($carriers)) {
            $carriers = $this->getAvailableCarriers();
        }

        foreach ($carriers as $carrier) {
            try {
                $provider = $this->getCarrierProvider($carrier);
                $calculation = $provider->calculate($from_address, $to_address, $package);
                
                if ($calculation['success']) {
                    $results[$carrier] = $calculation;
                }
            } catch (Exception $e) {
                $this->log->write("SHTAB LOGISTICS ERROR: $carrier - " . $e->getMessage());
                $results[$carrier] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'cost' => 0,
                    'days' => 0
                ];
            }
        }

        // Сортируем по стоимости
        uasort($results, function($a, $b) {
            if (!$a['success']) return 1;
            if (!$b['success']) return -1;
            return $a['cost'] <=> $b['cost'];
        });

        return $results;
    }

    /**
     * Создать заявку на доставку
     */
    public function createShippingRequest($order_id, $carrier, $request_data) {
        $provider = $this->getCarrierProvider($carrier);
        
        try {
            $result = $provider->createShipment($request_data);
            
            // Сохраняем заявку в БД
            $this->db->query("INSERT INTO " . DB_PREFIX . "shtab_logistics_request 
                SET order_id = '" . (int)$order_id . "',
                    carrier = '" . $this->db->escape($carrier) . "',
                    service_type = '" . $this->db->escape($request_data['service_type']) . "',
                    pickup_address = '" . $this->db->escape(json_encode($request_data['pickup_address'])) . "',
                    delivery_address = '" . $this->db->escape(json_encode($request_data['delivery_address'])) . "',
                    package_dimensions = '" . $this->db->escape(json_encode($request_data['package'])) . "',
                    package_weight = '" . (float)$request_data['package']['weight'] . "',
                    declared_value = '" . (float)$request_data['declared_value'] . "',
                    calculated_cost = '" . (float)$result['cost'] . "',
                    external_request_id = '" . $this->db->escape($result['request_id']) . "',
                    request_data = '" . $this->db->escape(json_encode($request_data)) . "',
                    response_data = '" . $this->db->escape(json_encode($result)) . "',
                    status = 'created',
                    date_added = NOW()");

            $request_id = $this->db->getLastId();

            $this->log->write("SHTAB: Создана заявка на доставку #$request_id через $carrier");

            return [
                'success' => true,
                'request_id' => $request_id,
                'tracking_number' => $result['tracking_number'] ?? null,
                'cost' => $result['cost']
            ];

        } catch (Exception $e) {
            $this->log->write("SHTAB LOGISTICS ERROR: Ошибка создания заявки $carrier - " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Получить статус доставки по трек-номеру
     */
    public function getTrackingStatus($carrier, $tracking_number) {
        $provider = $this->getCarrierProvider($carrier);
        
        try {
            return $provider->getTrackingStatus($tracking_number);
        } catch (Exception $e) {
            $this->log->write("SHTAB TRACKING ERROR: $carrier - $tracking_number - " . $e->getMessage());
            return null;
        }
    }

    /**
     * Верификация адреса
     */
    public function verifyAddress($address) {
        // Используем сервис Dadata для верификации адресов
        $api_key = $this->config->get('shtab_dadata_api_key');
        $secret_key = $this->config->get('shtab_dadata_secret_key');
        
        if (!$api_key) {
            return ['success' => false, 'error' => 'API ключ DaData не настроен'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://cleaner.dadata.ru/api/v1/clean/address');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([$address]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Token ' . $api_key,
            'X-Secret: ' . $secret_key
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $data = json_decode($response, true);
            if ($data && isset($data[0])) {
                $result = $data[0];
                
                // Сохраняем результат верификации
                $this->db->query("INSERT INTO " . DB_PREFIX . "shtab_address_verification 
                    SET original_address = '" . $this->db->escape($address) . "',
                        verified_address = '" . $this->db->escape($result['result']) . "',
                        confidence_score = '" . (float)$result['qc'] . "',
                        verification_service = 'dadata',
                        is_valid = '" . (int)($result['qc'] >= 0.8) . "',
                        verification_data = '" . $this->db->escape($response) . "',
                        date_added = NOW()");

                return [
                    'success' => true,
                    'original' => $address,
                    'verified' => $result['result'],
                    'confidence' => $result['qc'],
                    'is_valid' => $result['qc'] >= 0.8,
                    'details' => $result
                ];
            }
        }

        return ['success' => false, 'error' => 'Ошибка верификации адреса'];
    }

    /**
     * Получить провайдера транспортной компании
     */
    protected function getCarrierProvider($carrier) {
        $providerClass = 'Shtab' . ucfirst($carrier) . 'Provider';
        $providerFile = DIR_SYSTEM . 'library/shtab/logistics/provider/' . strtolower($providerClass) . '.php';

        if (!file_exists($providerFile)) {
            throw new Exception('Провайдер ТК не найден: ' . $carrier);
        }

        require_once($providerFile);

        if (!class_exists($providerClass)) {
            throw new Exception('Класс провайдера не найден: ' . $providerClass);
        }

        return new $providerClass($this->registry);
    }

    /**
     * Получить список доступных ТК
     */
    protected function getAvailableCarriers() {
        return ['cdek', 'boxberry', 'russianpost', 'dpd', 'dellin'];
    }
}
?>