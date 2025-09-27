<?php
class ModelExtensionModuleShtabSettings extends Model {
    private $table_setting = DB_PREFIX . 'shtab_setting';

    public function getMarketplaceSettings() {
        // ВСЕ 17 МАРКЕТПЛЕЙСОВ ИЗ ТЗ
        $marketplaces = [
            'ozon' => [
                'name' => 'OZON',
                'fields' => [
                    'client_id' => ['type' => 'text', 'required' => true, 'label' => 'Client ID'],
                    'api_key' => ['type' => 'password', 'required' => true, 'label' => 'API Key'],
                    'seller_id' => ['type' => 'text', 'required' => true, 'label' => 'Seller ID']
                ],
                'api_docs' => 'https://api-seller.ozon.ru/',
                'help_text' => 'Ключи можно получить в личном кабинете OZON Seller'
            ],
            'wildberries' => [
                'name' => 'Wildberries',
                'fields' => [
                    'api_key' => ['type' => 'password', 'required' => true, 'label' => 'API Key'],
                    'supplier_id' => ['type' => 'text', 'required' => true, 'label' => 'Supplier ID'],
                    'warehouse_id' => ['type' => 'text', 'required' => true, 'label' => 'Warehouse ID']
                ],
                'api_docs' => 'https://suppliers-api.wildberries.ru/',
                'help_text' => 'Ключи в личном кабинете поставщика Wildberries'
            ],
            'yandex_market' => [
                'name' => 'Яндекс.Маркет',
                'fields' => [
                    'oauth_token' => ['type' => 'password', 'required' => true, 'label' => 'OAuth Token'],
                    'campaign_id' => ['type' => 'text', 'required' => true, 'label' => 'Campaign ID'],
                    'client_id' => ['type' => 'text', 'required' => true, 'label' => 'Client ID']
                ],
                'api_docs' => 'https://yandex.ru/dev/market/',
                'help_text' => 'Настройки OAuth в Яндекс.OAuth'
            ],
            'avito' => [
                'name' => 'Avito',
                'fields' => [
                    'client_id' => ['type' => 'text', 'required' => true, 'label' => 'Client ID'],
                    'client_secret' => ['type' => 'password', 'required' => true, 'label' => 'Client Secret'],
                    'token' => ['type' => 'password', 'required' => true, 'label' => 'Access Token']
                ],
                'api_docs' => 'https://developers.avito.ru/',
                'help_text' => 'Ключи в кабинете разработчика Avito'
            ],
            'aliexpress' => [
                'name' => 'AliExpress Russia',
                'fields' => [
                    'app_key' => ['type' => 'text', 'required' => true, 'label' => 'App Key'],
                    'app_secret' => ['type' => 'password', 'required' => true, 'label' => 'App Secret'],
                    'session_key' => ['type' => 'password', 'required' => true, 'label' => 'Session Key']
                ],
                'api_docs' => 'https://developers.aliexpress.com/',
                'help_text' => 'Ключи в Aliexpress Open Platform'
            ],
            'sbermegamarket' => [
                'name' => 'СберМегаМаркет',
                'fields' => [
                    'api_key' => ['type' => 'password', 'required' => true, 'label' => 'API Key'],
                    'client_id' => ['type' => 'text', 'required' => true, 'label' => 'Client ID'],
                    'merchant_id' => ['type' => 'text', 'required' => true, 'label' => 'Merchant ID']
                ],
                'api_docs' => 'https://sbermegamarket.ru/api/',
                'help_text' => 'Ключи в личном кабинете партнера'
            ],
            'citilink' => [
                'name' => 'Ситилинк',
                'fields' => [
                    'api_key' => ['type' => 'password', 'required' => true, 'label' => 'API Key'],
                    'partner_id' => ['type' => 'text', 'required' => true, 'label' => 'Partner ID']
                ],
                'api_docs' => 'https://www.citilink.ru/info/partners/api/',
                'help_text' => 'Доступ по запросу для партнеров'
            ],
            'mvideo' => [
                'name' => 'М.Видео',
                'fields' => [
                    'api_key' => ['type' => 'password', 'required' => true, 'label' => 'API Key'],
                    'merchant_code' => ['type' => 'text', 'required' => true, 'label' => 'Merchant Code']
                ],
                'api_docs' => 'https://www.mvideo.ru/partneram',
                'help_text' => 'Для партнеров М.Видео'
            ],
            'eldorado' => [
                'name' => 'Эльдорадо',
                'fields' => [
                    'api_key' => ['type' => 'password', 'required' => true, 'label' => 'API Key'],
                    'store_id' => ['type' => 'text', 'required' => true, 'label' => 'Store ID']
                ],
                'api_docs' => 'https://www.eldorado.ru/partneram/',
                'help_text' => 'API для партнеров Эльдорадо'
            ],
            'dns' => [
                'name' => 'DNS',
                'fields' => [
                    'api_key' => ['type' => 'password', 'required' => true, 'label' => 'API Key'],
                    'supplier_id' => ['type' => 'text', 'required' => true, 'label' => 'Supplier ID']
                ],
                'api_docs' => 'https://www.dns-shop.ru/partner/api/',
                'help_text' => 'Для поставщиков DNS'
            ],
            'leroymerlin' => [
                'name' => 'Леруа Мерлен',
                'fields' => [
                    'api_key' => ['type' => 'password', 'required' => true, 'label' => 'API Key'],
                    'contractor_id' => ['type' => 'text', 'required' => true, 'label' => 'Contractor ID']
                ],
                'api_docs' => 'https://leroymerlin.ru/partneram/',
                'help_text' => 'API для поставщиков'
            ],
            'petrovich' => [
                'name' => 'Петрович',
                'fields' => [
                    'api_key' => ['type' => 'password', 'required' => true, 'label' => 'API Key'],
                    'account_id' => ['type' => 'text', 'required' => true, 'label' => 'Account ID']
                ],
                'api_docs' => 'https://petrovich.ru/partneram/',
                'help_text' => 'Для партнеров Петрович'
            ],
            'maxidom' => [
                'name' => 'Максидом',
                'fields' => [
                    'api_key' => ['type' => 'password', 'required' => true, 'label' => 'API Key'],
                    'partner_code' => ['type' => 'text', 'required' => true, 'label' => 'Partner Code']
                ],
                'api_docs' => 'https://www.maxidom.ru/partneram/',
                'help_text' => 'API для поставщиков Максидом'
            ],
            'vseinstrumenty' => [
                'name' => 'ВсеИнструменты.ру',
                'fields' => [
                    'api_key' => ['type' => 'password', 'required' => true, 'label' => 'API Key'],
                    'dealer_id' => ['type' => 'text', 'required' => true, 'label' => 'Dealer ID']
                ],
                'api_docs' => 'https://www.vseinstrumenti.ru/partneram/',
                'help_text' => 'Для дилеров ВсеИнструменты'
            ],
            '220volt' => [
                'name' => '220 Вольт',
                'fields' => [
                    'api_key' => ['type' => 'password', 'required' => true, 'label' => 'API Key'],
                    'client_code' => ['type' => 'text', 'required' => true, 'label' => 'Client Code']
                ],
                'api_docs' => 'https://220-volt.ru/partneram/',
                'help_text' => 'API для партнеров 220 Вольт'
            ],
            'mirtekhniki' => [
                'name' => 'Мир техники',
                'fields' => [
                    'api_key' => ['type' => 'password', 'required' => true, 'label' => 'API Key'],
                    'account_id' => ['type' => 'text', 'required' => true, 'label' => 'Account ID']
                ],
                'api_docs' => 'https://www.mir-tehniki.ru/partneram/',
                'help_text' => 'Для партнеров Мир техники'
            ],
            'ulmart' => [
                'name' => 'Юлмарт',
                'fields' => [
                    'api_key' => ['type' => 'password', 'required' => true, 'label' => 'API Key'],
                    'user_id' => ['type' => 'text', 'required' => true, 'label' => 'User ID']
                ],
                'api_docs' => 'https://www.ulmart.ru/partneram/',
                'help_text' => 'API для партнеров Юлмарт'
            ]
        ];

        // Загружаем сохраненные значения для ВСЕХ маркетплейсов
        foreach ($marketplaces as $code => &$marketplace) {
            foreach ($marketplace['fields'] as $field => $config) {
                $key = "shtab_{$code}_{$field}";
                $marketplace['fields'][$field]['value'] = $this->getSetting($key);
            }
            $marketplace['enabled'] = (bool)$this->getSetting("shtab_{$code}_enabled");
            $marketplace['last_test'] = $this->getSetting("shtab_{$code}_last_test");
            $marketplace['test_status'] = $this->getSetting("shtab_{$code}_test_status");
        }

        return $marketplaces;
    }

