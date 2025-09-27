<?php
class ControllerExtensionModuleShtabOrders extends Controller {
    private $error = array();
    private $module_path = 'extension/module/shtab/';

    public function index() {
        $this->load->language($this->module_path . 'orders');
        $this->document->setTitle($this->language->get('heading_title'));

        $data = $this->prepareTemplateData();

        // Фильтры
        $filter_data = $this->getFilterData();
        $data['orders'] = $this->getOrdersList($filter_data);
        $data['total_orders'] = $this->getTotalOrders($filter_data);

        // Пагинация
        $pagination = new Pagination();
        $pagination->total = $data['total_orders'];
        $pagination->page = $filter_data['page'];
        $pagination->limit = $this->config->get('config_limit_admin');
        $pagination->url = $this->url->link($this->module_path . 'orders', 'user_token=' . $this->session->data['user_token'] . '&page={page}', true);
        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($data['total_orders']) ? (($filter_data['page'] - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($filter_data['page'] - 1) * $this->config->get('config_limit_admin')) > ($data['total_orders'] - $this->config->get('config_limit_admin'))) ? $data['total_orders'] : ((($filter_data['page'] - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $data['total_orders'], ceil($data['total_orders'] / $this->config->get('config_limit_admin')));

        $this->response->setOutput($this->load->view($this->module_path . 'orders/list', $data));
    }

    /**
     * Импорт заказов
     */
    public function import() {
        $this->load->language($this->module_path . 'orders');
        
        if (!$this->user->hasPermission('modify', $this->module_path . 'orders')) {
            $this->response->setOutput(json_encode(['error' => $this->language->get('error_permission')]));
            return;
        }

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $marketplace = $this->request->post['marketplace'];
            $days_back = isset($this->request->post['days_back']) ? (int)$this->request->post['days_back'] : 1;

            try {
                require_once(DIR_SYSTEM . 'library/shtab/service/servicemanager.php');
                $serviceManager = new ShtabServiceManager($this->registry);
                $orderService = $serviceManager->order();

                $params = [
                    'days_back' => $days_back,
                    'status' => 'awaiting_delivery,awaiting_packaging'
                ];

                $result = $orderService->importOrders($marketplace, $params);

                $this->response->setOutput(json_encode($result));

            } catch (Exception $e) {
                $this->response->setOutput(json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]));
            }
        }
    }

    /**
     * Создать заказ в OpenCart
     */
    public function createOpencartOrder() {
        $this->load->language($this->module_path . 'orders');
        
        if (!$this->user->hasPermission('modify', $this->module_path . 'orders')) {
            $this->response->setOutput(json_encode(['error' => $this->language->get('error_permission')]));
            return;
        }

        if (isset($this->request->post['shtab_order_id'])) {
            $shtab_order_id = (int)$this->request->post['shtab_order_id'];

            try {
                require_once(DIR_SYSTEM . 'library/shtab/service/servicemanager.php');
                $serviceManager = new ShtabServiceManager($this->registry);
                $orderService = $serviceManager->order();

                $opencart_order_id = $orderService->createOpencartOrder($shtab_order_id);

                $this->response->setOutput(json_encode([
                    'success' => true,
                    'opencart_order_id' => $opencart_order_id,
                    'message' => sprintf($this->language->get('text_order_created'), $opencart_order_id)
                ]));

            } catch (Exception $e) {
                $this->response->setOutput(json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]));
            }
        }
    }

    /**
     * Пакетное создание заказов
     */
    public function batchCreateOrders() {
        $this->load->language($this->module_path . 'orders');
        
        if (!$this->user->hasPermission('modify', $this->module_path . 'orders')) {
            $this->response->setOutput(json_encode(['error' => $this->language->get('error_permission')]));
            return;
        }

        if (isset($this->request->post['order_ids'])) {
            $order_ids = $this->request->post['order_ids'];
            $results = [];

            require_once(DIR_SYSTEM . 'library/shtab/service/servicemanager.php');
            $serviceManager = new ShtabServiceManager($this->registry);
            $orderService = $serviceManager->order();

            foreach ($order_ids as $shtab_order_id) {
                try {
                    $opencart_order_id = $orderService->createOpencartOrder($shtab_order_id);
                    $results[] = [
                        'shtab_order_id' => $shtab_order_id,
                        'success' => true,
                        'opencart_order_id' => $opencart_order_id
                    ];
                } catch (Exception $e) {
                    $results[] = [
                        'shtab_order_id' => $shtab_order_id,
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $this->response->setOutput(json_encode([
                'success' => true,
                'results' => $results
            ]));
        }
    }

    private function getOrdersList($filter_data) {
        $this->load->model('extension/module/shtab/orders');
        return $this->model_extension_module_shtab_orders->getOrders($filter_data);
    }

    private function getTotalOrders($filter_data) {
        $this->load->model('extension/module/shtab/orders');
        return $this->model_extension_module_shtab_orders->getTotalOrders($filter_data);
    }

    private function getFilterData() {
        return [
            'marketplace' => isset($this->request->get['filter_marketplace']) ? $this->request->get['filter_marketplace'] : null,
            'status' => isset($this->request->get['filter_status']) ? $this->request->get['filter_status'] : null,
            'date_from' => isset($this->request->get['filter_date_from']) ? $this->request->get['filter_date_from'] : null,
            'date_to' => isset($this->request->get['filter_date_to']) ? $this->request->get['filter_date_to'] : null,
            'is_processed' => isset($this->request->get['filter_processed']) ? $this->request->get['filter_processed'] : null,
            'page' => isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1,
            'limit' => $this->config->get('config_limit_admin')
        ];
    }

    private function prepareTemplateData() {
        $data['user_token'] = $this->session->data['user_token'];
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['menu'] = $this->load->controller($this->module_path . 'shtab_menu');

        // URLs для действий
        $data['import_url'] = $this->url->link($this->module_path . 'orders/import', 'user_token=' . $data['user_token'], true);
        $data['create_order_url'] = $this->url->link($this->module_path . 'orders/createOpencartOrder', 'user_token=' . $data['user_token'], true);
        $data['batch_create_url'] = $this->url->link($this->module_path . 'orders/batchCreateOrders', 'user_token=' . $data['user_token'], true);

        // Хлебные крошки
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link($this->module_path . 'orders', 'user_token=' . $this->session->data['user_token'], true)
        );

        return $data;
    }
}
?>