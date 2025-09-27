<?php
class ControllerExtensionModuleShtabRepricing extends Controller {
    private $error = array();
    private $module_path = 'extension/module/shtab/';

    public function index() {
        $this->load->language($this->module_path . 'repricing');
        $this->document->setTitle($this->language->get('heading_title'));

        // Загружаем Vue.js для конструктора правил
        $this->document->addScript('view/javascript/shtab/vue.min.js');
        $this->document->addScript('view/javascript/shtab/rete.js');
        $this->document->addScript('view/javascript/shtab/rule-builder.js');

        $data = $this->prepareTemplateData();

        // Статистика для дашборда
        $data['stats'] = $this->getRepricingStats();

        $this->response->setOutput($this->load->view($this->module_path . 'repricing/dashboard', $data));
    }

    /**
     * Конструктор правил (Vue.js приложение)
     */
    public function rules() {
        $this->load->language($this->module_path . 'repricing_rules');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->document->addScript('view/javascript/shtab/vue.min.js');
        $this->document->addScript('view/javascript/shtab/rete.js');
        $this->document->addScript('view/javascript/shtab/rule-builder.js');

        $data = $this->prepareTemplateData();
        $data['rules'] = $this->getRulesList();

        $this->response->setOutput($this->load->view($this->module_path . 'repricing/rules/builder', $data));
    }

    /**
     * Список задач CRON
     */
    public function tasks() {
        $this->load->language($this->module_path . 'repricing_tasks');
        $this->document->setTitle($this->language->get('heading_title'));

        $data = $this->prepareTemplateData();
        $data['tasks'] = $this->getTasksList();

        $this->response->setOutput($this->load->view($this->module_path . 'repricing/tasks/list', $data));
    }

    /**
     * История изменений цен
     */
    public function history() {
        $this->load->language($this->module_path . 'repricing_history');
        $this->document->setTitle($this->language->get('heading_title'));

        $data = $this->prepareTemplateData();
        $data['history'] = $this->getPriceHistory();

        $this->response->setOutput($this->load->view($this->module_path . 'repricing/history/list', $data));
    }

    /**
     * API: Сохранить правило
     */
    public function saveRule() {
        $this->load->language($this->module_path . 'repricing_rules');
        
        if (!$this->user->hasPermission('modify', $this->module_path . 'repricing')) {
            $this->response->setOutput(json_encode(['error' => $this->language->get('error_permission')]));
            return;
        }

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $this->load->model('extension/module/shtab/repricing_rules');
            
            $rule_data = $this->request->post;
            $rule_id = isset($rule_data['rule_id']) ? (int)$rule_data['rule_id'] : 0;

            try {
                if ($rule_id) {
                    $this->model_extension_module_shtab_repricing_rules->editRule($rule_id, $rule_data);
                } else {
                    $rule_id = $this->model_extension_module_shtab_repricing_rules->addRule($rule_data);
                }

                $this->response->setOutput(json_encode(['success' => true, 'rule_id' => $rule_id]));
            } catch (Exception $e) {
                $this->response->setOutput(json_encode(['error' => $e->getMessage()]));
            }
        }
    }

    /**
     * API: Запустить репрайсинг вручную
     */
    public function runManual() {
        $this->load->language($this->module_path . 'repricing');
        
        if (!$this->user->hasPermission('modify', $this->module_path . 'repricing')) {
            $this->response->setOutput(json_encode(['error' => $this->language->get('error_permission')]));
            return;
        }

        try {
            $product_ids = isset($this->request->post['product_ids']) ? $this->request->post['product_ids'] : [];
            $marketplace = isset($this->request->post['marketplace']) ? $this->request->post['marketplace'] : null;

            // Используем ServiceManager для загрузки RuleEngine
            require_once(DIR_SYSTEM . 'library/shtab/service/servicemanager.php');
            $serviceManager = new ShtabServiceManager($this->registry);
            $ruleEngine = $serviceManager->ruleEngine();
            
            $results = [];

            if (empty($product_ids)) {
                // Все товары
                $products = $serviceManager->product()->getProductsForRepricing();
                $product_ids = array_column($products, 'product_id');
            }

            foreach ($product_ids as $product_id) {
                $changes = $ruleEngine->applyRulesToProduct($product_id, $marketplace);
                if ($changes) {
                    $results[$product_id] = $changes;
                }
            }

            $this->response->setOutput(json_encode([
                'success' => true, 
                'processed' => count($product_ids),
                'changes' => count($results, COUNT_RECURSIVE) - count($results)
            ]));

        } catch (Exception $e) {
            $this->response->setOutput(json_encode(['error' => $e->getMessage()]));
        }
    }

    private function getRepricingStats() {
        $this->load->model('extension/module/shtab/repricing');
        return $this->model_extension_module_shtab_repricing->getStats();
    }

    private function getRulesList() {
        $this->load->model('extension/module/shtab/repricing_rules');
        return $this->model_extension_module_shtab_repricing_rules->getRules();
    }

    private function getTasksList() {
        $this->load->model('extension/module/shtab/repricing_tasks');
        return $this->model_extension_module_shtab_repricing_tasks->getTasks();
    }

    private function getPriceHistory() {
        $this->load->model('extension/module/shtab/repricing_history');
        return $this->model_extension_module_shtab_repricing_history->getHistory();
    }

    private function prepareTemplateData() {
        $data['user_token'] = $this->session->data['user_token'];
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['menu'] = $this->load->controller($this->module_path . 'shtab_menu');

        // URLs для навигации
        $data['add_rule_url'] = $this->url->link($this->module_path . 'repricing/rules', 'user_token=' . $data['user_token'], true);
        $data['tasks_url'] = $this->url->link($this->module_path . 'repricing/tasks', 'user_token=' . $data['user_token'], true);
        $data['history_url'] = $this->url->link($this->module_path . 'repricing/history', 'user_token=' . $data['user_token'], true);

        // Хлебные крошки
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link($this->module_path . 'repricing', 'user_token=' . $this->session->data['user_token'], true)
        );

        return $data;
    }
}
?>