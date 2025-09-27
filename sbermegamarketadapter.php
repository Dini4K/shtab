<?php
require_once(DIR_SYSTEM . 'library/shtab/marketplace/adapter/abstractadapter.php');

class ShtabSbermegamarketAdapter extends ShtabMarketplaceAdapter {
    private $base_url = 'https://api.megamarket.com/';
    private $api_key;
    private $client_id;
    private $merchant_id;

    protected function loadSettings() {
        $this->load->model('extension/module/shtab/settings');
        
        $this->api_key = $this->decrypt($this->model_extension_module_shtab_settings->getSetting('shtab_sbermegamarket_api_key'));
        $this->client_id = $this->decrypt($this->model_extension_module_shtab_settings->getSetting('shtab_sbermegamarket_client_id'));
        $this->merchant_id = $this->decrypt($this->model_extension_module_shtab_settings->getSetting('shtab_sbermegamarket_merchant_id'));
        $this->enabled = (bool)$this->model_extension_module_shtab_settings->getSetting('shtab_sbermegamarket_enabled');
    }

    protected function performConnectionTest() {
        return ['success' => true, 'message' => 'СберМегаМаркет adapter - реализация в процессе'];
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

    protected function getMarketplaceCode() { return 'sbermegamarket'; }
    protected function getMarketplaceName() { return 'СберМегаМаркет'; }
    protected function getRequiredSettings() { return ['api_key', 'client_id', 'merchant_id']; }
}
?>