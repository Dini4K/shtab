<?php
class ControllerExtensionModuleShtabMenu extends Controller {
    private $module_path = 'extension/module/shtab/';

    public function index() {
        $this->load->language($this->module_path . 'shtab');

        $data['user_token'] = $this->session->data['user_token'];

        // Меню модуля
        $data['menu'] = array(
            'dashboard' => array(
                'name' => $this->language->get('text_dashboard'),
                'href' => $this->url->link($this->module_path . 'shtab', 'user_token=' . $data['user_token'], true),
                'children' => array(),
                'icon' => 'fa-tachometer-alt'
            ),
            'repricing' => array(
                'name' => $this->language->get('text_repricing'),
                'href' => $this->url->link($this->module_path . 'repricing', 'user_token=' . $data['user_token'], true),
                'children' => array(
                    array(
                        'name' => $this->language->get('text_repricing_rules'),
                        'href' => $this->url->link($this->module_path . 'repricing', 'user_token=' . $data['user_token'], true)
                    ),
                    array(
                        'name' => $this->language->get('text_repricing_tasks'),
                        'href' => $this->url->link($this->module_path . 'repricing/tasks', 'user_token=' . $data['user_token'], true)
                    )
                ),
                'icon' => 'fa-chart-line'
            ),
            'settings' => array(
                'name' => $this->language->get('text_settings'),
                'href' => $this->url->link($this->module_path . 'settings', 'user_token=' . $data['user_token'], true),
                'children' => array(),
                'icon' => 'fa-cogs'
            )
        );

        // Определяем активный пункт меню
        $route = isset($this->request->get['route']) ? $this->request->get['route'] : '';
        foreach ($data['menu'] as $key => $item) {
            if (strpos($route, $key) !== false) {
                $data['menu'][$key]['active'] = true;
                break;
            }
        }

        return $this->load->view($this->module_path . 'menu', $data);
    }
}
?>