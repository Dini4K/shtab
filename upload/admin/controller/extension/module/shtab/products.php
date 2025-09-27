<?php
class ControllerExtensionModuleShtabProducts extends Controller {
    private $error = array();
    private $module_path = 'extension/module/shtab/';

    public function index() {
        $this->load->language($this->module_path . 'products');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->library('shtab/shtab');
        $serviceManager = new ShtabServiceManager($this->registry);
        $productService = $serviceManager->product();

        // Фильтры
        $filter_data = $this->getFilterData();

        // Получаем товары
        $data['products'] = $productService->getProductsForRepricing($filter_data);
        $data['total'] = count($data['products']);

        // Настройки пагинации
        $pagination = new Pagination();
        $pagination->total = $data['total'];
        $pagination->page = $filter_data['page'];
        $pagination->limit = $this->config->get('config_limit_admin');
        $pagination->url = $this->url->link($this->module_path . 'products', 'user_token=' . $this->session->data['user_token'] . '&page={page}', true);
        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($data['total']) ? (($filter_data['page'] - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($filter_data['page'] - 1) * $this->config->get('config_limit_admin')) > ($data['total'] - $this->config->get('config_limit_admin'))) ? $data['total'] : ((($filter_data['page'] - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $data['total'], ceil($data['total'] / $this->config->get('config_limit_admin')));

        // Подготовка данных для шаблона
        $data = $this->prepareTemplateData($data);

        $this->response->setOutput($this->load->view($this->module_path . 'products/list', $data));
    }

    public function save() {
        $this->load->language($this->module_path . 'products');
        
        if (!$this->user->hasPermission('modify', $this->module_path . 'products')) {
            $this->response->setOutput(json_encode(['error' => $this->language->get('error_permission')]));
            return;
        }

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $this->load->library('shtab/shtab');
            $serviceManager = new ShtabServiceManager($this->registry);
            $productService = $serviceManager->product();

            try {
                $product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
                $data = $this->request->post;

                $productService->saveProductData($product_id, $data);

                $this->response->setOutput(json_encode(['success' => true]));
            } catch (Exception $e) {
                $this->response->setOutput(json_encode(['error' => $e->getMessage()]));
            }
        }
    }

    private function getFilterData() {
        return [
            'page' => isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1,
            'marketplace' => isset($this->request->get['filter_marketplace']) ? $this->request->get['filter_marketplace'] : null,
            'limit' => $this->config->get('config_limit_admin')
        ];
    }

    private function prepareTemplateData($data) {
        $data['user_token'] = $this->session->data['user_token'];
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['menu'] = $this->load->controller($this->module_path . 'shtab_menu');

        // Хлебные крошки
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link($this->module_path . 'products', 'user_token=' . $this->session->data['user_token'], true)
        );

        return $data;
    }
}
?>