    public function getLogisticsSettings() {
        // ВСЕ 12 ТРАНСПОРТНЫХ КОМПАНИЙ ИЗ ТЗ
        $carriers = [
            'cdek' => [
                'name' => 'СДЭК',
                'fields' => [
                    'account' => ['type' => 'text', 'required' => true, 'label' => 'Аккаунт'],
                    'secure_password' => ['type' => 'password', 'required' => true, 'label' => 'Secure Password'],
                    'test_mode' => ['type' => 'checkbox', 'required' => false, 'label' => 'Тестовый режим']
                ]
            ],
            'boxberry' => [
                'name' => 'Boxberry',
                'fields' => [
                    'api_key' => ['type' => 'password', 'required' => true, 'label' => 'API Key'],
                    'token' => ['type' => 'password', 'required' => true, 'label' => 'Token']
                ]
            ],
            'russianpost' => [
                'name' => 'Почта России',
                'fields' => [
                    'access_token' => ['type' => 'password', 'required' => true, 'label' => 'Access Token'],
                    'login' => ['type' => 'text', 'required' => true, 'label' => 'Логин']
                ]
            ],
            'dpd' => [
                'name' => 'DPD Russia',
                'fields' => [
                    'client_number' => ['type' => 'text', 'required' => true, 'label' => 'Номер клиента'],
                    'client_key' => ['type' => 'password', 'required' => true, 'label' => 'Ключ клиента']
                ]
            ],
            'dellin' => [
                'name' => 'Деловые Линии',
                'fields' => [
                    'app_key' => ['type' => 'password', 'required' => true, 'label' => 'App Key']
                ]
            ],
            'pek' => [
                'name' => 'ПЭК',
                'fields' => [
                    'api_key' => ['type' => 'password', 'required' => true, 'label' => 'API Key']
                ]
            ],
            'jde' => [
                'name' => 'ЖДЭ',
                'fields' => [
                    'login' => ['type' => 'text', 'required' => true, 'label' => 'Логин'],
                    'password' => ['type' => 'password', 'required' => true, 'label' => 'Пароль']
                ]
            ],
            'iml' => [
                'name' => 'IML',
                'fields' => [
                    'client_id' => ['type' => 'text', 'required' => true, 'label' => 'Client ID'],
                    'api_key' => ['type' => 'password', 'required' => true, 'label' => 'API Key']
                ]
            ],
            'shiptor' => [
                'name' => 'Shiptor',
                'fields' => [
                    'api_key' => ['type' => 'password', 'required' => true, 'label' => 'API Key']
                ]
            ],
            'nrg' => [
                'name' => 'Энергия',
                'fields' => [
                    'user_id' => ['type' => 'text', 'required' => true, 'label' => 'User ID'],
                    'api_key' => ['type' => 'password', 'required' => true, 'label' => 'API Key']
                ]
            ],
            'kurer' => [
                'name' => 'Курьерсервис',
                'fields' => [
                    'login' => ['type' => 'text', 'required' => true, 'label' => 'Логин'],
                    'password' => ['type' => 'password', 'required' => true, 'label' => 'Пароль']
                ]
            ],
            'pickpoint' => [
                'name' => 'PickPoint',
                'fields' => [
                    'api_key' => ['type' => 'password', 'required' => true, 'label' => 'API Key']
                ]
            ]
        ];

        // Загружаем сохраненные значения для ВСЕХ ТК
        foreach ($carriers as $code => &$carrier) {
            foreach ($carrier['fields'] as $field => $config) {
                $key = "shtab_{$code}_{$field}";
                $carrier['fields'][$field]['value'] = $this->getSetting($key);
            }
            $carrier['enabled'] = (bool)$this->getSetting("shtab_{$code}_enabled");
        }

        return $carriers;
    }

