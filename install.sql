-- Таблица настроек модуля
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_setting` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` text,
  `serialized` tinyint(1) NOT NULL DEFAULT 0,
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица для логов модуля
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(32) NOT NULL COMMENT 'error, warning, info, debug',
  `message` text NOT NULL,
  `context` text,
  `user_id` int(11) DEFAULT NULL,
  `ip` varchar(40) NOT NULL,
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `type` (`type`),
  KEY `user_id` (`user_id`),
  KEY `date_added` (`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Вставляем настройки по умолчанию
INSERT INTO `{{DB_PREFIX}}shtab_setting` (`key`, `value`, `serialized`) VALUES
('shtab_status', '1', 0),
('shtab_version', '1.0.0', 0),
('shtab_redis_host', '127.0.0.1', 0),
('shtab_redis_port', '6379', 0),
('shtab_redis_prefix', 'shtab_', 0),
('shtab_telegram_bot_token', '', 0),
('shtab_telegram_chat_id', '', 0);

-- Таблица товаров для репрайсинга
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_product` (
  `product_id` int(11) NOT NULL,
  `cost_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `min_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `max_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `rrp_price` decimal(15,4) NOT NULL DEFAULT '0.0000' COMMENT 'Рекомендуемая цена',
  `last_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `repricing_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `custom_data` text COMMENT 'Дополнительные данные (JSON)',
  PRIMARY KEY (`product_id`),
  KEY `repricing_enabled` (`repricing_enabled`),
  KEY `last_updated` (`last_updated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица привязки товаров к маркетплейсам
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_product_to_marketplace` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `marketplace` varchar(50) NOT NULL COMMENT 'ozon, wildberries, yandex_market',
  `external_id` varchar(100) NOT NULL COMMENT 'ID товара на маркетплейсе',
  `external_sku` varchar(100) DEFAULT NULL COMMENT 'Артикул на маркетплейсе',
  `price` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `old_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `stock` int(11) NOT NULL DEFAULT '0',
  `status` varchar(20) NOT NULL DEFAULT 'active' COMMENT 'active, disabled, error',
  `last_sync` datetime DEFAULT NULL,
  `sync_error` text,
  `custom_data` text COMMENT 'Данные маркетплейса (JSON)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_marketplace` (`product_id`, `marketplace`),
  KEY `marketplace_external_id` (`marketplace`, `external_id`),
  KEY `last_sync` (`last_sync`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица цен конкурентов (для парсинга)
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_competitor_price` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `competitor_name` varchar(100) NOT NULL COMMENT 'Название конкурента',
  `competitor_price` decimal(15,4) NOT NULL,
  `competitor_url` varchar(500) DEFAULT NULL,
  `stock_info` varchar(100) DEFAULT NULL,
  `last_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_competitor` (`product_id`, `competitor_name`),
  KEY `last_updated` (`last_updated`),
  KEY `competitor_name` (`competitor_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица правил репрайсинга
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_repricing_rule` (
  `rule_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `conditions` text NOT NULL COMMENT 'Условия в JSON',
  `actions` text NOT NULL COMMENT 'Действия в JSON',
  `priority` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `stop_on_match` tinyint(1) NOT NULL DEFAULT '0',
  `apply_to_all_marketplaces` tinyint(1) NOT NULL DEFAULT '1',
  `specific_marketplaces` text COMMENT 'Список маркетплейсов',
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`rule_id`),
  KEY `is_active` (`is_active`),
  KEY `priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица CRON заданий
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_repricing_task` (
  `task_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `rule_ids` text NOT NULL COMMENT 'ID правил через запятую',
  `cron_expression` varchar(100) NOT NULL DEFAULT '0 * * * *' COMMENT 'Cron expression',
  `last_run` datetime DEFAULT NULL,
  `next_run` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `run_count` int(11) NOT NULL DEFAULT '0',
  `last_duration` int(11) DEFAULT NULL COMMENT 'Длительность в секундах',
  `last_error` text,
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`task_id`),
  KEY `is_active` (`is_active`),
  KEY `next_run` (`next_run`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица истории изменений цен
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_price_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `marketplace` varchar(50) NOT NULL,
  `old_price` decimal(15,4) NOT NULL,
  `new_price` decimal(15,4) NOT NULL,
  `change_type` varchar(20) NOT NULL COMMENT 'auto, manual, rule',
  `rule_id` int(11) DEFAULT NULL,
  `task_id` int(11) DEFAULT NULL,
  `reason` text COMMENT 'Причина изменения',
  `user_id` int(11) DEFAULT NULL,
  `ip` varchar(40) NOT NULL,
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `product_id` (`product_id`),
  KEY `marketplace` (`marketplace`),
  KEY `change_type` (`change_type`),
  KEY `date_added` (`date_added`),
  KEY `rule_id` (`rule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица мониторинга цен конкурентов
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_competitor_monitoring` (
  `monitor_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `competitor_name` varchar(100) NOT NULL,
  `competitor_url` varchar(500) NOT NULL,
  `current_price` decimal(15,4) DEFAULT NULL,
  `previous_price` decimal(15,4) DEFAULT NULL,
  `last_check` datetime DEFAULT NULL,
  `check_interval` int(11) NOT NULL DEFAULT 3600 COMMENT 'Интервал в секундах',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_error` text,
  PRIMARY KEY (`monitor_id`),
  UNIQUE KEY `product_competitor` (`product_id`, `competitor_name`),
  KEY `is_active` (`is_active`),
  KEY `last_check` (`last_check`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Примеры правил по умолчанию
INSERT INTO `{{DB_PREFIX}}shtab_repricing_rule` (`name`, `description`, `conditions`, `actions`, `priority`) VALUES
('Защита минимальной цены', 'Не опускаться ниже минимальной цены', '{"condition":"and","rules":[{"field":"new_price","operator":"less","value":"min_price"}]}', '{"action":"set_price","value":"min_price","reason":"Защита минимальной цены"}', 100),
('Следование за конкурентом', 'Установить цену на 5% ниже конкурента', '{"condition":"and","rules":[{"field":"competitor_price","operator":"greater","value":0}]}', '{"action":"set_price","value":"competitor_price * 0.95","reason":"Следование за конкурентом"}', 50);

-- Таблица унифицированных заказов
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_order` (
  `shtab_order_id` int(11) NOT NULL AUTO_INCREMENT,
  `marketplace` varchar(50) NOT NULL,
  `external_order_id` varchar(100) NOT NULL,
  `external_order_number` varchar(100) DEFAULT NULL,
  `order_status` varchar(50) NOT NULL DEFAULT 'new',
  `order_date` datetime NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `shipping_address` text NOT NULL,
  `shipping_city` varchar(255) DEFAULT NULL,
  `shipping_region` varchar(255) DEFAULT NULL,
  `shipping_postcode` varchar(20) DEFAULT NULL,
  `shipping_country` varchar(100) DEFAULT NULL,
  `shipping_method` varchar(255) DEFAULT NULL,
  `payment_method` varchar(255) DEFAULT NULL,
  `total_amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `shipping_cost` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `commission` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `items` text NOT NULL COMMENT 'JSON с товарами',
  `delivery_date` datetime DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `carrier_name` varchar(100) DEFAULT NULL,
  `notes` text,
  `is_processed` tinyint(1) NOT NULL DEFAULT '0',
  `opencart_order_id` int(11) DEFAULT NULL,
  `last_sync` datetime DEFAULT NULL,
  `sync_error` text,
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`shtab_order_id`),
  UNIQUE KEY `marketplace_order` (`marketplace`, `external_order_id`),
  KEY `order_status` (`order_status`),
  KEY `order_date` (`order_date`),
  KEY `is_processed` (`is_processed`),
  KEY `opencart_order_id` (`opencart_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица поставщиков
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_supplier` (
  `supplier_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `address` text,
  `inn` varchar(20) DEFAULT NULL COMMENT 'ИНН',
  `kpp` varchar(20) DEFAULT NULL COMMENT 'КПП',
  `bank_details` text COMMENT 'Реквизиты банка (JSON)',
  `lead_time` int(11) DEFAULT 0 COMMENT 'Срок поставки в днях',
  `reliability_score` decimal(3,2) DEFAULT '5.00' COMMENT 'Оценка надежности 1-5',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text,
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`supplier_id`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица рейтингов поставщиков
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_supplier_rating` (
  `rating_id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `rating` tinyint(1) NOT NULL DEFAULT 5 COMMENT '1-5',
  `comment` text,
  `rating_type` varchar(50) NOT NULL COMMENT 'delivery, quality, communication',
  `rated_by` varchar(100) DEFAULT NULL,
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rating_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `rating_type` (`rating_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица заявок в транспортные компании
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_logistics_request` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `carrier` varchar(50) NOT NULL COMMENT 'Название ТК',
  `service_type` varchar(100) NOT NULL COMMENT 'Тип услуги',
  `pickup_address` text NOT NULL,
  `delivery_address` text NOT NULL,
  `package_dimensions` text COMMENT 'Габариты (JSON)',
  `package_weight` decimal(10,3) DEFAULT NULL,
  `declared_value` decimal(15,4) DEFAULT NULL,
  `calculated_cost` decimal(15,4) DEFAULT NULL,
  `actual_cost` decimal(15,4) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'new',
  `external_request_id` varchar(100) DEFAULT NULL,
  `request_data` text COMMENT 'Данные запроса (JSON)',
  `response_data` text COMMENT 'Данные ответа (JSON)',
  `error_message` text,
  `pickup_date` datetime DEFAULT NULL,
  `delivery_date` datetime DEFAULT NULL,
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  KEY `order_id` (`order_id`),
  KEY `carrier` (`carrier`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица для верификации адресов
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_address_verification` (
  `verification_id` int(11) NOT NULL AUTO_INCREMENT,
  `original_address` text NOT NULL,
  `verified_address` text,
  `confidence_score` decimal(3,2) DEFAULT NULL COMMENT 'Уверенность 0-1',
  `verification_service` varchar(50) DEFAULT NULL,
  `is_valid` tinyint(1) DEFAULT NULL,
  `verification_data` text COMMENT 'Данные верификации (JSON)',
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`verification_id`),
  KEY `is_valid` (`is_valid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица данных для аналитики
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_analytics_data` (
  `analytics_id` int(11) NOT NULL AUTO_INCREMENT,
  `data_type` varchar(50) NOT NULL COMMENT 'sales, profit, roi, customer',
  `period_type` varchar(20) NOT NULL COMMENT 'daily, weekly, monthly',
  `period_date` date NOT NULL,
  `marketplace` varchar(50) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `metric_name` varchar(100) NOT NULL,
  `metric_value` decimal(15,4) NOT NULL,
  `additional_data` text COMMENT 'Дополнительные данные (JSON)',
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`analytics_id`),
  UNIQUE KEY `data_unique` (`data_type`, `period_type`, `period_date`, `marketplace`, `product_id`, `metric_name`),
  KEY `period_date` (`period_date`),
  KEY `data_type` (`data_type`),
  KEY `marketplace` (`marketplace`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица RFM-анализа клиентов
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_customer_rfm` (
  `rfm_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `recency` int(11) NOT NULL COMMENT 'Дней с последнего заказа',
  `frequency` int(11) NOT NULL COMMENT 'Количество заказов',
  `monetary` decimal(15,4) NOT NULL COMMENT 'Общая сумма заказов',
  `rfm_score` varchar(10) NOT NULL COMMENT 'RFM-сегмент (например, 555)',
  `rfm_segment` varchar(50) NOT NULL COMMENT 'Название сегмента',
  `last_order_date` date DEFAULT NULL,
  `analysis_date` date NOT NULL,
  PRIMARY KEY (`rfm_id`),
  UNIQUE KEY `customer_analysis` (`customer_id`, `analysis_date`),
  KEY `rfm_score` (`rfm_score`),
  KEY `analysis_date` (`analysis_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица ROI по каналам продаж
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_roi_analysis` (
  `roi_id` int(11) NOT NULL AUTO_INCREMENT,
  `marketplace` varchar(50) NOT NULL,
  `period_date` date NOT NULL,
  `revenue` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `costs` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `profit` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `investment` decimal(15,4) NOT NULL DEFAULT '0.0000' COMMENT 'Инвестиции в канал',
  `roi_percent` decimal(8,2) NOT NULL DEFAULT '0.00',
  `orders_count` int(11) NOT NULL DEFAULT '0',
  `average_order_value` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`roi_id`),
  UNIQUE KEY `marketplace_period` (`marketplace`, `period_date`),
  KEY `period_date` (`period_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица предсказаний AI моделей
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_ai_prediction` (
  `prediction_id` int(11) NOT NULL AUTO_INCREMENT,
  `model_type` varchar(50) NOT NULL COMMENT 'demand, price, logistics',
  `product_id` int(11) DEFAULT NULL,
  `marketplace` varchar(50) DEFAULT NULL,
  `prediction_date` date NOT NULL,
  `prediction_data` text NOT NULL COMMENT 'Данные предсказания (JSON)',
  `confidence` decimal(5,4) DEFAULT NULL COMMENT 'Уверенность модели 0-1',
  `actual_result` text COMMENT 'Фактические результаты (JSON)',
  `accuracy` decimal(5,4) DEFAULT NULL COMMENT 'Точность предсказания',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`prediction_id`),
  UNIQUE KEY `model_prediction` (`model_type`, `product_id`, `marketplace`, `prediction_date`),
  KEY `model_type` (`model_type`),
  KEY `prediction_date` (`prediction_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица логов безопасности
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_security_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(50) NOT NULL COMMENT 'fraud, api_error, system',
  `severity` varchar(20) NOT NULL COMMENT 'low, medium, high, critical',
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text,
  `request_data` text COMMENT 'Данные запроса (JSON)',
  `description` text NOT NULL,
  `suspected_fraud` tinyint(1) NOT NULL DEFAULT '0',
  `action_taken` varchar(100) DEFAULT NULL COMMENT 'blocked, warned, notified',
  `user_id` int(11) DEFAULT NULL,
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `event_type` (`event_type`),
  KEY `severity` (`severity`),
  KEY `suspected_fraud` (`suspected_fraud`),
  KEY `date_added` (`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица сессий мобильного приложения
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_mobile_session` (
  `session_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `device_id` varchar(255) NOT NULL,
  `device_type` varchar(50) NOT NULL COMMENT 'ios, android, web',
  `push_token` varchar(255) DEFAULT NULL,
  `last_activity` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`session_id`),
  UNIQUE KEY `device_user` (`device_id`, `user_id`),
  KEY `user_id` (`user_id`),
  KEY `last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица виджетов дашборда
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_dashboard_widget` (
  `widget_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `widget_type` varchar(50) NOT NULL,
  `widget_config` text NOT NULL COMMENT 'Конфигурация виджета (JSON)',
  `column_position` int(11) NOT NULL DEFAULT '0',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`widget_id`),
  KEY `user_id` (`user_id`),
  KEY `widget_type` (`widget_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица для circuit breaker
CREATE TABLE IF NOT EXISTS `{{DB_PREFIX}}shtab_circuit_breaker` (
  `breaker_id` int(11) NOT NULL AUTO_INCREMENT,
  `service_name` varchar(100) NOT NULL,
  `failure_count` int(11) NOT NULL DEFAULT '0',
  `last_failure` datetime DEFAULT NULL,
  `state` varchar(20) NOT NULL DEFAULT 'closed' COMMENT 'closed, open, half_open',
  `last_state_change` datetime NOT NULL,
  `next_retry` datetime DEFAULT NULL,
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`breaker_id`),
  UNIQUE KEY `service_name` (`service_name`),
  KEY `state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;