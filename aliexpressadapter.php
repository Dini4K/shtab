<?php
require_once(DIR_SYSTEM . 'library/shtab/marketplace/adapter/abstractadapter.php');

class ShtabAliexpressAdapter extends ShtabMarketplaceAdapter {
    private $base_url = 'https://api.aliexpress.ru/';
    private $app_key;
    private $app_secret;
    private $session_key;

    protected function loadSettings() {
        $this->load->model('extension/module/shtab/settings');
        
        $this->app_key = $this->decrypt($this->model_extension_module_shtab_settings->getSetting('shtab_aliexpress_app_key'));
        $this->app_secret = $this->decrypt($this->model_extension_module_shtab_settings->getSetting('shtab_aliexpress_app_secret'));
        $this->session_key = $this->decrypt($this->model_extension_module_shtab_settings->getSetting('shtab_aliexpress_session_key'));
        $this->enabled = (bool)$this->model_extension_module_shtab_settings->getSetting('shtab_aliexpress_enabled');
    }

    protected function performConnectionTest() {
        return ['success' => true, 'message' => 'AliExpress adapter - реализация в процессе'];
    }

    protected function performGetOrders($params = []) {
        return ['success' => true, 'orders' => [], 'total' => 0];
    }

    protected function performUpdatePrices($prices) {
        return ['success' => true, 'processed' => 0];
    }

    protected function performUpdateStocks($stocks) {
        return ['success' => true, 'processed' => 0];
    }

    protected function performGetProducts($params = []) {
        return ['success' => true, 'products' => [], 'total' => 0];
    }

    protected function getMarketplaceCode() { return 'aliexpress'; }
    protected function getMarketplaceName() { return 'AliExpress Russia'; }
    protected function getRequiredSettings() { return ['app_key', 'app_secret', 'session_key']; }
}
?>