<?php
/**
 * Серверный прокси DaData.
 *
 * Зачем прокси: токен и секрет DaData не должны попадать в JavaScript.
 * Браузер обращается к api/dadata.php, а уже сервер — к DaData.
 *
 * GET-параметры:
 *   action=suggest&type=address|fio|email|phone&query=...
 *   action=clean&type=phone|email|name|address&value=...
 *   action=status
 */
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cache_dir(): string
{
    $dir = __DIR__ . '/../cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function cache_get(string $key): ?array
{
    $file = cache_dir() . DIRECTORY_SEPARATOR . md5($key) . '.json';
    if (!is_file($file) || filemtime($file) < time() - 86400) {
        return null;
    }
    $data = json_decode((string)@file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

function cache_set(string $key, array $data): void
{
    $file = cache_dir() . DIRECTORY_SEPARATOR . md5($key) . '.json';
    @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function http_post_json(string $url, $payload, bool $withSecret = false): array
{
    $token = $withSecret
        ? (DADATA_CLEAN_TOKEN !== '' ? DADATA_CLEAN_TOKEN : DADATA_TOKEN)
        : DADATA_TOKEN;
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Token ' . $token,
    ];
    if ($withSecret) {
        $headers[] = 'X-Secret: ' . DADATA_SECRET;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 8,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    return [$code, is_string($body) ? $body : '', $err];
}

/* ============================ Локальный справочник ============================ */
/* Используется только если тариф DaData не включает suggestions (403)
   или сервис временно недоступен. Так форма остаётся рабочей. */

function local_addresses(string $query): array
{
    $all = [
        'г Москва, ул. Тверская, д. 15',
        'г Москва, ул. Арбат, д. 12',
        'г Москва, Ленинский пр-кт, д. 30',
        'г Москва, Кутузовский пр-кт, д. 24',
        'г Москва, ул. Профсоюзная, д. 56',
        'г Москва, ул. Новый Арбат, д. 8',
        'г Москва, ул. Пятницкая, д. 25',
        'г Москва, ул. Мясницкая, д. 20',
        'г Москва, ул. Большая Дмитровка, д. 7',
        'г Москва, ул. Земляной Вал, д. 27',
        'г Москва, ул. Ленина, д. 10',
        'г Москва, ул. Садовая-Кудринская, д. 5',
        'г Санкт-Петербург, Невский пр-кт, д. 28',
        'г Санкт-Петербург, ул. Рубинштейна, д. 18',
        'г Казань, ул. Баумана, д. 44',
        'г Екатеринбург, ул. Ленина, д. 24',
    ];
    $q = mb_strtolower(trim($query));
    $result = [];
    foreach ($all as $value) {
        if ($q === '' || mb_stripos(mb_strtolower($value), $q) !== false) {
            $result[] = ['value' => $value, 'unrestricted_value' => $value, 'data' => []];
        }
    }
    return array_slice($result, 0, 8);
}

function local_fio(string $query): array
{
    $all = [
        'Алексей Смирнов', 'Екатерина Волкова', 'Дмитрий Орлов', 'Ольга Соколова',
        'Анна Иванова', 'Мария Петрова', 'Иван Кузнецов', 'Сергей Попов',
        'Наталья Морозова', 'Михаил Новиков', 'Татьяна Фёдорова', 'Андрей Белов',
    ];
    $q = mb_strtolower(trim($query));
    $result = [];
    foreach ($all as $value) {
        if (mb_stripos(mb_strtolower($value), $q) !== false) {
            $result[] = ['value' => $value, 'unrestricted_value' => $value, 'data' => []];
        }
    }
    return array_slice($result, 0, 6);
}

function local_email(string $query): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }
    $domains = ['mail.ru', 'gmail.com', 'yandex.ru', 'inbox.ru', 'bk.ru'];
    $result = [];
    if (strpos($query, '@') !== false) {
        [$local, $domain] = explode('@', $query, 2);
        foreach ($domains as $d) {
            if ($domain === '' || mb_stripos($d, $domain) === 0) {
                $result[] = ['value' => $local . '@' . $d, 'unrestricted_value' => $local . '@' . $d, 'data' => []];
            }
        }
    } else {
        foreach ($domains as $d) {
            $result[] = ['value' => $query . '@' . $d, 'unrestricted_value' => $query . '@' . $d, 'data' => []];
        }
    }
    return array_slice($result, 0, 5);
}

/* ============================ Действия ============================ */

$action = $_GET['action'] ?? 'status';

if ($action === 'status') {
    [$code] = http_post_json(DADATA_SUGGEST_URL . 'fio', ['query' => 'Иван', 'count' => 1]);
    $suggestionsAvailable = $code === 200;

    [$cleanCode] = http_post_json(DADATA_CLEAN_URL . 'phone', ['+7 916 123-45-67'], true);
    $cleanerAvailable = $cleanCode === 200;

    if ($suggestionsAvailable && $cleanerAvailable) {
        $message = 'DaData: подсказки и нормализация доступны.';
    } elseif ($suggestionsAvailable) {
        $message = 'DaData: подсказки доступны, нормализация недоступна — используется локальная обработка.';
    } elseif ($cleanerAvailable) {
        $message = 'DaData: нормализация доступна, подсказки недоступны — включён локальный справочник.';
    } else {
        $message = 'DaData недоступна — включён локальный режим подсказок и нормализации.';
    }

    json_out([
        'ok'                    => true,
        'suggestions_available' => $suggestionsAvailable,
        'cleaner'               => $cleanerAvailable,
        'message'               => $message,
    ]);
}

if ($action === 'suggest') {
    $type  = $_GET['type'] ?? 'address';
    $type  = in_array($type, ['address', 'fio', 'email', 'phone', 'party'], true) ? $type : 'address';
    $query = trim((string)($_GET['query'] ?? ''));
    $count = max(1, min(10, (int)($_GET['count'] ?? 8)));

    if (mb_strlen($query) < 2) {
        json_out(['ok' => true, 'suggestions' => [], 'fallback' => false]);
    }

    $key = 'suggest|' . $type . '|' . mb_strtolower($query) . '|' . $count;
    $cached = cache_get($key);
    if ($cached !== null) {
        json_out($cached);
    }

    $payload = ['query' => $query, 'count' => $count];
    [$code, $body, $err] = http_post_json(DADATA_SUGGEST_URL . $type, $payload);

    if ($code === 200) {
        $data = json_decode($body, true);
        $suggestions = $data['suggestions'] ?? [];
        $out = ['ok' => true, 'suggestions' => $suggestions, 'fallback' => false];
        cache_set($key, $out);
        json_out($out);
    }

    // 403 — тариф без suggestions. Отдаём локальные подсказки, чтобы UX не ломался.
    $fallback = [];
    if ($type === 'address') {
        $fallback = local_addresses($query);
    } elseif ($type === 'fio') {
        $fallback = local_fio($query);
    } elseif ($type === 'email') {
        $fallback = local_email($query);
    }

    $out = [
        'ok'          => true,
        'suggestions' => $fallback,
        'fallback'    => true,
        'http_code'   => $code,
        'notice'      => $code === 403
            ? 'Тариф DaData не включает SUGGESTIONS — показан локальный справочник.'
            : 'DaData недоступна — показан локальный справочник.',
    ];
    json_out($out);
}

if ($action === 'clean') {
    $type  = $_GET['type'] ?? '';
    $type  = in_array($type, ['phone', 'email', 'name', 'address'], true) ? $type : '';
    $value = trim((string)($_GET['value'] ?? ''));
    if ($type === '' || $value === '') {
        json_out(['ok' => false, 'error' => 'Не переданы type и value'], 400);
    }

    $key = 'clean|' . $type . '|' . mb_strtolower($value);
    $cached = cache_get($key);
    if ($cached !== null) {
        json_out($cached);
    }

    $payload = [$value];
    [$code, $body, $err] = http_post_json(DADATA_CLEAN_URL . $type, $payload, true);

    if ($code === 200) {
        $data = json_decode($body, true);
        $item = is_array($data) ? ($data[0] ?? null) : null;
        $canonical = $value;
        if (is_array($item)) {
            if ($type === 'phone') {
                $canonical = $item['phone'] ?? $value;
            } elseif ($type === 'email') {
                $canonical = $item['email'] ?? $value;
            } else {
                $canonical = $item['result'] ?? $item['source'] ?? $value;
            }
        }
        $out = ['ok' => true, 'value' => $canonical, 'raw' => $item, 'fallback' => false];
        cache_set($key, $out);
        json_out($out);
    }

    // Если cleaner недоступен, не мешаем пользователю: возвращаем исходное значение.
    $local = $value;
    if ($type === 'phone') {
        $digits = preg_replace('/\D+/', '', $value);
        if (strlen($digits) === 11) {
            $local = '+7 (' . substr($digits, 1, 3) . ') ' . substr($digits, 4, 3) . '-' . substr($digits, 7, 2) . '-' . substr($digits, 9, 2);
        }
    } elseif ($type === 'email') {
        $local = mb_strtolower($value);
    } elseif ($type === 'name') {
        $local = mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    json_out([
        'ok'       => false,
        'value'    => $local,
        'fallback' => true,
        'http_code'=> $code,
        'error'    => $code === 401 ? 'Неверный токен/секрет DaData' : ($err ?: 'DaData cleaner недоступен'),
    ]);
}

json_out(['ok' => false, 'error' => 'Неизвестное действие'], 400);
