<?php
class ControllerExtensionModuleShtab extends Controller {
    private $error = array();
    private $module_path = 'extension/module/shtab/';
    private $module_code = 'shtab';

    public function __construct($registry) {
        parent::__construct($registry);
        
        // Загружаем основные сервисы
        $this->load->library('shtab/shtab');
        $this->load->language($this->module_path . 'shtab');
    }

    public function index() {
        // Проверка прав доступа
        if (!$this->user->hasPermission('modify', $this->module_path . 'shtab')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        // Устанавливаем заголовок
        $this->document->setTitle($this->language->get('heading_title'));

        // Хлебные крошки
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_module'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link($this->module_path . 'shtab', 'user_token=' . $this->session->data['user_token'], true)
        );

        // CSRF токен
        $data['user_token'] = $this->session->data['user_token'];
        $data['action'] = $this->url->link($this->module_path . 'shtab/save', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        // Загружаем настройки
        $data['settings'] = $this->getSettings();

        // Обработка ошибок
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        // Подключаем шаблоны
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['menu'] = $this->load->controller($this->module_path . 'shtab_menu');

        $this->response->setOutput($this->load->view($this->module_path . 'dashboard', $data));
    }

    public function save() {
        if (!$this->user->hasPermission('modify', $this->module_path . 'shtab')) {
            $this->response->setOutput(json_encode(['error' => $this->language->get('error_permission')]));
            return;
        }

        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
            $this->load->model('extension/module/shtab/shtab');
            
            foreach ($this->request->post as $key => $value) {
                $this->model_extension_module_shtab_shtab->setSetting($key, $value);
            }

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->setOutput(json_encode(['success' => true]));
        } else {
            $this->response->setOutput(json_encode(['error' => $this->error]));
        }
    }

    private function validate() {
        // Базовая валидация
        if (!$this->user->hasPermission('modify', $this->module_path . 'shtab')) {
            $this->error['permission'] = $this->language->get('error_permission');
        }

        // Валидация Redis
        if (!empty($this->request->post['shtab_redis_host']) && !filter_var($this->request->post['shtab_redis_host'], FILTER_VALIDATE_IP)) {
            $this->error['redis_host'] = $this->language->get('error_redis_host');
        }

        return !$this->error;
    }

    private function getSettings() {
        $this->load->model('extension/module/shtab/shtab');
        return $this->model_extension_module_shtab_shtab->getSettings();
    }

    public function install() {
        $this->load->model('extension/module/shtab/shtab');
        $this->model_extension_module_shtab_shtab->install();

        // Добавляем права доступа
        $this->load->model('user/user_group');
        $this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', $this->module_path . 'shtab');
        $this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', $this->module_path . 'shtab');
        
        // Добавляем права для всех дочерних контроллеров
        $controllers = ['shtab_menu', 'repricing', 'settings', 'products', 'orders', 'suppliers', 'logistics', 'analytics', 'ai', 'security', 'mobile'];
        foreach ($controllers as $controller) {
            $this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', $this->module_path . $controller);
            $this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', $this->module_path . $controller);
        }
    }

    public function uninstall() {
        $this->load->model('extension/module/shtab/shtab');
        $this->model_extension_module_shtab_shtab->uninstall();
    }
}
?>