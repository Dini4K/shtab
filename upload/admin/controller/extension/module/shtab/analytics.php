<?php
class ControllerExtensionModuleShtabAnalytics extends Controller {
    private $error = array();
    private $module_path = 'extension/module/shtab/';

    public function index() {
        $this->load->language($this->module_path . 'analytics');
        $this->document->setTitle($this->language->get('heading_title'));

        // Загружаем Charts.js для графиков
        $this->document->addScript('view/javascript/shtab/chart.min.js');

        $data = $this->prepareTemplateData();

        // Данные для дашборда
        $data['metrics'] = $this->getDashboardMetrics();
        $data['roi_report'] = $this->getROIReport();
        $data['rfm_data'] = $this->getRFMData();

        $this->response->setOutput($this->load->view($this->module_path . 'analytics/dashboard', $data));
    }

    /**
     * Отчет по ROI
     */
    public function roi() {
        $this->load->language($this->module_path . 'analytics');
        $this->document->setTitle($this->language->get('text_roi_report'));

        $data = $this->prepareTemplateData();

        $date_from = isset($this->request->get['date_from']) ? $this->request->get['date_from'] : date('Y-m-01');
        $date_to = isset($this->request->get['date_to']) ? $this->request->get['date_to'] : date('Y-m-d');

        $data['roi_report'] = $this->generateROIReport($date_from, $date_to);
        $data['date_from'] = $date_from;
        $data['date_to'] = $date_to;

        $this->response->setOutput($this->load->view($this->module_path . 'analytics/roi', $data));
    }

    /**
     * RFM-анализ
     */
    public function rfm() {
        $this->load->language($this->module_path . 'analytics');
        $this->document->setTitle($this->language->get('text_rfm_analysis'));

        $data = $this->prepareTemplateData();

        // Запускаем RFM-анализ если нужно
        if (isset($this->request->get['analyze'])) {
            $this->performRFMAnalysis();
            $data['success'] = $this->language->get('text_rfm_analyzed');
        }

        $data['rfm_segments'] = $this->getRFMSegments();

        $this->response->setOutput($this->load->view($this->module_path . 'analytics/rfm', $data));
    }

    /**
     * Экспорт отчета
     */
    public function export() {
        $this->load->language($this->module_path . 'analytics');
        
        $type = $this->request->get['type'] ?? 'roi';
        $format = $this->request->get['format'] ?? 'csv';

        require_once(DIR_SYSTEM . 'library/shtab/service/servicemanager.php');
        $serviceManager = new ShtabServiceManager($this->registry);
        $analyticsService = $serviceManager->analytics();

        switch ($type) {
            case 'roi':
                $report = $analyticsService->generateROIReport(
                    $this->request->get['date_from'] ?? date('Y-m-01'),
                    $this->request->get['date_to'] ?? date('Y-m-d')
                );
                $this->exportROIReport($report, $format);
                break;
            
            case 'sales':
                // Экспорт продаж
                break;
        }
    }

    private function getDashboardMetrics() {
        require_once(DIR_SYSTEM . 'library/shtab/service/servicemanager.php');
        $serviceManager = new ShtabServiceManager($this->registry);
        $analyticsService = $serviceManager->analytics();

        $date_from = date('Y-m-01'); // Начало месяца
        $date_to = date('Y-m-d');

        return $analyticsService->calculateMetrics($date_from, $date_to);
    }

    private function getROIReport() {
        require_once(DIR_SYSTEM . 'library/shtab/service/servicemanager.php');
        $serviceManager = new ShtabServiceManager($this->registry);
        $analyticsService = $serviceManager->analytics();

        $date_from = date('Y-m-01');
        $date_to = date('Y-m-d');

        return $analyticsService->generateROIReport($date_from, $date_to);
    }

    private function getRFMData() {
        $this->load->model('extension/module/shtab/analytics');
        return $this->model_extension_module_shtab_analytics->getRFMSegments();
    }

    private function performRFMAnalysis() {
        require_once(DIR_SYSTEM . 'library/shtab/service/servicemanager.php');
        $serviceManager = new ShtabServiceManager($this->registry);
        $analyticsService = $serviceManager->analytics();

        return $analyticsService->performRFMAnalysis();
    }

    private function getRFMSegments() {
        $this->load->model('extension/module/shtab/analytics');
        return $this->model_extension_module_shtab_analytics->getRFMSegments();
    }

    private function generateROIReport($date_from, $date_to) {
        require_once(DIR_SYSTEM . 'library/shtab/service/servicemanager.php');
        $serviceManager = new ShtabServiceManager($this->registry);
        $analyticsService = $serviceManager->analytics();

        return $analyticsService->generateROIReport($date_from, $date_to);
    }

    private function exportROIReport($report, $format) {
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=roi_report_' . date('Y-m-d') . '.csv');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Маркетплейс', 'Выручка', 'Заказы', 'Инвестиции', 'Прибыль', 'ROI%', 'Средний чек']);
            
            foreach ($report as $row) {
                fputcsv($output, [
                    $row['marketplace'],
                    $row['revenue'],
                    $row['orders'],
                    $row['investment'],
                    $row['profit'],
                    $row['roi_percent'],
                    $row['average_order_value']
                ]);
            }
            
            fclose($output);
            exit;
        }
    }

    private function prepareTemplateData() {
        $data['user_token'] = $this->session->data['user_token'];
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['menu'] = $this->load->controller($this->module_path . 'shtab_menu');

        // URLs
        $data['action'] = $this->url->link($this->module_path . 'analytics', 'user_token=' . $data['user_token'], true);
        $data['roi_url'] = $this->url->link($this->module_path . 'analytics/roi', 'user_token=' . $data['user_token'], true);
        $data['rfm_url'] = $this->url->link($this->module_path . 'analytics/rfm', 'user_token=' . $data['user_token'], true);
        $data['export_url'] = $this->url->link($this->module_path . 'analytics/export', 'user_token=' . $data['user_token'], true);

        // Хлебные крошки
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link($this->module_path . 'analytics', 'user_token=' . $this->session->data['user_token'], true)
        );

        return $data;
    }
}
?>