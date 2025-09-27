<?php
class ControllerExtensionModuleShtabSettings extends Controller {
    private $error = array();
    private $module_path = 'extension/module/shtab/';

    public function index() {
        $this->load->language($this->module_path . 'settings');
        $this->document->setTitle($this->language->get('heading_title'));

        // Загружаем JavaScript для управления настройками
        $this->document->addScript('view/javascript/shtab/settings.js');

        $data = $this->prepareTemplateData();

        // Загружаем настройки ВСЕХ маркетплейсов и ТК
        $data['marketplace_settings'] = $this->getMarketplaceSettings();
        $data['logistics_settings'] = $this->getLogisticsSettings();
        $data['connection_status'] = $this->getConnectionStatus();

        $this->response->setOutput($this->load->view($this->module_path . 'settings/form', $data));
    }

    public function save() {
        $this->load->language($this->module_path . 'settings');
        
        if (!$this->user->hasPermission('modify', $this->module_path . 'settings')) {
            $this->response->setOutput(json_encode(['error' => $this->language->get('error_permission')]));
            return;
        }

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $this->load->model('extension/module/shtab/settings');
            
            try {
                $this->model_extension_module_shtab_settings->saveMarketplaceSettings($this->request->post);

                $this->response->setOutput(json_encode([
                    'success' => true,
                    'message' => $this->language->get('text_success')
                ]));

            } catch (Exception $e) {
                $this->response->setOutput(json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]));
            }
        }
    }

    public function testConnection() {
        $this->load->language($this->module_path . 'settings');
        
        if (!$this->user->hasPermission('modify', $this->module_path . 'settings')) {
            $this->response->setOutput(json_encode(['error' => $this->language->get('error_permission')]));
            return;
        }

        $type = $this->request->post['type']; // marketplace или logistics
        $code = $this->request->post['code'];
        $settings = $this->request->post['settings'];

        try {
            if ($type === 'marketplace') {
                $result = $this->testMarketplaceConnection($code, $settings);
            } elseif ($type === 'logistics') {
                $result = $this->testLogisticsConnection($code, $settings);
            } else {
                throw new Exception('Неизвестный тип подключения');
            }

            // Сохраняем результат теста
            $this->load->model('extension/module/shtab/settings');
            $this->model_extension_module_shtab_settings->updateTestResult(
                $code, 
                $result['success'], 
                $result['message'] ?? $result['error'] ?? ''
            );

            $this->response->setOutput(json_encode($result));

        } catch (Exception $e) {
            $this->response->setOutput(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
        }
    }

    public function testAllConnections() {
        $this->load->language($this->module_path . 'settings');
        
        if (!$this->user->hasPermission('modify', $this->module_path . 'settings')) {
            $this->response->setOutput(json_encode(['error' => $this->language->get('error_permission')]));
            return;
        }

        $type = $this->request->post['type']; // marketplace или logistics

        try {
            if ($type === 'marketplace') {
                $results = $this->testAllMarketplaceConnections();
            } elseif ($type === 'logistics') {
                $results = $this->testAllLogisticsConnections();
            } else {
                throw new Exception('Неизвестный тип подключения');
            }

            $this->response->setOutput(json_encode([
                'success' => true,
                'results' => $results
            ]));

        } catch (Exception $e) {
            $this->response->setOutput(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
        }
    }

    private function testMarketplaceConnection($marketplace, $settings) {
        // Заглушка - реальная реализация будет в сервисе
        // Сейчас возвращаем успех для тестирования интерфейса
        return [
            'success' => true,
            'message' => 'Тестовое подключение успешно',
            'marketplace' => $marketplace,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    private function testLogisticsConnection($carrier, $settings) {
        // Заглушка - реальная реализация будет в сервисе
        return [
            'success' => true,
            'message' => 'Тестовое подключение успешно',
            'carrier' => $carrier,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    private function testAllMarketplaceConnections() {
        $this->load->model('extension/module/shtab/settings');
        $marketplaces = $this->model_extension_module_shtab_settings->getMarketplaceSettings();
        
        $results = [];
        foreach ($marketplaces as $code => $settings) {
            if ($settings['enabled']) {
                $results[$code] = $this->testMarketplaceConnection($code, $settings);
            }
        }
        
        return $results;
    }

    private function testAllLogisticsConnections() {
        $this->load->model('extension/module/shtab/settings');
        $carriers = $this->model_extension_module_shtab_settings->getLogisticsSettings();
        
        $results = [];
        foreach ($carriers as $code => $settings) {
            if ($settings['enabled']) {
                $results[$code] = $this->testLogisticsConnection($code, $settings);
            }
        }
        
        return $results;
    }

    private function getMarketplaceSettings() {
        $this->load->model('extension/module/shtab/settings');
        return $this->model_extension_module_shtab_settings->getMarketplaceSettings();
    }

    private function getLogisticsSettings() {
        $this->load->model('extension/module/shtab/settings');
        return $this->model_extension_module_shtab_settings->getLogisticsSettings();
    }

    private function getConnectionStatus() {
        // Заглушка - реальные статусы будут из сервиса
        return [
            'last_checked' => date('Y-m-d H:i:s'),
            'total_marketplaces' => 17,
            'total_carriers' => 12,
            'active_connections' => 0
        ];
    }

    private function prepareTemplateData() {
        $data['user_token'] = $this->session->data['user_token'];
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['menu'] = $this->load->controller($this->module_path . 'shtab_menu');

        // URLs для AJAX запросов
        $data['save_url'] = $this->url->link($this->module_path . 'settings/save', 'user_token=' . $data['user_token'], true);
        $data['test_connection_url'] = $this->url->link($this->module_path . 'settings/testConnection', 'user_token=' . $data['user_token'], true);
        $data['test_all_connections_url'] = $this->url->link($this->module_path . 'settings/testAllConnections', 'user_token=' . $data['user_token'], true);

        // Хлебные крошки
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link($this->module_path . 'settings', 'user_token=' . $data['user_token'], true)
        );

        return $data;
    }
}
?>