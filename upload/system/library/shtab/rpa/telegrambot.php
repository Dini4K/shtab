<?php
class ShtabTelegramBot {
    protected $registry;
    protected $db;
    protected $log;
    protected $bot_token;
    protected $api_url;

    public function __construct($registry) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->log = $registry->get('log');
        $this->bot_token = $this->config->get('shtab_telegram_bot_token');
        $this->api_url = 'https://api.telegram.org/bot' . $this->bot_token . '/';
    }

    /**
     * Отправка уведомления в Telegram
     */
    public function sendNotification($chat_id, $message, $options = []) {
        if (!$this->bot_token) {
            $this->log->write('SHTAB Telegram: Bot token not configured');
            return false;
        }

        $data = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];

        if (isset($options['keyboard'])) {
            $data['reply_markup'] = json_encode(['inline_keyboard' => $options['keyboard']]);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url . 'sendMessage');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            return true;
        } else {
            $this->log->write("SHTAB Telegram ERROR: $http_code - " . $response);
            return false;
        }
    }

    /**
     * Отправка уведомления о новом заказе
     */
    public function sendOrderNotification($order_data) {
        $message = "🆕 <b>Новый заказ!</b>\n\n";
        $message .= "📦 №: " . $order_data['external_order_number'] . "\n";
        $message .= "🏪 Маркетплейс: " . $order_data['marketplace'] . "\n";
        $message .= "👤 Клиент: " . $order_data['customer_name'] . "\n";
        $message .= "💳 Сумма: " . number_format($order_data['total_amount'], 2) . " ₽\n";
        $message .= "📮 Адрес: " . $order_data['shipping_address'] . "\n\n";
        
        $keyboard = [
            [
                ['text' => '📋 Открыть заказ', 'url' => $this->getOrderUrl($order_data['shtab_order_id'])]
            ]
        ];

        return $this->sendNotification($this->config->get('shtab_telegram_chat_id'), $message, [
            'keyboard' => $keyboard
        ]);
    }

    /**
     * Отправка уведомления об ошибке
     */
    public function sendErrorNotification($error_message, $context = []) {
        $message = "🚨 <b>Критическая ошибка!</b>\n\n";
        $message .= "📝 " . $error_message . "\n";
        
        if (!empty($context)) {
            $message .= "\n📊 Контекст:\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        return $this->sendNotification($this->config->get('shtab_telegram_chat_id'), $message);
    }

    /**
     * Отправка ежедневного отчета
     */
    public function sendDailyReport() {
        require_once(DIR_SYSTEM . 'library/shtab/service/servicemanager.php');
        $serviceManager = new ShtabServiceManager($this->registry);
        $analyticsService = $serviceManager->analytics();

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $metrics = $analyticsService->calculateMetrics($yesterday, $yesterday);

        $message = "📊 <b>Ежедневный отчет</b> - " . date('d.m.Y') . "\n\n";
        $message .= "🛒 Заказов: " . $metrics['orders']['count'] . "\n";
        $message .= "💰 Выручка: " . number_format($metrics['sales']['total'], 2) . " ₽\n";
        $message .= "💵 Прибыль: " . number_format($metrics['profit']['profit'], 2) . " ₽\n";
        $message .= "📈 Маржа: " . number_format($metrics['profit']['margin'], 1) . "%\n";
        $message .= "🎯 ROI: " . number_format($metrics['roi']['roi_percent'], 1) . "%\n";

        return $this->sendNotification($this->config->get('shtab_telegram_chat_id'), $message);
    }

    /**
     * Обработка входящих сообщений от Telegram
     */
    public function handleWebhook($update) {
        if (!isset($update['message'])) {
            return;
        }

        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $text = $message['text'] ?? '';

        switch ($text) {
            case '/start':
                $this->sendWelcomeMessage($chat_id);
                break;
                
            case '/stats':
                $this->sendCurrentStats($chat_id);
                break;
                
            case '/orders':
                $this->sendRecentOrders($chat_id);
                break;
                
            case '/alerts':
                $this->sendActiveAlerts($chat_id);
                break;
                
            default:
                $this->sendHelpMessage($chat_id);
        }
    }

    /**
     * Отправка приветственного сообщения
     */
    protected function sendWelcomeMessage($chat_id) {
        $message = "🤖 <b>SHTAB AI Assistant</b>\n\n";
        $message .= "Я ваш помощник в управлении e-commerce бизнесом.\n\n";
        $message .= "Доступные команды:\n";
        $message .= "/stats - Текущая статистика\n";
        $message .= "/orders - Последние заказы\n";
        $message .= "/alerts - Активные предупреждения\n";
        $message .= "/help - Справка по командам";

        $this->sendNotification($chat_id, $message);
    }

    /**
     * Отправка текущей статистики
     */
    protected function sendCurrentStats($chat_id) {
        require_once(DIR_SYSTEM . 'library/shtab/service/servicemanager.php');
        $serviceManager = new ShtabServiceManager($this->registry);
        $analyticsService = $serviceManager->analytics();

        $today = date('Y-m-d');
        $metrics = $analyticsService->calculateMetrics($today, $today);

        $message = "📈 <b>Статистика за сегодня</b>\n\n";
        $message .= "🛒 Заказов: " . $metrics['orders']['count'] . "\n";
        $message .= "💰 Выручка: " . number_format($metrics['sales']['total'], 2) . " ₽\n";
        $message .= "💵 Прибыль: " . number_format($metrics['profit']['profit'], 2) . " ₽\n";

        $this->sendNotification($chat_id, $message);
    }

    /**
     * Генерация URL для заказа
     */
    protected function getOrderUrl($order_id) {
        return HTTPS_CATALOG . 'admin/index.php?route=extension/module/shtab/orders&user_token=XXX&filter_order_id=' . $order_id;
    }
}
?>