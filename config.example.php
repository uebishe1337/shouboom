<?php
/**
 * Шаблон конфигурации проекта «ШоуБум».
 *
 * Скопируйте этот файл в config.php и укажите свои значения:
 *   copy config.example.php config.php
 *
 * Значения можно переопределить переменными окружения (удобно для хостинга).
 */

define('DB_HOST', getenv('SHOUBOOM_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('SHOUBOOM_DB_NAME') ?: 'Услуги аниматоров');
define('DB_USER', getenv('SHOUBOOM_DB_USER') ?: 'root');
define('DB_PASS', getenv('SHOUBOOM_DB_PASS') ?: '');

/**
 * DaData. Токен и секрет используются ТОЛЬКО на сервере (api/dadata.php),
 * чтобы не раскрывать их в исходном коде страниц.
 *
 * Получите токены в личном кабинете https://dadata.ru/profile/#info
 * и впишите их сюда либо задайте переменные окружения.
 */
define('DADATA_TOKEN', getenv('DADATA_TOKEN') ?: '');
/**
 * Второй токен — для cleaner (нормализации). У токенов DaData наборы функций
 * могут отличаться: у одного включены SUGGESTIONS, у другого CLEAN.
 * Если оставить пустым, для cleaner будет использован DADATA_TOKEN.
 */
define('DADATA_CLEAN_TOKEN', getenv('DADATA_CLEAN_TOKEN') ?: '');
define('DADATA_SECRET', getenv('DADATA_SECRET') ?: '');
define('DADATA_SUGGEST_URL', 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/');
define('DADATA_CLEAN_URL', 'https://cleaner.dadata.ru/api/v1/clean/');

define('SITE_NAME', 'ШоуБум');
define('SITE_TAGLINE', 'Аниматоры, шоу и квесты для детей 3–14 лет');
define('SITE_PHONE', '+7 (495) 123-45-67');
define('SITE_EMAIL', 'info@shouboom.ru');
define('SITE_ADDRESS', 'г. Москва, ул. Тверская, д. 15');

define('UPLOAD_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'uploads');
define('UPLOAD_URL', 'uploads');
