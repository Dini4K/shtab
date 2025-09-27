<?php
class ControllerExtensionModuleShtabMobile extends Controller {
    private $module_path = 'extension/module/shtab/';

    public function index() {
        $this->load->language($this->module_path . 'mobile');
        $this->document->setTitle($this->language->get('heading_title'));

        $data = $this->prepareTemplateData();

        // Настройки Telegram бота
        $data['bot_settings'] = $this->getBotSettings();
        $data['mobile_sessions'] = $this->getMobileSessions();

        $this->response->setOutput($this->load->view($this->module_path . 'mobile/dashboard', $data));
    }

    /**
     * Сохранить настройки Telegram бота
     */
    public function saveBotSettings() {
        $this->load->language($this->module_path . 'mobile');
        
        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $this->load->model('extension/module/shtab/shtab');
            
            $settings = [
                'shtab_telegram_bot_token' => $this->request->post['bot_token'],
                'shtab_telegram_chat_id' => $this->request->post['chat_id'],
                'shtab_telegram_notifications' => $this->request->post['notifications'] ?? []
            ];

            foreach ($settings as $key => $value) {
                $this->model_extension_module_shtab_shtab->setSetting($key, $value);
            }

            // Тестируем соединение с ботом
            $test_result = $this->testTelegramConnection();

            $this->response->setOutput(json_encode([
                'success' => true,
                'test_result' => $test_result
            ]));
        }
    }

    /**
     * Отправить тестовое уведомление
     */
    public function sendTestNotification() {
        try {
            require_once(DIR_SYSTEM . 'library/shtab/rpa/telegrambot.php');
            $telegramBot = new ShtabTelegramBot($this->registry);

            $result = $telegramBot->sendNotification(
                $this->config->get('shtab_telegram_chat_id'),
                '✅ <b>Тестовое уведомление</b>\nЭто тестовое сообщение от SHTAB AI системы.'
            );

            $this->response->setOutput(json_encode([
                'success' => $result,
                'message' => $result ? 'Тестовое уведомление отправлено' : 'Ошибка отправки'
            ]));

        } catch (Exception $e) {
            $this->response->setOutput(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
        }
    }

    /**
     * Генерация QR кода для мобильного приложения
     */
    public function generateQRCode() {
        $user_token = $this->session->data['user_token'];
        $qr_data = [
            'api_url' => HTTPS_CATALOG,
            'user_token' => $user_token,
            'timestamp' => time()
        ];

        $qr_code = base64_encode(json_encode($qr_data));

        $this->response->setOutput(json_encode([
            'success' => true,
            'qr_code' => $qr_code,
            'setup_url' => 'https://example.com/shtab-app-setup?data=' . urlencode($qr_code)
        ]));
    }

    private function getBotSettings() {
        $this->load->model('extension/module/shtab/shtab');
        
        return [
            'bot_token' => $this->model_extension_module_shtab_shtab->getSetting('shtab_telegram_bot_token'),
            'chat_id' => $this->model_extension_module_shtab_shtab->getSetting('shtab_telegram_chat_id'),
            'notifications' => $this->model_extension_module_shtab_shtab->getSetting('shtab_telegram_notifications') ?? []
        ];
    }

    private function getMobileSessions() {
        $query = $this->db->query("SELECT ms.*, u.username 
                                  FROM " . DB_PREFIX . "shtab_mobile_session ms
                                  LEFT JOIN " . DB_PREFIX . "user u ON (ms.user_id = u.user_id)
                                  WHERE ms.is_active = 1
                                  ORDER BY ms.last_activity DESC");
        
        return $query->rows;
    }

    private function testTelegramConnection() {
        try {
            require_once(DIR_SYSTEM . 'library/shtab/rpa/telegrambot.php');
            $telegramBot = new ShtabTelegramBot($this->registry);

            // Пытаемся получить информацию о боте
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.telegram.org/bot' . $this->request->post['bot_token'] . '/getMe');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200) {
                $data = json_decode($response, true);
                return [
                    'success' => true,
                    'bot_name' => $data['result']['first_name'] ?? 'Unknown',
                    'bot_username' => $data['result']['username'] ?? 'Unknown'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Неверный токен бота'
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function prepareTemplateData() {
        $data['user_token'] = $this->session->data['user_token'];
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['menu'] = $this->load->controller($this->module_path . 'shtab_menu');

        // URLs
        $data['save_bot_settings_url'] = $this->url->link($this->module_path . 'mobile/saveBotSettings', 'user_token=' . $data['user_token'], true);
        $data['test_notification_url'] = $this->url->link($this->module_path . 'mobile/sendTestNotification', 'user_token=' . $data['user_token'], true);
        $data['generate_qr_url'] = $this->url->link($this->module_path . 'mobile/generateQRCode', 'user_token=' . $data['user_token'], true);

        // Хлебные крошки
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link($this->module_path . 'mobile', 'user_token=' . $this->session->data['user_token'], true)
        );

        return $data;
    }
}
?>