    public function saveMarketplaceSettings($data) {
        foreach ($data as $key => $value) {
            if (strpos($key, 'shtab_') === 0) {
                // Шифруем чувствительные данные перед сохранением
                if (strpos($key, 'api_key') !== false || 
                    strpos($key, 'password') !== false || 
                    strpos($key, 'token') !== false ||
                    strpos($key, 'secret') !== false) {
                    $value = $this->encrypt($value);
                }
                $this->setSetting($key, $value);
            }
        }
    }

    public function updateTestResult($marketplace, $success, $message = '') {
        $this->setSetting("shtab_{$marketplace}_last_test", date('Y-m-d H:i:s'));
        $this->setSetting("shtab_{$marketplace}_test_status", $success ? 'success' : 'error');
        $this->setSetting("shtab_{$marketplace}_test_message", $message);
    }

    private function encrypt($data) {
        if (empty($data)) return $data;
        
        $key = $this->config->get('shtab_encryption_key') ?: 'default_key_ChangeInProduction123!';
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-GCM', $key, 0, $iv, $tag);
        return base64_encode($iv . $tag . $encrypted);
    }

    private function decrypt($data) {
        if (empty($data)) return $data;
        
        $key = $this->config->get('shtab_encryption_key') ?: 'default_key_ChangeInProduction123!';
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $encrypted = substr($data, 32);
        return openssl_decrypt($encrypted, 'AES-256-GCM', $key, 0, $iv, $tag);
    }

    public function getSetting($key, $default = null) {
        $query = $this->db->query("SELECT value FROM " . $this->table_setting . " WHERE `key` = '" . $this->db->escape($key) . "'");
        return $query->num_rows ? $query->row['value'] : $default;
    }

    public function setSetting($key, $value) {
        $this->db->query("REPLACE INTO " . $this->table_setting . " SET `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) . "'");
    }
}
?>