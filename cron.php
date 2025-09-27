<?php
class ControllerExtensionModuleShtabCron extends Controller {
    public function index() {
        // Проверяем ключ безопасности
        $cron_key = $this->config->get('shtab_cron_key');
        if (empty($cron_key) || !isset($this->request->get['key']) || $this->request->get['key'] !== $cron_key) {
            $this->response->setOutput('Unauthorized');
            return;
        }

        // Загружаем необходимые файлы
        require_once(DIR_SYSTEM . 'library/shtab/service/servicemanager.php');
        require_once(DIR_SYSTEM . 'library/shtab/repricing/cronmanager.php');
        
        $cronManager = new ShtabCronManager($this->registry);
        $tasks_run = $cronManager->runScheduledTasks();
        
        $this->response->setOutput("Выполнено задач: $tasks_run");
    }
}
?>