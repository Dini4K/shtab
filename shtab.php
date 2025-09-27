<?php
class Shtab {
    protected $registry;
    protected $settings;
    protected $logger;

    public function __construct($registry) {
        $this->registry = $registry;
        $this->loadSettings();
    }

    private function loadSettings() {
        $model = $this->registry->get('load')->model('extension/module/shtab/shtab');
        $this->settings = $model->getSettings();
    }

    public function getSetting($key, $default = null) {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    public function log($message, $type = 'info', $context = array()) {
        if (!$this->logger) {
            $this->logger = new Log('shtab.log');
        }
        
        $log_message = date('Y-m-d H:i:s') . ' - ' . strtoupper($type) . ' - ' . $message;
        if ($context) {
            $log_message .= ' - ' . json_encode($context);
        }
        
        $this->logger->write($log_message);
    }
}
?>