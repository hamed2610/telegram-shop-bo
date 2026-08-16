<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Telegram Store Bot - Easy Run Full
| Single File / PHP / Webhook / JSON Storage
|--------------------------------------------------------------------------
| فقط این دو مقدار را پر کن:
| 1) BOT_TOKEN
| 2) OWNER_ID
|--------------------------------------------------------------------------
| ویژگی‌ها:
| - فقط وبهوک لازم دارد
| - بدون دیتابیس SQL
| - ذخیره‌سازی با JSON
| - رفرال + نوتیف دعوت‌کننده
| - حساب کاربری کامل
| - دسته‌بندی: افزودن / ویرایش / حذف / فعال-غیرفعال / ترتیب
| - محصول: افزودن / ویرایش / حذف / فعال-غیرفعال / ویژه / تغییر تحویل
| - مخزن هر محصول
| - تحویل خودکار از مخزن
| - درخواست محصول + تأیید / رد
| - قرعه‌کشی: افزودن / ویرایش / حذف / فعال-غیرفعال / انتخاب برنده
| - چالش: تاس / دارت / اسلات
| - تنظیمات: متن‌ها، کانال گزارش، عضویت اجباری چندکاناله
| - ادمین‌ها: افزودن / حذف / لیست
| - پیام همگانی
|--------------------------------------------------------------------------
*/

const BOT_TOKEN = '8687848452:AAGgXw8oZ9nHoq9BWBHuIEsU4WatffrswYM';
const OWNER_ID  = 1075714073;

const DATA_DIR = __DIR__ . '/data_heasgoasy_bot';
const LOG_FILE = DATA_DIR . '/d1eb1ug.log';
const BOT_USERNAME_FALLBACK = 'YourBotUsername';
const MAX_TEXT_LEN = 3900;

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('UTC');

/* =========================================================
   BASIC
   ========================================================= */
function now(): string { return gmdate('Y-m-d H:i:s'); }
function esc(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function safeText(string $v): string { return mb_substr($v, 0, MAX_TEXT_LEN); }
function jenc($v): string { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
function jdec(?string $v, $default = []) { $x = json_decode((string)$v, true); return is_array($x) ? $x : $default; }

function boot_storage(): void
{
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0777, true);
    }

    $defaults = [
        'users.json' => [],
        'admins.json' => [OWNER_ID],
        'categories.json' => [],
        'products.json' => [],
        'inventory.json' => [],
        'requests.json' => [],
        'lotteries.json' => [],
        'lottery_entries.json' => [],
        'challenge_plays.json' => [],
        'states.json' => [],
        'admin_logs.json' => [],
        'counters.json' => [
            'categories' => 0,
            'products' => 0,
            'inventory' => 0,
            'requests' => 0,
            'lotteries' => 0,
            'lottery_entries' => 0,
            'challenge_plays' => 0,
            'admin_logs' => 0,
        ],
        'settings.json' => [
            'bot_username' => BOT_USERNAME_FALLBACK,
            'bot_enabled' => '1',
            'challenge_enabled' => '1',
            'lottery_enabled' => '1',
            'welcome_text' => "سلام 🌹\nبه ربات خوش اومدی.\nاز منوی زیر استفاده کن.",
            'rules_text' => "1) تقلب ممنوع\n2) تصمیم نهایی با مدیریت است",
            'support_text' => "برای پشتیبانی با مدیریت در ارتباط باش.",
            'force_join_text' => "⛔️ برای استفاده از ربات باید اول در کانال‌های زیر عضو شوی.\n\nبعد از عضویت روی دکمه بررسی عضویت بزن.",
            'report_channel' => '',
            'force_channels' => [],
        ],
    ];

    foreach ($defaults as $file => $data) {
        $path = DATA_DIR . '/' . $file;
        if (!file_exists($path)) {
            @file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }
    }
}

function log_debug(string $text): void
{
    @file_put_contents(LOG_FILE, '[' . now() . '] ' . $text . PHP_EOL, FILE_APPEND);
}

function load_json(string $file, $default = [])
{
    $path = DATA_DIR . '/' . $file;
    if (!file_exists($path)) return $default;
    $raw = @file_get_contents($path);
    if ($raw === false) return $default;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

function save_json(string $file, $data): void
{
    $path = DATA_DIR . '/' . $file;
    @file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

function next_id(string $key): int
{
    $counters = load_json('counters.json', []);
    $counters[$key] = (int)($counters[$key] ?? 0) + 1;
    save_json('counters.json', $counters);
    return (int)$counters[$key];
}

/* =========================================================
   TELEGRAM
   ========================================================= */
function tg(string $method, array $params = []): array
{
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        log_debug('curl error: ' . $err);
        return ['ok' => false, 'description' => $err];
    }

    $decoded = json_decode((string)$res, true);
    if (!is_array($decoded)) {
        log_debug('invalid telegram response: ' . (string)$res);
        return ['ok' => false, 'description' => 'invalid response'];
    }
    return $decoded;
}

function sendMessage(int|string $chatId, string $text, ?array $kb = null): array
{
    $params = [
        'chat_id' => $chatId,
        'text' => safeText($text),
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];
    if ($kb !== null) $params['reply_markup'] = jenc($kb);
    return tg('sendMessage', $params);
}

function editMessageText(int|string $chatId, int $messageId, string $text, ?array $kb = null): array
{
    $params = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => safeText($text),
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];
    if ($kb !== null) $params['reply_markup'] = jenc($kb);
    return tg('editMessageText', $params);
}

function answerCallback(string $id, string $text = '', bool $alert = false): array
{
    return tg('answerCallbackQuery', [
        'callback_query_id' => $id,
        'text' => $text,
        'show_alert' => $alert ? 'true' : 'false',
    ]);
}

function sendDice(int|string $chatId, string $emoji): array
{
    return tg('sendDice', ['chat_id' => $chatId, 'emoji' => $emoji]);
}

function getChatMember(string|int $chatId, int $userId): array
{
    return tg('getChatMember', ['chat_id' => $chatId, 'user_id' => $userId]);
}

/* =========================================================
   SETTINGS / ADMINS / STATES
   ========================================================= */
function setting(string $key, ?string $default = null): ?string
{
    $settings = load_json('settings.json', []);
    return array_key_exists($key, $settings) ? (string)$settings[$key] : $default;
}

function setSetting(string $key, $value): void
{
    $settings = load_json('settings.json', []);
    $settings[$key] = $value;
    save_json('settings.json', $settings);
}

function getAdmins(): array
{
    $admins = load_json('admins.json', [OWNER_ID]);
    $admins = array_values(array_unique(array_map('intval', $admins)));
    if (!in_array(OWNER_ID, $admins, true)) $admins[] = OWNER_ID;
    sort($admins);
    return $admins;
}

function saveAdmins(array $admins): void
{
    $admins = array_values(array_unique(array_map('intval', $admins)));
    if (!in_array(OWNER_ID, $admins, true)) $admins[] = OWNER_ID;
    sort($admins);
    save_json('admins.json', $admins);
}

function isAdminUser(int $id): bool
{
    return in_array($id, getAdmins(), true);
}

function botUsername(): string
{
    return ltrim((string)(setting('bot_username', BOT_USERNAME_FALLBACK) ?: BOT_USERNAME_FALLBACK), '@');
}

function reportChannel(): string
{
    return trim((string)setting('report_channel', ''));
}

function reportText(string $text, ?array $kb = null): void
{
    $ch = reportChannel();
    if ($ch !== '') sendMessage($ch, $text, $kb);
}

function getForceChannels(): array
{
    $settings = load_json('settings.json', []);
    $channels = $settings['force_channels'] ?? [];
    return array_values(array_filter(array_map('trim', (array)$channels), fn($x) => $x !== ''));
}

function saveForceChannels(array $channels): void
{
    $channels = array_values(array_filter(array_map('trim', $channels), fn($x) => $x !== ''));
    setSetting('force_channels', $channels);
}

function setState(int $userId, string $state, array $data = []): void
{
    $states = load_json('states.json', []);
    $states[(string)$userId] = [
        'state_name' => $state,
        'state_data' => $data,
        'updated_at' => now(),
    ];
    save_json('states.json', $states);
}

function getState(int $userId): ?array
{
    $states = load_json('states.json', []);
    return $states[(string)$userId] ?? null;
}

function clearState(int $userId): void
{
    $states = load_json('states.json', []);
    unset($states[(string)$userId]);
    save_json('states.json', $states);
}

function logAdmin(int $adminId, string $action, array $details = []): void
{
    $logs = load_json('admin_logs.json', []);
    $logs[] = [
        'id' => next_id('admin_logs'),
        'admin_id' => $adminId,
        'action_name' => $action,
        'details' => $details,
        'created_at' => now(),
    ];
    save_json('admin_logs.json', $logs);
}

/* =========================================================
   USERS
   ========================================================= */
function getUsers(): array
{
    return load_json('users.json', []);
}

function saveUsers(array $users): void
{
    save_json('users.json', array_values($users));
}

function getUser(int $id): ?array
{
    foreach (getUsers() as $u) {
        if ((int)$u['id'] === $id) return $u;
    }
    return null;
}

function saveUserRow(array $row): void
{
    $users = getUsers();
    $found = false;
    foreach ($users as $k => $u) {
        if ((int)$u['id'] === (int)$row['id']) {
            $users[$k] = $row;
            $found = true;
            break;
        }
    }
    if (!$found) $users[] = $row;
    saveUsers($users);
}

function availableReferrals(array $user): int
{
    return max(0, (int)$user['referrals_count'] - (int)$user['referrals_spent']);
}

function ensureUser(array $from, ?int $invitedBy = null): array
{
    $id = (int)$from['id'];
    $username = isset($from['username']) ? (string)$from['username'] : '';
    $fullName = trim((string)($from['first_name'] ?? '') . ' ' . (string)($from['last_name'] ?? ''));

    $user = getUser($id);
    if ($user) {
        $user['username'] = $username;
        $user['full_name'] = $fullName;
        $user['last_active_at'] = now();
        $user['is_admin'] = isAdminUser($id) ? 1 : 0;
        saveUserRow($user);
        return $user;
    }

    $user = [
        'id' => $id,
        'username' => $username,
        'full_name' => $fullName,
        'joined_at' => now(),
        'last_active_at' => now(),
        'is_blocked' => 0,
        'is_admin' => isAdminUser($id) ? 1 : 0,
        'referrals_count' => 0,
        'referrals_spent' => 0,
        'points' => 0,
        'warnings' => 0,
        'level_name' => 'Bronze',
        'invited_by' => ($invitedBy && $invitedBy !== $id) ? $invitedBy : null,
        'note' => '',
    ];
    saveUserRow($user);

    if ($invitedBy && $invitedBy !== $id) {
        $inviter = getUser($invitedBy);
        if ($inviter) {
            $inviter['referrals_count'] = (int)$inviter['referrals_count'] + 1;
            $inviter['points'] = (int)$inviter['points'] + 1;
            saveUserRow($inviter);

            sendMessage(
                $invitedBy,
                "🎉 <b>تبریک!</b>\n\nیک نفر با لینک شما وارد شد.\n\n" .
                "👤 نام: " . esc($fullName) . "\n" .
                "🆔 آیدی: <code>{$id}</code>\n" .
                "🔖 یوزرنیم: @" . esc($username ?: 'ندارد') . "\n\n" .
                "✅ یک رفرال به شما اضافه شد."
            );
        }
    }

    return $user;
}

function accountText(array $user): string
{
    $link = 'https://t.me/' . botUsername() . '?start=ref_' . $user['id'];
    return
        "👤 <b>حساب کاربری</b>\n\n" .
        "🆔 <b>آیدی عددی:</b> <code>{$user['id']}</code>\n" .
        "🙍 <b>نام:</b> " . esc($user['full_name']) . "\n" .
        "🔖 <b>یوزرنیم:</b> @" . esc($user['username'] ?: 'ندارد') . "\n" .
        "📅 <b>عضویت:</b> {$user['joined_at']}\n" .
        "🕒 <b>آخرین فعالیت:</b> {$user['last_active_at']}\n" .
        "🛡 <b>ادمین:</b> " . ((int)$user['is_admin'] ? 'بله' : 'خیر') . "\n" .
        "🏅 <b>سطح:</b> " . esc($user['level_name']) . "\n" .
        "⭐ <b>امتیاز:</b> {$user['points']}\n" .
        "⚠️ <b>اخطار:</b> {$user['warnings']}\n" .
        "🚫 <b>وضعیت:</b> " . ((int)$user['is_blocked'] ? 'مسدود' : 'فعال') . "\n\n" .
        "👥 <b>کل زیرمجموعه‌ها:</b> {$user['referrals_count']}\n" .
        "♻️ <b>رفرال مصرف‌شده:</b> {$user['referrals_spent']}\n" .
        "✅ <b>رفرال قابل استفاده:</b> " . availableReferrals($user) . "\n\n" .
        "🔗 <b>لینک دعوت:</b>\n<code>{$link}</code>";
}

/* =========================================================
   FORCE JOIN
   ========================================================= */
function getForceJoinMissing(int $userId): array
{
    $channels = getForceChannels();
    if (!$channels) return [];

    $missing = [];
    foreach ($channels as $channel) {
        $res = getChatMember($channel, $userId);
        if (!($res['ok'] ?? false)) {
            $missing[] = $channel;
            continue;
        }
        $status = $res['result']['status'] ?? '';
        if (!in_array($status, ['member', 'administrator', 'creator'], true)) {
            $missing[] = $channel;
        }
    }
    return $missing;
}

function forceJoinPassed(int $userId): bool
{
    return count(getForceJoinMissing($userId)) === 0;
}

function forceJoinKeyboard(array $missing): array
{
    $rows = [];
    foreach ($missing as $channel) {
        $url = str_starts_with($channel, '@') ? ('https://t.me/' . ltrim($channel, '@')) : null;
        if ($url) {
            $rows[] = [['text' => '📢 ' . $channel, 'url' => $url]];
        }
    }
    $rows[] = [['text' => '✅ بررسی عضویت', 'callback_data' => 'check_force_join']];
    $rows[] = [['text' => '🔙 منوی اصلی', 'callback_data' => 'main_menu']];
    return ['inline_keyboard' => $rows];
}

function forceJoinText(array $missing): string
{
    $base = (string)setting('force_join_text', "⛔️ برای استفاده از ربات باید اول عضو شوی.");
    $txt = $base . "\n\n";
    foreach ($missing as $ch) {
        $txt .= "• <code>" . esc($ch) . "</code>\n";
    }
    return trim($txt);
}

/* =========================================================
   CATEGORIES
   ========================================================= */
function getCategories(): array { return load_json('categories.json', []); }
function saveCategories(array $rows): void { save_json('categories.json', array_values($rows)); }

function getCategory(int $id): ?array
{
    foreach (getCategories() as $r) {
        if ((int)$r['id'] === $id) return $r;
    }
    return null;
}

function saveCategoryRow(array $row): void
{
    $rows = getCategories();
    $found = false;
    foreach ($rows as $k => $r) {
        if ((int)$r['id'] === (int)$row['id']) {
            $rows[$k] = $row;
            $found = true;
            break;
        }
    }
    if (!$found) $rows[] = $row;
    saveCategories($rows);
}

function deleteCategoryById(int $id): void
{
    $rows = array_values(array_filter(getCategories(), fn($x) => (int)$x['id'] !== $id));
    saveCategories($rows);
}

/* =========================================================
   PRODUCTS
   ========================================================= */
function getProducts(): array { return load_json('products.json', []); }
function saveProducts(array $rows): void { save_json('products.json', array_values($rows)); }

function getProduct(int $id): ?array
{
    foreach (getProducts() as $r) {
        if ((int)$r['id'] === $id) return $r;
    }
    return null;
}

function saveProductRow(array $row): void
{
    $rows = getProducts();
    $found = false;
    foreach ($rows as $k => $r) {
        if ((int)$r['id'] === (int)$row['id']) {
            $rows[$k] = $row;
            $found = true;
            break;
        }
    }
    if (!$found) $rows[] = $row;
    saveProducts($rows);
}

function deleteProductById(int $id): void
{
    saveProducts(array_values(array_filter(getProducts(), fn($x) => (int)$x['id'] !== $id)));
    save_json('inventory.json', array_values(array_filter(load_json('inventory.json', []), fn($x) => (int)$x['product_id'] !== $id)));
    save_json('requests.json', array_values(array_filter(load_json('requests.json', []), fn($x) => (int)$x['product_id'] !== $id)));
}

/* =========================================================
   INVENTORY
   ========================================================= */
function getInventory(): array { return load_json('inventory.json', []); }
function saveInventory(array $rows): void { save_json('inventory.json', array_values($rows)); }

function productStock(int $productId): int
{
    $c = 0;
    foreach (getInventory() as $i) {
        if ((int)$i['product_id'] === $productId && (int)$i['is_used'] === 0) $c++;
    }
    return $c;
}

function consumeProductItem(int $productId, int $userId): ?string
{
    $items = getInventory();
    foreach ($items as $k => $item) {
        if ((int)$item['product_id'] === $productId && (int)$item['is_used'] === 0) {
            $items[$k]['is_used'] = 1;
            $items[$k]['used_by'] = $userId;
            $items[$k]['used_at'] = now();
            saveInventory($items);
            return (string)$item['item_text'];
        }
    }
    return null;
}

/* =========================================================
   REQUESTS
   ========================================================= */
function getRequests(): array { return load_json('requests.json', []); }
function saveRequests(array $rows): void { save_json('requests.json', array_values($rows)); }

function getRequestById(int $id): ?array
{
    foreach (getRequests() as $r) {
        if ((int)$r['id'] === $id) return $r;
    }
    return null;
}

function saveRequestRow(array $row): void
{
    $rows = getRequests();
    $found = false;
    foreach ($rows as $k => $r) {
        if ((int)$r['id'] === (int)$row['id']) {
            $rows[$k] = $row;
            $found = true;
            break;
        }
    }
    if (!$found) $rows[] = $row;
    saveRequests($rows);
}

function createProductRequest(int $userId, int $productId): array
{
    $user = getUser($userId);
    $product = getProduct($productId);

    if (!$user) return ['ok' => false, 'message' => 'کاربر پیدا نشد.'];
    if (!$product) return ['ok' => false, 'message' => 'محصول پیدا نشد.'];
    if ((int)$product['is_active'] !== 1) return ['ok' => false, 'message' => 'محصول غیرفعال است.'];
    if ((int)$user['is_blocked'] === 1) return ['ok' => false, 'message' => 'حساب شما مسدود است.'];
    if ($product['delivery_mode'] === 'auto' && productStock($productId) <= 0) return ['ok' => false, 'message' => 'موجودی این محصول تمام شده.'];
    if (availableReferrals($user) < (int)$product['referral_cost']) return ['ok' => false, 'message' => 'رفرال کافی نداری.'];

    foreach (getRequests() as $r) {
        if ((int)$r['user_id'] === $userId && (int)$r['product_id'] === $productId) {
            return ['ok' => false, 'message' => 'قبلاً برای این محصول درخواست داده‌ای.'];
        }
    }

    $request = [
        'id' => next_id('requests'),
        'user_id' => $userId,
        'product_id' => $productId,
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
        'note' => '',
        'admin_note' => '',
        'delivered_content' => '',
        'delivered_at' => '',
    ];
    saveRequestRow($request);

    $user['referrals_spent'] = (int)$user['referrals_spent'] + (int)$product['referral_cost'];
    saveUserRow($user);

    if ($product['delivery_mode'] === 'auto') {
        $item = consumeProductItem($productId, $userId);
        if ($item === null) {
            $user['referrals_spent'] = max(0, (int)$user['referrals_spent'] - (int)$product['referral_cost']);
            saveUserRow($user);
            return ['ok' => false, 'message' => 'موجودی خالی است.'];
        }

        $request['status'] = 'delivered';
        $request['updated_at'] = now();
        $request['delivered_content'] = $item;
        $request['delivered_at'] = now();
        saveRequestRow($request);

        sendMessage(
            $userId,
            "🎁 <b>محصول شما تحویل شد</b>\n\n" .
            "📦 محصول: " . esc($product['title']) . "\n" .
            "🆔 درخواست: <code>{$request['id']}</code>\n\n" .
            "🔓 <b>محتوا / کد / اطلاعات:</b>\n<code>" . esc($item) . "</code>"
        );

        reportText(
            "✅ <b>تحویل خودکار محصول</b>\n\n" .
            "🆔 درخواست: <code>{$request['id']}</code>\n" .
            "👤 کاربر: " . esc($user['full_name']) . "\n" .
            "🆔 آیدی: <code>{$user['id']}</code>\n" .
            "🎁 محصول: " . esc($product['title'])
        );

        return ['ok' => true, 'message' => 'محصول خودکار تحویل شد.'];
    }

    $cat = getCategory((int)$product['category_id']);
    $kb = ['inline_keyboard' => [[
        ['text' => '✅ تأیید', 'callback_data' => 'approve_req_' . $request['id']],
        ['text' => '❌ رد', 'callback_data' => 'reject_req_' . $request['id']],
    ]]];

    reportText(
        "📨 <b>درخواست جدید محصول</b>\n\n" .
        "🆔 درخواست: <code>{$request['id']}</code>\n" .
        "👤 کاربر: " . esc($user['full_name']) . "\n" .
        "🆔 آیدی: <code>{$user['id']}</code>\n" .
        "🔖 یوزرنیم: @" . esc($user['username'] ?: 'ندارد') . "\n" .
        "🎁 محصول: " . esc($product['title']) . "\n" .
        "📂 دسته: " . esc($cat['title'] ?? 'ندارد') . "\n" .
        "👥 رفرال لازم: {$product['referral_cost']}\n" .
        "🕒 زمان: " . now(),
        $kb
    );

    return ['ok' => true, 'message' => 'درخواست ثبت و برای مدیریت ارسال شد.'];
}

function approveRequest(int $adminId, int $requestId): array
{
    $req = getRequestById($requestId);
    if (!$req) return ['ok' => false, 'message' => 'درخواست پیدا نشد.'];
    if ($req['status'] !== 'pending') return ['ok' => false, 'message' => 'قبلاً بررسی شده.'];

    $product = getProduct((int)$req['product_id']);
    if (!$product) return ['ok' => false, 'message' => 'محصول پیدا نشد.'];

    if ($product['delivery_mode'] === 'auto') {
        $item = consumeProductItem((int)$product['id'], (int)$req['user_id']);
        if ($item === null) return ['ok' => false, 'message' => 'موجودی خودکار خالی است.'];

        $req['status'] = 'delivered';
        $req['updated_at'] = now();
        $req['delivered_content'] = $item;
        $req['delivered_at'] = now();
        saveRequestRow($req);

        sendMessage((int)$req['user_id'],
            "✅ درخواست شما تأیید و محصول تحویل شد.\n\n" .
            "📦 محصول: " . esc($product['title']) . "\n" .
            "🆔 درخواست: <code>{$requestId}</code>\n\n" .
            "🔓 <b>محتوا:</b>\n<code>" . esc($item) . "</code>"
        );
    } else {
        $req['status'] = 'approved';
        $req['updated_at'] = now();
        saveRequestRow($req);
        sendMessage((int)$req['user_id'], "✅ درخواست محصول «" . esc($product['title']) . "» تأیید شد.");
    }

    logAdmin($adminId, 'approve_request', ['request_id' => $requestId]);
    return ['ok' => true, 'message' => 'درخواست تأیید شد.'];
}

function rejectRequest(int $adminId, int $requestId): array
{
    $req = getRequestById($requestId);
    if (!$req) return ['ok' => false, 'message' => 'درخواست پیدا نشد.'];
    if ($req['status'] !== 'pending') return ['ok' => false, 'message' => 'قبلاً بررسی شده.'];

    $product = getProduct((int)$req['product_id']);
    $user = getUser((int)$req['user_id']);
    if (!$product || !$user) return ['ok' => false, 'message' => 'اطلاعات ناقص است.'];

    $req['status'] = 'rejected';
    $req['updated_at'] = now();
    saveRequestRow($req);

    $user['referrals_spent'] = max(0, (int)$user['referrals_spent'] - (int)$product['referral_cost']);
    saveUserRow($user);

    sendMessage((int)$req['user_id'], "❌ درخواست محصول «" . esc($product['title']) . "» رد شد.");
    logAdmin($adminId, 'reject_request', ['request_id' => $requestId]);

    return ['ok' => true, 'message' => 'درخواست رد شد.'];
}

/* =========================================================
   LOTTERIES
   ========================================================= */
function getLotteries(): array { return load_json('lotteries.json', []); }
function saveLotteries(array $rows): void { save_json('lotteries.json', array_values($rows)); }

function getLottery(int $id): ?array
{
    foreach (getLotteries() as $r) {
        if ((int)$r['id'] === $id) return $r;
    }
    return null;
}

function saveLotteryRow(array $row): void
{
    $rows = getLotteries();
    $found = false;
    foreach ($rows as $k => $r) {
        if ((int)$r['id'] === (int)$row['id']) {
            $rows[$k] = $row;
            $found = true;
            break;
        }
    }
    if (!$found) $rows[] = $row;
    saveLotteries($rows);
}

function deleteLotteryById(int $id): void
{
    saveLotteries(array_values(array_filter(getLotteries(), fn($x) => (int)$x['id'] !== $id)));
    save_json('lottery_entries.json', array_values(array_filter(load_json('lottery_entries.json', []), fn($x) => (int)$x['lottery_id'] !== $id)));
}

function getLotteryEntries(): array { return load_json('lottery_entries.json', []); }
function saveLotteryEntries(array $rows): void { save_json('lottery_entries.json', array_values($rows)); }

function joinLottery(int $userId, int $lotteryId): array
{
    $user = getUser($userId);
    $lot = getLottery($lotteryId);
    if (!$user) return ['ok' => false, 'message' => 'کاربر پیدا نشد.'];
    if (!$lot) return ['ok' => false, 'message' => 'قرعه‌کشی پیدا نشد.'];
    if ((int)$lot['is_active'] !== 1) return ['ok' => false, 'message' => 'بسته است.'];
    if ($lot['ends_at'] && strtotime($lot['ends_at']) < time()) return ['ok' => false, 'message' => 'مهلت تمام شده.'];
    if ((int)$user['referrals_count'] < (int)$lot['min_referrals']) return ['ok' => false, 'message' => 'رفرال کافی نداری.'];

    $entries = getLotteryEntries();
    $count = 0;
    foreach ($entries as $e) {
        if ((int)$e['lottery_id'] === $lotteryId && (int)$e['user_id'] === $userId) $count++;
    }
    if ($count >= (int)$lot['max_entries_per_user']) return ['ok' => false, 'message' => 'سهمیه شما کامل شده.'];

    $entries[] = [
        'id' => next_id('lottery_entries'),
        'lottery_id' => $lotteryId,
        'user_id' => $userId,
        'created_at' => now(),
    ];
    saveLotteryEntries($entries);

    reportText(
        "🎉 <b>ثبت شرکت در قرعه‌کشی</b>\n" .
        "👤 " . esc($user['full_name']) . "\n" .
        "🆔 <code>{$user['id']}</code>\n" .
        "🏷 " . esc($lot['title'])
    );

    return ['ok' => true, 'message' => 'با موفقیت در قرعه‌کشی شرکت کردی.'];
}

function pickLatestLotteryWinners(int $adminId): array
{
    $lots = array_values(array_filter(getLotteries(), fn($x) => (int)$x['is_active'] === 1));
    if (!$lots) return ['ok' => false, 'message' => 'قرعه‌کشی فعال وجود ندارد.'];
    usort($lots, fn($a, $b) => (int)$b['id'] <=> (int)$a['id']);
    $lot = $lots[0];

    $ids = [];
    foreach (getLotteryEntries() as $e) {
        if ((int)$e['lottery_id'] === (int)$lot['id']) $ids[] = (int)$e['user_id'];
    }
    $ids = array_values(array_unique($ids));
    if (!$ids) return ['ok' => false, 'message' => 'شرکت‌کننده‌ای وجود ندارد.'];

    shuffle($ids);
    $winnerCount = min((int)$lot['winners_count'], count($ids));
    $winners = array_slice($ids, 0, $winnerCount);

    $lot['is_active'] = 0;
    $lot['updated_at'] = now();
    saveLotteryRow($lot);

    $text = "🏆 <b>برندگان قرعه‌کشی " . esc($lot['title']) . "</b>\n\n";
    foreach ($winners as $uid) {
        $u = getUser($uid);
        if ($u) {
            $text .= "👤 " . esc($u['full_name']) . " | <code>{$u['id']}</code> | @" . esc($u['username'] ?: 'ندارد') . "\n";
            sendMessage($uid, "🎉 تبریک! شما برنده قرعه‌کشی «" . esc($lot['title']) . "» شدی.\n🎁 جایزه: " . esc($lot['reward']));
        }
    }

    reportText($text);
    logAdmin($adminId, 'pick_lottery_winners', ['lottery_id' => (int)$lot['id'], 'winners' => $winners]);
    return ['ok' => true, 'message' => $text];
}

/* =========================================================
   CHALLENGES
   ========================================================= */
function getChallengePlays(): array { return load_json('challenge_plays.json', []); }
function saveChallengePlays(array $rows): void { save_json('challenge_plays.json', array_values($rows)); }

function registerChallenge(int $userId, string $type, int $value, bool $won, int $reward): void
{
    $plays = getChallengePlays();
    $plays[] = [
        'id' => next_id('challenge_plays'),
        'user_id' => $userId,
        'game_type' => $type,
        'result_value' => $value,
        'won' => $won ? 1 : 0,
        'reward_points' => $reward,
        'created_at' => now(),
    ];
    saveChallengePlays($plays);

    $user = getUser($userId);
    if ($user) {
        $user['points'] = (int)$user['points'] + $reward;
        saveUserRow($user);
    }
}

/* =========================================================
   KEYBOARDS
   ========================================================= */
function mainMenu(bool $admin = false): array
{
    $rows = [
        [
            ['text' => '🎁 چیزهای رایگان', 'callback_data' => 'free_items'],
            ['text' => '👤 حساب کاربری', 'callback_data' => 'account'],
        ],
        [
            ['text' => '🎯 چالش رایگان', 'callback_data' => 'challenges'],
            ['text' => '🎉 قرعه‌کشی', 'callback_data' => 'lotteries'],
        ],
        [
            ['text' => '🔗 دعوت دوستان', 'callback_data' => 'referral_link'],
            ['text' => '📘 قوانین', 'callback_data' => 'rules'],
        ],
        [['text' => '🆘 پشتیبانی', 'callback_data' => 'support']],
    ];
    if ($admin) $rows[] = [['text' => '👑 پنل مدیریت', 'callback_data' => 'admin_panel']];
    return ['inline_keyboard' => $rows];
}

function accountMenu(): array
{
    return ['inline_keyboard' => [
        [
            ['text' => '🔗 لینک دعوت', 'callback_data' => 'referral_link'],
            ['text' => '📜 درخواست‌های من', 'callback_data' => 'my_requests'],
        ],
        [
            ['text' => '🎯 چالش‌های من', 'callback_data' => 'my_challenges'],
            ['text' => '🎉 قرعه‌کشی‌های من', 'callback_data' => 'my_lotteries'],
        ],
        [['text' => '🔙 منوی اصلی', 'callback_data' => 'main_menu']],
    ]];
}

function adminMenu(): array
{
    return ['inline_keyboard' => [
        [
            ['text' => '📦 محصولات', 'callback_data' => 'admin_products'],
            ['text' => '🗂 دسته‌بندی‌ها', 'callback_data' => 'admin_categories'],
        ],
        [
            ['text' => '📨 درخواست‌ها', 'callback_data' => 'admin_requests'],
            ['text' => '👥 کاربران', 'callback_data' => 'admin_users'],
        ],
        [
            ['text' => '🛡 ادمین‌ها', 'callback_data' => 'admin_admins'],
            ['text' => '🎉 قرعه‌کشی‌ها', 'callback_data' => 'admin_lotteries'],
        ],
        [
            ['text' => '🎯 چالش‌ها', 'callback_data' => 'admin_challenges'],
            ['text' => '⚙️ تنظیمات', 'callback_data' => 'admin_settings'],
        ],
        [
            ['text' => '📊 آمار', 'callback_data' => 'admin_stats'],
            ['text' => '📢 پیام همگانی', 'callback_data' => 'admin_broadcast'],
        ],
        [
            ['text' => '🧾 لاگ‌ها', 'callback_data' => 'admin_logs'],
        ],
        [['text' => '🔙 منوی اصلی', 'callback_data' => 'main_menu']],
    ]];
}

function adminProductsMenu(): array
{
    return ['inline_keyboard' => [
        [['text' => '➕ افزودن محصول', 'callback_data' => 'admin_add_product']],
        [['text' => '📋 لیست و مدیریت محصولات', 'callback_data' => 'admin_list_products']],
        [['text' => '📦 مخزن محصولات', 'callback_data' => 'admin_inventory_hub']],
        [['text' => '🔙 پنل مدیریت', 'callback_data' => 'admin_panel']],
    ]];
}

function adminCategoriesMenu(): array
{
    return ['inline_keyboard' => [
        [['text' => '➕ افزودن دسته‌بندی', 'callback_data' => 'admin_add_category']],
        [['text' => '📋 لیست و مدیریت دسته‌ها', 'callback_data' => 'admin_list_categories']],
        [['text' => '🔙 پنل مدیریت', 'callback_data' => 'admin_panel']],
    ]];
}

function adminLotteriesMenu(): array
{
    return ['inline_keyboard' => [
        [['text' => '➕ افزودن قرعه‌کشی', 'callback_data' => 'admin_add_lottery']],
        [['text' => '📋 لیست و مدیریت قرعه‌کشی‌ها', 'callback_data' => 'admin_list_lotteries']],
        [['text' => '🏆 انتخاب برنده', 'callback_data' => 'admin_pick_winner']],
        [['text' => '🔙 پنل مدیریت', 'callback_data' => 'admin_panel']],
    ]];
}

function categoriesMenu(): array
{
    $cats = array_values(array_filter(getCategories(), fn($x) => (int)$x['is_active'] === 1));
    usort($cats, fn($a, $b) => ((int)$a['sort_order'] <=> (int)$b['sort_order']) ?: ((int)$a['id'] <=> (int)$b['id']));
    $rows = [];
    foreach ($cats as $c) {
        $rows[] = [['text' => '📂 ' . $c['title'], 'callback_data' => 'cat_' . $c['id']]];
    }
    $rows[] = [['text' => '🔙 منوی اصلی', 'callback_data' => 'main_menu']];
    return ['inline_keyboard' => $rows];
}

function productsMenu(int $categoryId): array
{
    $products = array_values(array_filter(getProducts(), fn($x) => (int)$x['category_id'] === $categoryId && (int)$x['is_active'] === 1));
    usort($products, fn($a, $b) => ((int)$b['is_featured'] <=> (int)$a['is_featured']) ?: ((int)$b['id'] <=> (int)$a['id']));
    $rows = [];
    foreach ($products as $p) {
        $rows[] = [[
            'text' => '🎁 ' . $p['title'] . ' | ' . $p['referral_cost'] . ' رفرال',
            'callback_data' => 'product_' . $p['id']
        ]];
    }
    $rows[] = [['text' => '🔙 بازگشت', 'callback_data' => 'free_items']];
    return ['inline_keyboard' => $rows];
}

function productDetailMenu(int $productId): array
{
    return ['inline_keyboard' => [
        [['text' => '✅ دریافت محصول', 'callback_data' => 'request_product_' . $productId]],
        [['text' => '🔗 لینک دعوت', 'callback_data' => 'referral_link']],
        [['text' => '🔙 بازگشت', 'callback_data' => 'free_items']],
    ]];
}

function lotteriesMenu(): array
{
    $lots = array_values(array_filter(getLotteries(), fn($x) => (int)$x['is_active'] === 1));
    usort($lots, fn($a, $b) => (int)$b['id'] <=> (int)$a['id']);
    $rows = [];
    foreach ($lots as $l) {
        $rows[] = [['text' => '🎉 ' . $l['title'], 'callback_data' => 'lottery_' . $l['id']]];
    }
    $rows[] = [['text' => '🔙 منوی اصلی', 'callback_data' => 'main_menu']];
    return ['inline_keyboard' => $rows];
}

function lotteryDetailMenu(int $lotteryId): array
{
    return ['inline_keyboard' => [
        [['text' => '✅ شرکت در قرعه‌کشی', 'callback_data' => 'join_lottery_' . $lotteryId]],
        [['text' => '🔙 بازگشت', 'callback_data' => 'lotteries']],
    ]];
}

function challengesMenu(): array
{
    return ['inline_keyboard' => [
        [['text' => '🎲 تاس', 'callback_data' => 'play_dice']],
        [['text' => '🎯 دارت', 'callback_data' => 'play_dart']],
        [['text' => '🎰 کازینو', 'callback_data' => 'play_slot']],
        [['text' => '🔙 منوی اصلی', 'callback_data' => 'main_menu']],
    ]];
}

function categoryManageKeyboard(int $id): array
{
    return ['inline_keyboard' => [
        [
            ['text' => '✏️ نام', 'callback_data' => 'cat_edit_title_' . $id],
            ['text' => '📝 توضیح', 'callback_data' => 'cat_edit_desc_' . $id],
        ],
        [
            ['text' => '↕️ ترتیب', 'callback_data' => 'cat_edit_sort_' . $id],
            ['text' => '🔄 فعال/غیرفعال', 'callback_data' => 'cat_toggle_' . $id],
        ],
        [['text' => '❌ حذف دسته', 'callback_data' => 'cat_delete_' . $id]],
        [['text' => '🔙 دسته‌بندی‌ها', 'callback_data' => 'admin_list_categories']],
    ]];
}

function productManageKeyboard(int $id): array
{
    return ['inline_keyboard' => [
        [
            ['text' => '✏️ نام', 'callback_data' => 'prod_edit_title_' . $id],
            ['text' => '📝 کوتاه', 'callback_data' => 'prod_edit_short_' . $id],
        ],
        [
            ['text' => '📖 کامل', 'callback_data' => 'prod_edit_full_' . $id],
            ['text' => '🖼 پیش‌نمایش', 'callback_data' => 'prod_edit_preview_' . $id],
        ],
        [
            ['text' => '👥 رفرال', 'callback_data' => 'prod_edit_ref_' . $id],
            ['text' => '📂 دسته', 'callback_data' => 'prod_edit_category_' . $id],
        ],
        [
            ['text' => '🔄 فعال/غیرفعال', 'callback_data' => 'prod_toggle_' . $id],
            ['text' => '⭐ ویژه', 'callback_data' => 'prod_feature_' . $id],
        ],
        [
            ['text' => '⚙️ نوع تحویل', 'callback_data' => 'prod_delivery_' . $id],
            ['text' => '📦 مخزن', 'callback_data' => 'inventory_product_' . $id],
        ],
        [['text' => '❌ حذف محصول', 'callback_data' => 'prod_delete_' . $id]],
        [['text' => '🔙 محصولات', 'callback_data' => 'admin_list_products']],
    ]];
}

function lotteryManageKeyboard(int $id): array
{
    return ['inline_keyboard' => [
        [
            ['text' => '✏️ عنوان', 'callback_data' => 'lot_edit_title_' . $id],
            ['text' => '📝 توضیح', 'callback_data' => 'lot_edit_desc_' . $id],
        ],
        [
            ['text' => '🎁 جایزه', 'callback_data' => 'lot_edit_reward_' . $id],
            ['text' => '👥 حداقل رفرال', 'callback_data' => 'lot_edit_minref_' . $id],
        ],
        [
            ['text' => '🔁 سهمیه', 'callback_data' => 'lot_edit_max_' . $id],
            ['text' => '🏆 برنده', 'callback_data' => 'lot_edit_winners_' . $id],
        ],
        [
            ['text' => '⏳ زمان پایان', 'callback_data' => 'lot_edit_days_' . $id],
            ['text' => '🔄 فعال/غیرفعال', 'callback_data' => 'lot_toggle_' . $id],
        ],
        [['text' => '❌ حذف قرعه‌کشی', 'callback_data' => 'lot_delete_' . $id]],
        [['text' => '🔙 قرعه‌کشی‌ها', 'callback_data' => 'admin_list_lotteries']],
    ]];
}

function inventoryManageKeyboard(int $productId): array
{
    return ['inline_keyboard' => [
        [['text' => '➕ افزودن به مخزن', 'callback_data' => 'inventory_add_' . $productId]],
        [['text' => '📊 وضعیت مخزن', 'callback_data' => 'inventory_status_' . $productId]],
        [['text' => '🧹 پاک کردن موجودی استفاده‌نشده', 'callback_data' => 'inventory_clear_' . $productId]],
        [['text' => '🔙 مدیریت محصول', 'callback_data' => 'product_manage_' . $productId]],
    ]];
}

/* =========================================================
   STATE HANDLER
   ========================================================= */
function handleStateMessage(array $message, array $user): bool
{
    $state = getState((int)$user['id']);
    if (!$state) return false;
    if (!isAdminUser((int)$user['id'])) {
        clearState((int)$user['id']);
        return false;
    }

    $chatId = (int)$message['chat']['id'];
    $text = trim((string)($message['text'] ?? ''));

    switch ($state['state_name']) {
        case 'admin_set_report_channel':
            if ($text === '') { sendMessage($chatId, 'مقدار معتبر نیست.'); return true; }
            setSetting('report_channel', $text);
            clearState((int)$user['id']);
            sendMessage($chatId, "✅ کانال گزارشات ذخیره شد.", adminMenu());
            return true;

        case 'admin_add_force_channel':
            if ($text === '') { sendMessage($chatId, 'مقدار معتبر نیست.'); return true; }
            $channels = getForceChannels();
            if (!in_array($text, $channels, true)) $channels[] = $text;
            saveForceChannels($channels);
            clearState((int)$user['id']);
            sendMessage($chatId, "✅ کانال عضویت اجباری اضافه شد.", adminMenu());
            return true;

        case 'admin_remove_force_channel':
            if ($text === '') { sendMessage($chatId, 'مقدار معتبر نیست.'); return true; }
            $channels = array_values(array_filter(getForceChannels(), fn($x) => $x !== $text));
            saveForceChannels($channels);
            clearState((int)$user['id']);
            sendMessage($chatId, "✅ کانال عضویت اجباری حذف شد.", adminMenu());
            return true;

        case 'admin_set_force_join_text':
            setSetting('force_join_text', $text);
            clearState((int)$user['id']);
            sendMessage($chatId, "✅ متن عضویت اجباری ذخیره شد.", adminMenu());
            return true;

        case 'admin_set_bot_username':
            if ($text === '') { sendMessage($chatId, 'مقدار معتبر نیست.'); return true; }
            setSetting('bot_username', ltrim($text, '@'));
            clearState((int)$user['id']);
            sendMessage($chatId, "✅ یوزرنیم ربات ذخیره شد.", adminMenu());
            return true;

        case 'admin_set_welcome_text':
            setSetting('welcome_text', $text);
            clearState((int)$user['id']);
            sendMessage($chatId, "✅ متن خوشامدگویی ذخیره شد.", adminMenu());
            return true;

        case 'admin_set_rules_text':
            setSetting('rules_text', $text);
            clearState((int)$user['id']);
            sendMessage($chatId, "✅ متن قوانین ذخیره شد.", adminMenu());
            return true;

        case 'admin_set_support_text':
            setSetting('support_text', $text);
            clearState((int)$user['id']);
            sendMessage($chatId, "✅ متن پشتیبانی ذخیره شد.", adminMenu());
            return true;

        case 'admin_add_admin':
            if (!ctype_digit($text)) { sendMessage($chatId, 'فقط آیدی عددی بفرست.'); return true; }
            $id = (int)$text;
            $admins = getAdmins();
            if (!in_array($id, $admins, true)) $admins[] = $id;
            saveAdmins($admins);
            $u = getUser($id);
            if ($u) {
                $u['is_admin'] = 1;
                saveUserRow($u);
            }
            clearState((int)$user['id']);
            sendMessage($chatId, "✅ ادمین اضافه شد.", adminMenu());
            return true;

        case 'admin_remove_admin':
            if (!ctype_digit($text)) { sendMessage($chatId, 'فقط آیدی عددی بفرست.'); return true; }
            $id = (int)$text;
            if ($id === OWNER_ID) { sendMessage($chatId, 'مالک اصلی قابل حذف نیست.'); return true; }
            $admins = array_values(array_filter(getAdmins(), fn($x) => $x !== $id));
            saveAdmins($admins);
            $u = getUser($id);
            if ($u) {
                $u['is_admin'] = 0;
                saveUserRow($u);
            }
            clearState((int)$user['id']);
            sendMessage($chatId, "✅ ادمین حذف شد.", adminMenu());
            return true;

        case 'admin_add_category_title':
            if ($text === '') { sendMessage($chatId, 'نام معتبر نیست.'); return true; }
            setState((int)$user['id'], 'admin_add_category_description', ['title' => $text]);
            sendMessage($chatId, 'توضیح دسته‌بندی را بفرست:');
            return true;

        case 'admin_add_category_description':
            $row = [
                'id' => next_id('categories'),
                'title' => (string)$state['state_data']['title'],
                'description' => $text,
                'is_active' => 1,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            saveCategoryRow($row);
            clearState((int)$user['id']);
            sendMessage($chatId, "✅ دسته‌بندی اضافه شد.", adminCategoriesMenu());
            return true;

        case 'admin_edit_category_title':
            $row = getCategory((int)$state['state_data']['id']);
            if (!$row) { clearState((int)$user['id']); sendMessage($chatId, 'دسته پیدا نشد.'); return true; }
            $row['title'] = $text;
            $row['updated_at'] = now();
            saveCategoryRow($row);
            clearState((int)$user['id']);
            sendMessage($chatId, "✅ نام دسته ویرایش شد.", adminCategoriesMenu());
            return true;

        case 'admin_edit_category_description':
            $row = getCategory((int)$state['state_data']['id']);
            if (!$row) { clearState((int)$user['id']); sendMessage($chatId, 'دسته پیدا نشد.'); return true; }
            $row['description'] = $text;
            $row['updated_at'] = now();
            saveCategoryRow($row);
            clearState((int)$user['id']);
            sendMessage($chatId, "✅ توضیح دسته ویرایش شد.", adminCategoriesMenu());
            return true;

        case 'admin_edit_category_sort':
            if (!is_numeric($text)) { sendMessage($chatId, 'فقط عدد.'); return true; }
            $row = getCategory((int)$state['state_data']['id']);
            if (!$row) { clearState((int)$user['id']); sendMessage($chatId, 'دسته پیدا نشد.'); return true; }
            $row['sort_order'] = (int)$text;
            $row['updated_at'] = now();
            saveCategoryRow($row);
            clearState((int)$user['id']);
            sendMessage($chatId, "✅ ترتیب دسته ویرایش شد.", adminCategoriesMenu());
            return true;

        case 'admin_add_product_category':
            if (!ctype_digit($text) || !getCategory((int)$text)) { sendMessage($chatId, 'آیدی دسته معتبر نیست.'); return true; }
            setState((int)$user['id'], 'admin_add_product_title', ['category_id' => (int)$text]);
            sendMessage($chatId, 'نام محصول را بفرست:');
            return true;

        case 'admin_add_product_title':
            if ($text === '') { sendMessage($chatId, 'نام معتبر نیست.'); return true; }
            $d = $state['state_data']; $d['title'] = $text;
            setState((int)$user['id'], 'admin_add_product_short', $d);
            sendMessage($chatId, 'توضیح کوتاه را بفرست:');
            return true;

        case 'admin_add_product_short':
            $d = $state['state_data']; $d['short_desc'] = $text;
            setState((int)$user['id'], 'admin_add_product_full', $d);
            sendMessage($chatId, 'توضیح کامل را بفرست:');
            return true;

        case 'admin_add_product_full':
            $d = $state['state_data']; $d['full_desc'] = $text;
            setState((int)$user['id'], 'admin_add_product_preview', $d);
            sendMessage($chatId, 'متن پیش‌نمایش را بفرست:');
            return true;

        case 'admin_add_product_preview':
            $d = $state['state_data']; $d['preview_text'] = $text;
            setState((int)$user['id'], 'admin_add_product_ref', $d);
            sendMessage($chatId, 'تعداد رفرال لازم را بفرست:');
            return true;

        case 'admin_add_product_ref':
            if (!ctype_digit($text)) { sendMessage($chatId, 'فقط عدد.'); return true; }
            $d = $state['state_data']; $d['referral_cost'] = (int)$text;
            setState((int)$user['id'], 'admin_add_product_delivery', $d);
            sendMessage($chatId, "نوع تحویل را بفرست:\n<code>manual</code> یا <code>auto</code>");
            return true;

        case 'admin_add_product_delivery':
            $mode = strtolower($text);
            if (!in_array($mode, ['manual', 'auto'], true)) { sendMessage($chatId, 'فقط manual یا auto.'); return true; }
            $d = $state['state_data'];
            $row = [
                'id' => next_id('products'),
                'category_id' => (int)$d['category_id'],
                'title' => (string)$d['title'],
                'short_desc' => (string)($d['short_desc'] ?? ''),
                'full_desc' => (string)($d['full_desc'] ?? ''),
                'preview_text' => (string)($d['preview_text'] ?? ''),
                'referral_cost' => (int)$d['referral_cost'],
                'delivery_mode' => $mode,
                'is_active' => 1,
                'is_featured' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            saveProductRow($row);
            clearState((int)$user['id']);
            sendMessage($chatId, "✅ محصول اضافه شد.", adminProductsMenu());
            return true;

        case 'admin_edit_product_title':
        case 'admin_edit_product_short':
        case 'admin_edit_product_full':
        case 'admin_edit_product_preview':
        case 'admin_edit_product_ref':
        case 'admin_edit_product_category':
            $row = getProduct((int)$state['state_data']['id']);
            if (!$row) { clearState((int)$user['id']); sendMessage($chatId, 'محصول پیدا نشد.'); return true; }
            if ($state['state_name'] === 'admin_edit_product_title') $row['title'] = $text;
            if ($state['state_name'] === 'admin_edit_product_short') $row['short_desc'] = $text;
            if ($state['state_name'] === 'admin_edit_product_full') $row['full_desc'] = $text;
            if ($state['state_name'] === 'admin_edit_product_preview') $row['preview_text'] = $text;
            if ($state['state_name'] === 'admin_edit_product_ref') {
                if (!ctype_digit($text)) { sendMessage($chatId, 'فقط عدد.'); return true; }
                $row['referral_cost'] = (int)$text;
            }
            if ($state['state_name'] === 'admin_edit_product_category') {
                if (!ctype_digit($text) || !getCategory((int)$text)) { sendMessage($chatId, 'آیدی دسته معتبر نیست.'); return true; }
                $row['category_id'] = (int)$text;
            }
            $row['updated_at'] = now();
            saveProductRow($row);
            clearState((int)$user['id']);
            sendMessage($chatId, "✅ محصول ویرایش شد.", adminProductsMenu());
            return true;

        case 'admin_inventory_choose_product':
            if (!ctype_digit($text) || !getProduct((int)$text)) { sendMessage($chatId, 'آیدی محصول معتبر نیست.'); return true; }
            setState((int)$user['id'], 'admin_inventory_add_items', ['product_id' => (int)$text]);
            sendMessage($chatId, "هر خط = یک آیتم مخزن\n\nمثال:\n<code>user1:pass1\nuser2:pass2</code>");
            return true;

        case 'admin_inventory_add_items':
            $pid = (int)($state['state_data']['product_id'] ?? 0);
            if (!getProduct($pid)) { clearState((int)$user['id']); sendMessage($chatId, 'محصول پیدا نشد.'); return true; }
            $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text)));
            if (!$lines) { sendMessage($chatId, 'چیزی وارد نشده.'); return true; }
            $items = getInventory();
            $count = 0;
            foreach ($lines as $line) {
                $items[] = [
                    'id' => next_id('inventory'),
                    'product_id' => $pid,
                    'item_text' => $line,
                    'is_used' => 0,
                    'used_by' => 0,
                    'used_at' => '',
                    'created_at' => now(),
                ];
                $count++;
            }
            saveInventory($items);
            clearState((int)$user['id']);
            sendMessage($chatId, "✅ تعداد {$count} آیتم به مخزن اضافه شد.", adminProductsMenu());
            return true;

        case 'admin_inventory_clear_product':
            if (!ctype_digit($text) || !getProduct((int)$text)) { sendMessage($chatId, 'آیدی محصول معتبر نیست.'); return true; }
            $pid = (int)$text;
            saveInventory(array_values(array_filter(getInventory(), fn($x) => !((int)$x['product_id'] === $pid && (int)$x['is_used'] === 0))));
            clearState((int)$user['id']);
            sendMessage($chatId, "✅ موجودی استفاده‌نشده پاک شد.", adminProductsMenu());
            return true;

        case 'admin_add_lottery_title':
            if ($text === '') { sendMessage($chatId, 'عنوان معتبر نیست.'); return true; }
            setState((int)$user['id'], 'admin_add_lottery_desc', ['title' => $text]);
            sendMessage($chatId, 'توضیح قرعه‌کشی را بفرست:');
            return true;

        case 'admin_add_lottery_desc':
            $d = $state['state_data']; $d['description'] = $text;
            setState((int)$user['id'], 'admin_add_lottery_reward', $d);
            sendMessage($chatId, 'جایزه را بفرست:');
            return true;

        case 'admin_add_lottery_reward':
            $d = $state['state_data']; $d['reward'] = $text;
            setState((int)$user['id'], 'admin_add_lottery_min_ref', $d);
            sendMessage($chatId, 'حداقل رفرال لازم را بفرست:');
            return true;

        case 'admin_add_lottery_min_ref':
            if (!ctype_digit($text)) { sendMessage($chatId, 'فقط عدد.'); return true; }
            $d = $state['state_data']; $d['min_referrals'] = (int)$text;
            setState((int)$user['id'], 'admin_add_lottery_max_entries', $d);
            sendMessage($chatId, 'حداکثر سهمیه هر کاربر را بفرست:');
            return true;

        case 'admin_add_lottery_max_entries':
            if (!ctype_digit($text)) { sendMessage($chatId, 'فقط عدد.'); return true; }
            $d = $state['state_data']; $d['max_entries_per_user'] = (int)$text;
            setState((int)$user['id'], 'admin_add_lottery_winners', $d);
            sendMessage($chatId, 'تعداد برنده را بفرست:');
            return true;

        case 'admin_add_lottery_winners':
            if (!ctype_digit($text)) { sendMessage($chatId, 'فقط عدد.'); return true; }
            $d = $state['state_data']; $d['winners_count'] = (int)$text;
            setState((int)$user['id'], 'admin_add_lottery_days', $d);
            sendMessage($chatId, 'چند روز بعد تمام شود؟ فقط عدد:');
            return true;

        case 'admin_add_lottery_days':
            if (!ctype_digit($text)) { sendMessage($chatId, 'فقط عدد.'); return true; }
            $d = $state['state_data'];
            $row = [
                'id' => next_id('lotteries'),
                'title' => (string)$d['title'],
                'description' => (string)($d['description'] ?? ''),
                'reward' => (string)($d['reward'] ?? ''),
                'min_referrals' => (int)$d['min_referrals'],
                'max_entries_per_user' => (int)$d['max_entries_per_user'],
                'winners_count' => (int)$d['winners_count'],
                'is_active' => 1,
                'ends_at' => gmdate('Y-m-d H:i:s', time() + ((int)$text * 86400)),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            saveLotteryRow($row);
            clearState((int)$user['id']);
            sendMessage($chatId, "✅ قرعه‌کشی اضافه شد.", adminLotteriesMenu());
            return true;

        case 'admin_edit_lottery_title':
        case 'admin_edit_lottery_desc':
        case 'admin_edit_lottery_reward':
        case 'admin_edit_lottery_min_ref':
        case 'admin_edit_lottery_max_entries':
        case 'admin_edit_lottery_winners':
        case 'admin_edit_lottery_days':
            $row = getLottery((int)$state['state_data']['id']);
            if (!$row) { clearState((int)$user['id']); sendMessage($chatId, 'قرعه‌کشی پیدا نشد.'); return true; }
            if ($state['state_name'] === 'admin_edit_lottery_title') $row['title'] = $text;
            if ($state['state_name'] === 'admin_edit_lottery_desc') $row['description'] = $text;
            if ($state['state_name'] === 'admin_edit_lottery_reward') $row['reward'] = $text;
            if ($state['state_name'] === 'admin_edit_lottery_min_ref') {
                if (!ctype_digit($text)) { sendMessage($chatId, 'فقط عدد.'); return true; }
                $row['min_referrals'] = (int)$text;
            }
            if ($state['state_name'] === 'admin_edit_lottery_max_entries') {
                if (!ctype_digit($text)) { sendMessage($chatId, 'فقط عدد.'); return true; }
                $row['max_entries_per_user'] = (int)$text;
            }
            if ($state['state_name'] === 'admin_edit_lottery_winners') {
                if (!ctype_digit($text)) { sendMessage($chatId, 'فقط عدد.'); return true; }
                $row['winners_count'] = (int)$text;
            }
            if ($state['state_name'] === 'admin_edit_lottery_days') {
                if (!ctype_digit($text)) { sendMessage($chatId, 'فقط عدد.'); return true; }
                $row['ends_at'] = gmdate('Y-m-d H:i:s', time() + ((int)$text * 86400));
            }
            $row['updated_at'] = now();
            saveLotteryRow($row);
            clearState((int)$user['id']);
            sendMessage($chatId, "✅ قرعه‌کشی ویرایش شد.", adminLotteriesMenu());
            return true;

        case 'admin_broadcast_text':
            $ok = 0; $fail = 0;
            foreach (getUsers() as $u) {
                $res = sendMessage((int)$u['id'], $text);
                if ($res['ok'] ?? false) $ok++; else $fail++;
            }
            clearState((int)$user['id']);
            sendMessage($chatId, "📢 پیام همگانی ارسال شد.\n\n✅ موفق: {$ok}\n❌ ناموفق: {$fail}", adminMenu());
            return true;

        case 'admin_user_lookup':
            if (!ctype_digit($text)) { sendMessage($chatId, 'آیدی باید عدد باشد.'); return true; }
            $target = getUser((int)$text);
            clearState((int)$user['id']);
            if (!$target) { sendMessage($chatId, 'کاربر پیدا نشد.', adminMenu()); return true; }
            $msg = "👤 <b>اطلاعات کاربر</b>\n\n" .
                "🆔 <code>{$target['id']}</code>\n" .
                "🙍 " . esc($target['full_name']) . "\n" .
                "🔖 @" . esc($target['username'] ?: 'ندارد') . "\n" .
                "🛡 ادمین: " . ((int)$target['is_admin'] ? 'بله' : 'خیر') . "\n" .
                "👥 رفرال: {$target['referrals_count']}\n" .
                "♻️ مصرف‌شده: {$target['referrals_spent']}\n" .
                "✅ قابل استفاده: " . availableReferrals($target) . "\n" .
                "⭐ امتیاز: {$target['points']}\n" .
                "⚠️ اخطار: {$target['warnings']}\n" .
                "🚫 وضعیت: " . ((int)$target['is_blocked'] ? 'مسدود' : 'فعال') . "\n" .
                "📅 عضویت: {$target['joined_at']}\n" .
                "🕒 آخرین فعالیت: {$target['last_active_at']}";
            sendMessage($chatId, $msg, [
                'inline_keyboard' => [
                    [[
                        'text' => ((int)$target['is_blocked'] ? '✅ آزادسازی' : '🚫 مسدودسازی'),
                        'callback_data' => 'toggle_user_' . $target['id']
                    ]],
                    [['text' => '🔙 پنل مدیریت', 'callback_data' => 'admin_panel']],
                ]
            ]);
            return true;
    }

    return false;
}

/* =========================================================
   MESSAGE HANDLER
   ========================================================= */
function handleMessage(array $message): void
{
    if ((setting('bot_enabled', '1') ?? '1') !== '1') return;
    if (!isset($message['from'], $message['chat'])) return;

    $from = $message['from'];
    $chatId = (int)$message['chat']['id'];
    $text = trim((string)($message['text'] ?? ''));

    $payload = null;
    if (str_starts_with($text, '/start')) {
        $parts = explode(' ', $text, 2);
        $payload = $parts[1] ?? null;
    }

    $invitedBy = null;
    if ($payload && preg_match('/^ref_(\d+)$/', $payload, $m)) {
        $invitedBy = (int)$m[1];
    }

    $user = ensureUser($from, $invitedBy);
    if ((int)$user['is_blocked'] === 1) {
        sendMessage($chatId, 'حساب شما مسدود است.');
        return;
    }

    if (handleStateMessage($message, $user)) return;

    if ($text === '/start' || str_starts_with($text, '/start ')) {
        $missing = getForceJoinMissing((int)$user['id']);
        if ($missing) {
            sendMessage($chatId, forceJoinText($missing), forceJoinKeyboard($missing));
            return;
        }

        sendMessage($chatId, setting('welcome_text', 'سلام 🌹') ?: 'سلام 🌹', mainMenu(isAdminUser((int)$user['id'])));
        reportText(
            "🆕 <b>ورود کاربر</b>\n" .
            "👤 " . esc($user['full_name']) . "\n" .
            "🆔 <code>{$user['id']}</code>\n" .
            "🔖 @" . esc($user['username'] ?: 'ندارد') . "\n" .
            "👥 دعوت‌کننده: <code>" . esc((string)($user['invited_by'] ?? 'ندارد')) . "</code>"
        );
        return;
    }

    if ($text === '/menu') {
        $missing = getForceJoinMissing((int)$user['id']);
        if ($missing) {
            sendMessage($chatId, forceJoinText($missing), forceJoinKeyboard($missing));
            return;
        }
        sendMessage($chatId, 'منوی اصلی:', mainMenu(isAdminUser((int)$user['id'])));
        return;
    }

    if ($text === '/admin') {
        if (!isAdminUser((int)$user['id'])) {
            sendMessage($chatId, 'شما به این بخش دسترسی ندارید.');
            return;
        }
        sendMessage($chatId, '👑 پنل مدیریت', adminMenu());
        return;
    }

    sendMessage($chatId, "دستور نامعتبر است.\n\nاز /menu استفاده کن.", mainMenu(isAdminUser((int)$user['id'])));
}

/* =========================================================
   CALLBACK HANDLER
   ========================================================= */
function handleCallback(array $callback): void
{
    $data = (string)($callback['data'] ?? '');
    $from = $callback['from'] ?? null;
    $msg  = $callback['message'] ?? null;
    $callbackId = (string)($callback['id'] ?? '');

    if (!$from || !$msg) {
        answerCallback($callbackId);
        return;
    }

    $chatId = (int)$msg['chat']['id'];
    $messageId = (int)$msg['message_id'];
    $user = ensureUser($from);

    if ((int)$user['is_blocked'] === 1) {
        answerCallback($callbackId, 'حساب شما مسدود است.', true);
        return;
    }

    if ($data === 'check_force_join') {
        $missing = getForceJoinMissing((int)$user['id']);
        if ($missing) {
            editMessageText($chatId, $messageId, forceJoinText($missing), forceJoinKeyboard($missing));
            answerCallback($callbackId, 'هنوز عضو همه کانال‌ها نشده‌ای.', true);
            return;
        }
        editMessageText($chatId, $messageId, "✅ عضویت شما تأیید شد.\n\nبه ربات خوش آمدی.", mainMenu(isAdminUser((int)$user['id'])));
        answerCallback($callbackId, 'عضویت تأیید شد.');
        return;
    }

    /* user-side force join lock for important areas */
    $protectedActions = [
        'free_items', 'account', 'referral_link', 'rules', 'support',
        'my_requests', 'my_challenges', 'my_lotteries', 'challenges', 'lotteries'
    ];
    $needsCheck = in_array($data, $protectedActions, true)
        || preg_match('/^(cat_|product_|request_product_|lottery_|join_lottery_|play_dice|play_dart|play_slot)/', $data);

    if ($needsCheck) {
        $missing = getForceJoinMissing((int)$user['id']);
        if ($missing) {
            editMessageText($chatId, $messageId, forceJoinText($missing), forceJoinKeyboard($missing));
            answerCallback($callbackId, 'ابتدا عضو کانال‌های اجباری شو.', true);
            return;
        }
    }

    /* USER */
    if ($data === 'main_menu') {
        editMessageText($chatId, $messageId, 'منوی اصلی:', mainMenu(isAdminUser((int)$user['id'])));
        answerCallback($callbackId);
        return;
    }

    if ($data === 'account') {
        editMessageText($chatId, $messageId, accountText($user), accountMenu());
        answerCallback($callbackId);
        return;
    }

    if ($data === 'referral_link') {
        $link = 'https://t.me/' . botUsername() . '?start=ref_' . $user['id'];
        editMessageText($chatId, $messageId,
            "🔗 <b>لینک دعوت اختصاصی شما</b>\n\n<code>{$link}</code>",
            ['inline_keyboard' => [[['text' => '🔙 منوی اصلی', 'callback_data' => 'main_menu']]]]
        );
        answerCallback($callbackId);
        return;
    }

    if ($data === 'rules') {
        editMessageText($chatId, $messageId,
            "📘 <b>قوانین</b>\n\n" . esc(setting('rules_text', '')),
            ['inline_keyboard' => [[['text' => '🔙 منوی اصلی', 'callback_data' => 'main_menu']]]]
        );
        answerCallback($callbackId);
        return;
    }

    if ($data === 'support') {
        editMessageText($chatId, $messageId,
            "🆘 <b>پشتیبانی</b>\n\n" . esc(setting('support_text', '')),
            ['inline_keyboard' => [[['text' => '🔙 منوی اصلی', 'callback_data' => 'main_menu']]]]
        );
        answerCallback($callbackId);
        return;
    }

    if ($data === 'my_requests') {
        $rows = array_values(array_filter(getRequests(), fn($x) => (int)$x['user_id'] === (int)$user['id']));
        usort($rows, fn($a, $b) => (int)$b['id'] <=> (int)$a['id']);
        if (!$rows) {
            editMessageText($chatId, $messageId, '📜 هنوز درخواستی ثبت نکرده‌ای.', accountMenu());
            answerCallback($callbackId);
            return;
        }
        $text = "📜 <b>درخواست‌های شما</b>\n\n";
        foreach (array_slice($rows, 0, 20) as $r) {
            $p = getProduct((int)$r['product_id']);
            $text .= "#{$r['id']} | " . esc($p['title'] ?? 'محصول حذف شده') . "\n";
            $text .= "وضعیت: " . esc($r['status']) . "\n";
            $text .= "تاریخ: {$r['created_at']}\n\n";
        }
        editMessageText($chatId, $messageId, $text, accountMenu());
        answerCallback($callbackId);
        return;
    }

    if ($data === 'my_challenges') {
        $rows = array_values(array_filter(getChallengePlays(), fn($x) => (int)$x['user_id'] === (int)$user['id']));
        usort($rows, fn($a, $b) => (int)$b['id'] <=> (int)$a['id']);
        if (!$rows) {
            editMessageText($chatId, $messageId, '🎯 هنوز چالشی انجام نداده‌ای.', accountMenu());
            answerCallback($callbackId);
            return;
        }
        $text = "🎯 <b>تاریخچه چالش‌ها</b>\n\n";
        foreach (array_slice($rows, 0, 20) as $r) {
            $text .= esc($r['game_type']) . " | عدد: {$r['result_value']} | " . ((int)$r['won'] ? 'برد' : 'باخت') . " | امتیاز: {$r['reward_points']}\n";
            $text .= "{$r['created_at']}\n\n";
        }
        editMessageText($chatId, $messageId, $text, accountMenu());
        answerCallback($callbackId);
        return;
    }

    if ($data === 'my_lotteries') {
        $rows = array_values(array_filter(getLotteryEntries(), fn($x) => (int)$x['user_id'] === (int)$user['id']));
        usort($rows, fn($a, $b) => (int)$b['id'] <=> (int)$a['id']);
        if (!$rows) {
            editMessageText($chatId, $messageId, '🎉 هنوز در قرعه‌کشی‌ای شرکت نکرده‌ای.', accountMenu());
            answerCallback($callbackId);
            return;
        }
        $text = "🎉 <b>تاریخچه قرعه‌کشی‌های شما</b>\n\n";
        foreach (array_slice($rows, 0, 20) as $r) {
            $lot = getLottery((int)$r['lottery_id']);
            $text .= esc($lot['title'] ?? 'قرعه حذف شده') . "\n";
            $text .= "پاداش: " . esc($lot['reward'] ?? '-') . "\n";
            $text .= "تاریخ ثبت: {$r['created_at']}\n\n";
        }
        editMessageText($chatId, $messageId, $text, accountMenu());
        answerCallback($callbackId);
        return;
    }

    if ($data === 'free_items') {
        editMessageText($chatId, $messageId, "🎁 <b>چیزهای رایگان</b>\n\nابتدا دسته‌بندی را انتخاب کن:", categoriesMenu());
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^cat_(\d+)$/', $data, $m)) {
        $cat = getCategory((int)$m[1]);
        if (!$cat) {
            answerCallback($callbackId, 'دسته پیدا نشد.', true);
            return;
        }
        editMessageText($chatId, $messageId,
            "📂 <b>" . esc($cat['title']) . "</b>\n\n" . esc($cat['description'] ?: 'بدون توضیح') . "\n\nمحصول را انتخاب کن:",
            productsMenu((int)$cat['id'])
        );
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^product_(\d+)$/', $data, $m)) {
        $p = getProduct((int)$m[1]);
        if (!$p) {
            answerCallback($callbackId, 'محصول پیدا نشد.', true);
            return;
        }
        $cat = getCategory((int)$p['category_id']);
        $stock = $p['delivery_mode'] === 'auto' ? productStock((int)$p['id']) : 'دستی';
        $text = "🎁 <b>" . esc($p['title']) . "</b>\n\n" .
            "📂 <b>دسته:</b> " . esc($cat['title'] ?? '-') . "\n" .
            "📝 <b>توضیح کوتاه:</b>\n" . esc($p['short_desc'] ?: '-') . "\n\n" .
            "📖 <b>توضیح کامل:</b>\n" . esc($p['full_desc'] ?: '-') . "\n\n" .
            "🖼 <b>پیش‌نمایش:</b>\n" . esc($p['preview_text'] ?: '-') . "\n\n" .
            "👥 <b>رفرال لازم:</b> {$p['referral_cost']}\n" .
            "📦 <b>موجودی:</b> {$stock}\n" .
            "⚙️ <b>تحویل:</b> " . ($p['delivery_mode'] === 'auto' ? 'خودکار' : 'دستی');
        editMessageText($chatId, $messageId, $text, productDetailMenu((int)$p['id']));
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^request_product_(\d+)$/', $data, $m)) {
        $res = createProductRequest((int)$user['id'], (int)$m[1]);
        if (!$res['ok']) {
            answerCallback($callbackId, $res['message'], true);
            return;
        }
        editMessageText($chatId, $messageId, "✅ " . $res['message'], ['inline_keyboard' => [[['text' => '🔙 منوی اصلی', 'callback_data' => 'main_menu']]]]);
        answerCallback($callbackId);
        return;
    }

    if ($data === 'challenges') {
        if ((setting('challenge_enabled', '1') ?? '1') !== '1') {
            answerCallback($callbackId, 'بخش چالش غیرفعال است.', true);
            return;
        }
        editMessageText($chatId, $messageId, "🎯 <b>چالش رایگان</b>", challengesMenu());
        answerCallback($callbackId);
        return;
    }

    if (in_array($data, ['play_dice', 'play_dart', 'play_slot'], true)) {
        $emoji = $data === 'play_dice' ? '🎲' : ($data === 'play_dart' ? '🎯' : '🎰');
        $res = sendDice($chatId, $emoji);
        if (!($res['ok'] ?? false)) {
            answerCallback($callbackId, 'خطا در اجرای بازی.', true);
            return;
        }
        $value = (int)($res['result']['dice']['value'] ?? 0);
        if ($data === 'play_dice') { $won = $value >= 5; $reward = $won ? 2 : 0; $type = 'dice'; }
        elseif ($data === 'play_dart') { $won = $value >= 5; $reward = $won ? 3 : 0; $type = 'dart'; }
        else { $won = $value >= 50; $reward = $won ? 5 : 0; $type = 'slot'; }

        registerChallenge((int)$user['id'], $type, $value, $won, $reward);
        sendMessage($chatId, $emoji . " نتیجه بازی شما: <b>{$value}</b>\n" . ($won ? '✅ بردی' : '❌ باختی') . "\n⭐ امتیاز: {$reward}", ['inline_keyboard' => [[['text' => '🔙 منوی اصلی', 'callback_data' => 'main_menu']]]]);
        answerCallback($callbackId);
        return;
    }

    if ($data === 'lotteries') {
        if ((setting('lottery_enabled', '1') ?? '1') !== '1') {
            answerCallback($callbackId, 'بخش قرعه‌کشی غیرفعال است.', true);
            return;
        }
        editMessageText($chatId, $messageId, "🎉 <b>قرعه‌کشی‌های فعال</b>", lotteriesMenu());
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^lottery_(\d+)$/', $data, $m)) {
        $lot = getLottery((int)$m[1]);
        if (!$lot) {
            answerCallback($callbackId, 'قرعه‌کشی پیدا نشد.', true);
            return;
        }
        $entries = array_values(array_filter(getLotteryEntries(), fn($x) => (int)$x['lottery_id'] === (int)$lot['id']));
        $text = "🎉 <b>" . esc($lot['title']) . "</b>\n\n" .
            "📝 " . esc($lot['description']) . "\n\n" .
            "🎁 جایزه: " . esc($lot['reward']) . "\n" .
            "👥 حداقل رفرال: {$lot['min_referrals']}\n" .
            "🔁 سهمیه هر کاربر: {$lot['max_entries_per_user']}\n" .
            "🏆 تعداد برنده: {$lot['winners_count']}\n" .
            "👤 شرکت‌کننده: " . count($entries) . "\n" .
            "📅 پایان: " . esc($lot['ends_at'] ?: 'ندارد');
        editMessageText($chatId, $messageId, $text, lotteryDetailMenu((int)$lot['id']));
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^join_lottery_(\d+)$/', $data, $m)) {
        $res = joinLottery((int)$user['id'], (int)$m[1]);
        if (!$res['ok']) {
            answerCallback($callbackId, $res['message'], true);
            return;
        }
        editMessageText($chatId, $messageId, "✅ " . $res['message'], ['inline_keyboard' => [[['text' => '🔙 منوی اصلی', 'callback_data' => 'main_menu']]]]);
        answerCallback($callbackId);
        return;
    }

    /* ADMIN GATE */
    if (
        str_starts_with($data, 'admin_') ||
        str_starts_with($data, 'category_manage_') ||
        str_starts_with($data, 'product_manage_') ||
        str_starts_with($data, 'lottery_manage_') ||
        str_starts_with($data, 'inventory_') ||
        str_starts_with($data, 'cat_') ||
        str_starts_with($data, 'prod_') ||
        str_starts_with($data, 'lot_') ||
        str_starts_with($data, 'approve_req_') ||
        str_starts_with($data, 'reject_req_') ||
        str_starts_with($data, 'toggle_user_')
    ) {
        if (!isAdminUser((int)$user['id'])) {
            answerCallback($callbackId, 'دسترسی ندارید.', true);
            return;
        }
    }

    if ($data === 'admin_panel') {
        editMessageText($chatId, $messageId, '👑 پنل مدیریت', adminMenu());
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_products') {
        editMessageText($chatId, $messageId, '📦 مدیریت محصولات', adminProductsMenu());
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_categories') {
        editMessageText($chatId, $messageId, '🗂 مدیریت دسته‌بندی‌ها', adminCategoriesMenu());
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_requests') {
        $rows = getRequests();
        usort($rows, fn($a, $b) => (int)$b['id'] <=> (int)$a['id']);
        $text = "📨 <b>آخرین درخواست‌ها</b>\n\n";
        if (!$rows) {
            $text .= "درخواستی ثبت نشده.";
        } else {
            foreach (array_slice($rows, 0, 20) as $r) {
                $u = getUser((int)$r['user_id']);
                $p = getProduct((int)$r['product_id']);
                $text .= "#{$r['id']} | " . esc($p['title'] ?? 'محصول حذف شده') . "\n";
                $text .= "کاربر: " . esc($u['full_name'] ?? 'نامشخص') . " | <code>{$r['user_id']}</code>\n";
                $text .= "وضعیت: " . esc($r['status']) . "\n";
                $text .= "تاریخ: {$r['created_at']}\n\n";
            }
        }
        editMessageText($chatId, $messageId, $text, adminMenu());
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^(approve_req|reject_req)_(\d+)$/', $data, $m)) {
        $res = $m[1] === 'approve_req'
            ? approveRequest((int)$user['id'], (int)$m[2])
            : rejectRequest((int)$user['id'], (int)$m[2]);
        answerCallback($callbackId, $res['message'], !$res['ok']);
        return;
    }

    if ($data === 'admin_users') {
        $rows = getUsers();
        usort($rows, fn($a, $b) => strcmp((string)$b['joined_at'], (string)$a['joined_at']));
        $text = "👥 <b>آخرین کاربران</b>\n\n";
        foreach (array_slice($rows, 0, 20) as $u) {
            $text .= esc($u['full_name']) . "\n";
            $text .= "🆔 <code>{$u['id']}</code> | @" . esc($u['username'] ?: 'ندارد') . "\n";
            $text .= "🛡 ادمین: " . ((int)$u['is_admin'] ? 'بله' : 'خیر') . "\n";
            $text .= "👥 رفرال: {$u['referrals_count']} | ⭐ {$u['points']}\n";
            $text .= "🚫 " . ((int)$u['is_blocked'] ? 'مسدود' : 'فعال') . "\n\n";
        }
        editMessageText($chatId, $messageId, $text, [
            'inline_keyboard' => [
                [['text' => '🔎 جستجوی کاربر با آیدی', 'callback_data' => 'admin_lookup_user']],
                [['text' => '🔙 پنل مدیریت', 'callback_data' => 'admin_panel']],
            ]
        ]);
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_lookup_user') {
        setState((int)$user['id'], 'admin_user_lookup');
        sendMessage($chatId, 'آیدی عددی کاربر را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^toggle_user_(\d+)$/', $data, $m)) {
        $target = getUser((int)$m[1]);
        if (!$target) { answerCallback($callbackId, 'کاربر پیدا نشد.', true); return; }
        $target['is_blocked'] = (int)$target['is_blocked'] ? 0 : 1;
        saveUserRow($target);
        answerCallback($callbackId, (int)$target['is_blocked'] ? 'کاربر مسدود شد.' : 'کاربر آزاد شد.');
        return;
    }

    if ($data === 'admin_admins') {
        $admins = getAdmins();
        $text = "🛡 <b>لیست ادمین‌ها</b>\n\n";
        foreach ($admins as $id) {
            $u = getUser($id);
            $name = $u['full_name'] ?? 'نامشخص';
            $username = $u['username'] ?? '';
            $text .= "• <code>{$id}</code> | " . esc($name) . ' | @' . esc($username ?: 'ندارد');
            if ($id === OWNER_ID) $text .= " | مالک";
            $text .= "\n";
        }
        editMessageText($chatId, $messageId, $text, [
            'inline_keyboard' => [
                [['text' => '➕ افزودن ادمین', 'callback_data' => 'admin_add_admin']],
                [['text' => '➖ حذف ادمین', 'callback_data' => 'admin_remove_admin']],
                [['text' => '🔙 پنل مدیریت', 'callback_data' => 'admin_panel']],
            ]
        ]);
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_add_admin') {
        setState((int)$user['id'], 'admin_add_admin');
        sendMessage($chatId, 'آیدی عددی ادمین جدید را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_remove_admin') {
        setState((int)$user['id'], 'admin_remove_admin');
        sendMessage($chatId, 'آیدی عددی ادمینی که می‌خواهی حذف شود را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_challenges') {
        $plays = getChallengePlays();
        $total = count($plays);
        $wins = count(array_filter($plays, fn($x) => (int)$x['won'] === 1));
        $enabled = setting('challenge_enabled', '1') === '1';
        editMessageText($chatId, $messageId,
            "🎯 <b>مدیریت چالش‌ها</b>\n\nکل بازی‌ها: {$total}\nکل بردها: {$wins}\nوضعیت: " . ($enabled ? 'روشن' : 'خاموش'),
            [
                'inline_keyboard' => [
                    [[
                        'text' => $enabled ? '🔴 خاموش کردن چالش' : '🟢 روشن کردن چالش',
                        'callback_data' => 'toggle_challenge_enabled'
                    ]],
                    [['text' => '🔙 پنل مدیریت', 'callback_data' => 'admin_panel']],
                ]
            ]
        );
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_lotteries') {
        editMessageText($chatId, $messageId, '🎉 مدیریت قرعه‌کشی', adminLotteriesMenu());
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_broadcast') {
        setState((int)$user['id'], 'admin_broadcast_text');
        sendMessage($chatId, 'متن پیام همگانی را ارسال کن:');
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_stats') {
        $users = count(getUsers());
        $cats = count(getCategories());
        $prods = count(getProducts());
        $reqs = count(getRequests());
        $pending = count(array_filter(getRequests(), fn($x) => $x['status'] === 'pending'));
        $plays = count(getChallengePlays());
        $lots = count(getLotteries());
        $admins = count(getAdmins());
        $forceChannels = count(getForceChannels());

        editMessageText($chatId, $messageId,
            "📊 <b>آمار کلی</b>\n\n" .
            "👥 کاربران: {$users}\n" .
            "🛡 ادمین‌ها: {$admins}\n" .
            "🗂 دسته‌بندی‌ها: {$cats}\n" .
            "🎁 محصولات: {$prods}\n" .
            "📦 کانال‌های اجباری: {$forceChannels}\n" .
            "📨 درخواست‌ها: {$reqs}\n" .
            "⏳ در انتظار: {$pending}\n" .
            "🎯 بازی‌ها: {$plays}\n" .
            "🎉 قرعه‌کشی‌ها: {$lots}",
            adminMenu()
        );
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_logs') {
        $rows = load_json('admin_logs.json', []);
        usort($rows, fn($a, $b) => (int)$b['id'] <=> (int)$a['id']);
        $text = "🧾 <b>لاگ‌های مدیریت</b>\n\n";
        if (!$rows) $text .= "لاگی وجود ندارد.";
        else {
            foreach (array_slice($rows, 0, 20) as $r) {
                $text .= "#{$r['id']} | " . esc($r['action_name']) . "\n";
                $text .= "ادمین: <code>{$r['admin_id']}</code>\n";
                $text .= "زمان: {$r['created_at']}\n\n";
            }
        }
        editMessageText($chatId, $messageId, $text, adminMenu());
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_settings') {
        $channels = getForceChannels();
        $channelsText = $channels ? implode("\n", array_map(fn($x) => "• <code>" . esc($x) . "</code>", $channels)) : 'ندارد';

        $text = "⚙️ <b>تنظیمات</b>\n\n" .
            "🤖 ربات: " . (setting('bot_enabled', '1') === '1' ? 'روشن' : 'خاموش') . "\n" .
            "🎯 چالش: " . (setting('challenge_enabled', '1') === '1' ? 'روشن' : 'خاموش') . "\n" .
            "🎉 قرعه‌کشی: " . (setting('lottery_enabled', '1') === '1' ? 'روشن' : 'خاموش') . "\n" .
            "📣 کانال گزارشات: <code>" . esc(reportChannel() ?: 'تنظیم نشده') . "</code>\n" .
            "🔗 یوزرنیم ربات: <code>" . esc(botUsername()) . "</code>\n\n" .
            "📦 <b>کانال‌های عضویت اجباری:</b>\n" . $channelsText;

        editMessageText($chatId, $messageId, $text, [
            'inline_keyboard' => [
                [[
                    'text' => setting('bot_enabled', '1') === '1' ? '🔴 خاموش کردن ربات' : '🟢 روشن کردن ربات',
                    'callback_data' => 'toggle_bot_enabled'
                ]],
                [[
                    'text' => setting('challenge_enabled', '1') === '1' ? '🔴 خاموش کردن چالش' : '🟢 روشن کردن چالش',
                    'callback_data' => 'toggle_challenge_enabled'
                ]],
                [[
                    'text' => setting('lottery_enabled', '1') === '1' ? '🔴 خاموش کردن قرعه‌کشی' : '🟢 روشن کردن قرعه‌کشی',
                    'callback_data' => 'toggle_lottery_enabled'
                ]],
                [['text' => '📣 تنظیم کانال گزارشات', 'callback_data' => 'admin_set_report_channel']],
                [['text' => '➕ افزودن کانال اجباری', 'callback_data' => 'admin_add_force_channel']],
                [['text' => '➖ حذف کانال اجباری', 'callback_data' => 'admin_remove_force_channel']],
                [['text' => '✏️ متن عضویت اجباری', 'callback_data' => 'admin_set_force_join_text']],
                [['text' => '🔗 تنظیم یوزرنیم ربات', 'callback_data' => 'admin_set_bot_username']],
                [['text' => '✏️ متن خوشامدگویی', 'callback_data' => 'admin_set_welcome_text']],
                [['text' => '✏️ متن قوانین', 'callback_data' => 'admin_set_rules_text']],
                [['text' => '✏️ متن پشتیبانی', 'callback_data' => 'admin_set_support_text']],
                [['text' => '🔙 پنل مدیریت', 'callback_data' => 'admin_panel']],
            ]
        ]);
        answerCallback($callbackId);
        return;
    }

    if (in_array($data, ['toggle_bot_enabled', 'toggle_challenge_enabled', 'toggle_lottery_enabled'], true)) {
        $map = [
            'toggle_bot_enabled' => 'bot_enabled',
            'toggle_challenge_enabled' => 'challenge_enabled',
            'toggle_lottery_enabled' => 'lottery_enabled',
        ];
        $key = $map[$data];
        $new = setting($key, '1') === '1' ? '0' : '1';
        setSetting($key, $new);
        answerCallback($callbackId, 'تنظیم شد.');
        $callback['data'] = 'admin_settings';
        handleCallback($callback);
        return;
    }

    if ($data === 'admin_set_report_channel') {
        setState((int)$user['id'], 'admin_set_report_channel');
        sendMessage($chatId, "آیدی یا یوزرنیم کانال گزارشات را بفرست.\nمثال:\n<code>@channel</code>\nیا\n<code>-1001234567890</code>");
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_add_force_channel') {
        setState((int)$user['id'], 'admin_add_force_channel');
        sendMessage($chatId, "یوزرنیم کانال اجباری را بفرست.\nمثال:\n<code>@channel</code>");
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_remove_force_channel') {
        setState((int)$user['id'], 'admin_remove_force_channel');
        sendMessage($chatId, "یوزرنیم کانال اجباری که می‌خواهی حذف شود را بفرست.\nمثال:\n<code>@channel</code>");
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_set_force_join_text') {
        setState((int)$user['id'], 'admin_set_force_join_text');
        sendMessage($chatId, 'متن پیام عضویت اجباری را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_set_bot_username') {
        setState((int)$user['id'], 'admin_set_bot_username');
        sendMessage($chatId, "یوزرنیم ربات را بفرست.\nمثال:\n<code>MyStoreBot</code>");
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_set_welcome_text') {
        setState((int)$user['id'], 'admin_set_welcome_text');
        sendMessage($chatId, 'متن خوشامدگویی را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_set_rules_text') {
        setState((int)$user['id'], 'admin_set_rules_text');
        sendMessage($chatId, 'متن قوانین را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_set_support_text') {
        setState((int)$user['id'], 'admin_set_support_text');
        sendMessage($chatId, 'متن پشتیبانی را بفرست:');
        answerCallback($callbackId);
        return;
    }

    /* categories */
    if ($data === 'admin_categories') {
        editMessageText($chatId, $messageId, '🗂 مدیریت دسته‌بندی‌ها', adminCategoriesMenu());
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_add_category') {
        setState((int)$user['id'], 'admin_add_category_title');
        sendMessage($chatId, 'نام دسته‌بندی را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_list_categories') {
        $rows = getCategories();
        usort($rows, fn($a, $b) => ((int)$a['sort_order'] <=> (int)$b['sort_order']) ?: ((int)$b['id'] <=> (int)$a['id']));
        if (!$rows) {
            editMessageText($chatId, $messageId, "🗂 دسته‌ای وجود ندارد.", adminCategoriesMenu());
            answerCallback($callbackId);
            return;
        }
        $lines = [];
        foreach ($rows as $c) {
            $lines[] = "#{$c['id']} | " . esc($c['title']) . " | " . ((int)$c['is_active'] ? 'فعال' : 'غیرفعال') . " | sort: {$c['sort_order']}";
        }
        $kbRows = [];
        foreach ($rows as $c) {
            $kbRows[] = [[
                'text' => 'مدیریت دسته #' . $c['id'] . ' - ' . $c['title'],
                'callback_data' => 'category_manage_' . $c['id']
            ]];
        }
        $kbRows[] = [['text' => '🔙 مدیریت دسته‌ها', 'callback_data' => 'admin_categories']];
        editMessageText($chatId, $messageId, "🗂 <b>لیست دسته‌بندی‌ها</b>\n\n" . implode("\n", $lines), ['inline_keyboard' => $kbRows]);
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^category_manage_(\d+)$/', $data, $m)) {
        $row = getCategory((int)$m[1]);
        if (!$row) { answerCallback($callbackId, 'دسته پیدا نشد.', true); return; }
        $text = "🗂 <b>مدیریت دسته‌بندی</b>\n\n" .
            "🆔 <code>{$row['id']}</code>\n" .
            "نام: " . esc($row['title']) . "\n" .
            "توضیح: " . esc($row['description']) . "\n" .
            "ترتیب: {$row['sort_order']}\n" .
            "وضعیت: " . ((int)$row['is_active'] ? 'فعال' : 'غیرفعال');
        editMessageText($chatId, $messageId, $text, categoryManageKeyboard((int)$row['id']));
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^cat_edit_title_(\d+)$/', $data, $m)) {
        setState((int)$user['id'], 'admin_edit_category_title', ['id' => (int)$m[1]]);
        sendMessage($chatId, 'نام جدید دسته را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^cat_edit_desc_(\d+)$/', $data, $m)) {
        setState((int)$user['id'], 'admin_edit_category_description', ['id' => (int)$m[1]]);
        sendMessage($chatId, 'توضیح جدید دسته را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^cat_edit_sort_(\d+)$/', $data, $m)) {
        setState((int)$user['id'], 'admin_edit_category_sort', ['id' => (int)$m[1]]);
        sendMessage($chatId, 'ترتیب جدید را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^cat_toggle_(\d+)$/', $data, $m)) {
        $row = getCategory((int)$m[1]);
        if (!$row) { answerCallback($callbackId, 'دسته پیدا نشد.', true); return; }
        $row['is_active'] = (int)$row['is_active'] ? 0 : 1;
        $row['updated_at'] = now();
        saveCategoryRow($row);
        answerCallback($callbackId, (int)$row['is_active'] ? 'فعال شد.' : 'غیرفعال شد.');
        return;
    }

    if (preg_match('/^cat_delete_(\d+)$/', $data, $m)) {
        $id = (int)$m[1];
        foreach (getProducts() as $p) {
            if ((int)$p['category_id'] === $id) {
                answerCallback($callbackId, 'اول محصولات این دسته را حذف یا منتقل کن.', true);
                return;
            }
        }
        deleteCategoryById($id);
        answerCallback($callbackId, 'دسته حذف شد.');
        return;
    }

    /* products */
    if ($data === 'admin_add_product') {
        setState((int)$user['id'], 'admin_add_product_category');
        sendMessage($chatId, 'آیدی دسته‌بندی محصول را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_list_products') {
        $rows = getProducts();
        usort($rows, fn($a, $b) => (int)$b['id'] <=> (int)$a['id']);
        if (!$rows) {
            editMessageText($chatId, $messageId, "📦 محصولی وجود ندارد.", adminProductsMenu());
            answerCallback($callbackId);
            return;
        }
        $lines = [];
        foreach ($rows as $p) {
            $cat = getCategory((int)$p['category_id']);
            $stock = $p['delivery_mode'] === 'auto' ? productStock((int)$p['id']) : 'دستی';
            $lines[] = "#{$p['id']} | " . esc($p['title']) . " | " . esc($cat['title'] ?? '-') . " | ref={$p['referral_cost']} | {$p['delivery_mode']} | stock={$stock}";
        }
        $kbRows = [];
        foreach ($rows as $p) {
            $kbRows[] = [[
                'text' => 'مدیریت محصول #' . $p['id'] . ' - ' . $p['title'],
                'callback_data' => 'product_manage_' . $p['id']
            ]];
        }
        $kbRows[] = [['text' => '🔙 مدیریت محصولات', 'callback_data' => 'admin_products']];
        editMessageText($chatId, $messageId, "📦 <b>لیست محصولات</b>\n\n" . implode("\n", $lines), ['inline_keyboard' => $kbRows]);
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^product_manage_(\d+)$/', $data, $m)) {
        $row = getProduct((int)$m[1]);
        if (!$row) { answerCallback($callbackId, 'محصول پیدا نشد.', true); return; }
        $cat = getCategory((int)$row['category_id']);
        $stock = $row['delivery_mode'] === 'auto' ? productStock((int)$row['id']) : 'دستی';
        $text = "📦 <b>مدیریت محصول</b>\n\n" .
            "🆔 <code>{$row['id']}</code>\n" .
            "نام: " . esc($row['title']) . "\n" .
            "دسته: " . esc($cat['title'] ?? '-') . "\n" .
            "رفرال: {$row['referral_cost']}\n" .
            "تحویل: {$row['delivery_mode']}\n" .
            "ویژه: " . ((int)$row['is_featured'] ? 'بله' : 'خیر') . "\n" .
            "وضعیت: " . ((int)$row['is_active'] ? 'فعال' : 'غیرفعال') . "\n" .
            "موجودی: {$stock}";
        editMessageText($chatId, $messageId, $text, productManageKeyboard((int)$row['id']));
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^prod_edit_title_(\d+)$/', $data, $m)) {
        setState((int)$user['id'], 'admin_edit_product_title', ['id' => (int)$m[1]]);
        sendMessage($chatId, 'نام جدید محصول را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^prod_edit_short_(\d+)$/', $data, $m)) {
        setState((int)$user['id'], 'admin_edit_product_short', ['id' => (int)$m[1]]);
        sendMessage($chatId, 'توضیح کوتاه جدید را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^prod_edit_full_(\d+)$/', $data, $m)) {
        setState((int)$user['id'], 'admin_edit_product_full', ['id' => (int)$m[1]]);
        sendMessage($chatId, 'توضیح کامل جدید را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^prod_edit_preview_(\d+)$/', $data, $m)) {
        setState((int)$user['id'], 'admin_edit_product_preview', ['id' => (int)$m[1]]);
        sendMessage($chatId, 'پیش‌نمایش جدید را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^prod_edit_ref_(\d+)$/', $data, $m)) {
        setState((int)$user['id'], 'admin_edit_product_ref', ['id' => (int)$m[1]]);
        sendMessage($chatId, 'رفرال جدید را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^prod_edit_category_(\d+)$/', $data, $m)) {
        setState((int)$user['id'], 'admin_edit_product_category', ['id' => (int)$m[1]]);
        sendMessage($chatId, 'آیدی دسته جدید را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^prod_toggle_(\d+)$/', $data, $m)) {
        $row = getProduct((int)$m[1]);
        if (!$row) { answerCallback($callbackId, 'محصول پیدا نشد.', true); return; }
        $row['is_active'] = (int)$row['is_active'] ? 0 : 1;
        $row['updated_at'] = now();
        saveProductRow($row);
        answerCallback($callbackId, (int)$row['is_active'] ? 'فعال شد.' : 'غیرفعال شد.');
        return;
    }

    if (preg_match('/^prod_feature_(\d+)$/', $data, $m)) {
        $row = getProduct((int)$m[1]);
        if (!$row) { answerCallback($callbackId, 'محصول پیدا نشد.', true); return; }
        $row['is_featured'] = (int)$row['is_featured'] ? 0 : 1;
        $row['updated_at'] = now();
        saveProductRow($row);
        answerCallback($callbackId, (int)$row['is_featured'] ? 'ویژه شد.' : 'از حالت ویژه خارج شد.');
        return;
    }

    if (preg_match('/^prod_delivery_(\d+)$/', $data, $m)) {
        $row = getProduct((int)$m[1]);
        if (!$row) { answerCallback($callbackId, 'محصول پیدا نشد.', true); return; }
        $row['delivery_mode'] = $row['delivery_mode'] === 'auto' ? 'manual' : 'auto';
        $row['updated_at'] = now();
        saveProductRow($row);
        answerCallback($callbackId, 'نوع تحویل تغییر کرد.');
        return;
    }

    if (preg_match('/^prod_delete_(\d+)$/', $data, $m)) {
        deleteProductById((int)$m[1]);
        answerCallback($callbackId, 'محصول حذف شد.');
        return;
    }

    /* inventory */
    if ($data === 'admin_inventory_hub') {
        editMessageText($chatId, $messageId, "📦 <b>مدیریت مخزن محصولات</b>", [
            'inline_keyboard' => [
                [['text' => '➕ افزودن به مخزن محصول', 'callback_data' => 'admin_add_inventory']],
                [['text' => '📋 لیست محصولات برای مخزن', 'callback_data' => 'admin_list_products']],
                [['text' => '🔙 مدیریت محصولات', 'callback_data' => 'admin_products']],
            ]
        ]);
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_add_inventory') {
        setState((int)$user['id'], 'admin_inventory_choose_product');
        sendMessage($chatId, 'آیدی محصول را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^inventory_product_(\d+)$/', $data, $m)) {
        $pid = (int)$m[1];
        $p = getProduct($pid);
        if (!$p) { answerCallback($callbackId, 'محصول پیدا نشد.', true); return; }
        $items = array_values(array_filter(getInventory(), fn($x) => (int)$x['product_id'] === $pid));
        $available = count(array_filter($items, fn($x) => (int)$x['is_used'] === 0));
        $used = count(array_filter($items, fn($x) => (int)$x['is_used'] === 1));
        $text = "📦 <b>مخزن محصول</b>\n\n" .
            "محصول: " . esc($p['title']) . "\n" .
            "تحویل: {$p['delivery_mode']}\n" .
            "کل آیتم‌ها: " . count($items) . "\n" .
            "موجودی استفاده‌نشده: {$available}\n" .
            "استفاده‌شده: {$used}";
        editMessageText($chatId, $messageId, $text, inventoryManageKeyboard($pid));
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^inventory_add_(\d+)$/', $data, $m)) {
        setState((int)$user['id'], 'admin_inventory_add_items', ['product_id' => (int)$m[1]]);
        sendMessage($chatId, "هر خط = یک آیتم مخزن\n\nمثال:\n<code>user1:pass1\nuser2:pass2</code>");
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^inventory_status_(\d+)$/', $data, $m)) {
        $pid = (int)$m[1];
        $p = getProduct($pid);
        if (!$p) { answerCallback($callbackId, 'محصول پیدا نشد.', true); return; }
        $items = array_values(array_filter(getInventory(), fn($x) => (int)$x['product_id'] === $pid));
        $available = count(array_filter($items, fn($x) => (int)$x['is_used'] === 0));
        $used = count(array_filter($items, fn($x) => (int)$x['is_used'] === 1));
        editMessageText($chatId, $messageId,
            "📊 <b>وضعیت مخزن</b>\n\n" .
            "محصول: " . esc($p['title']) . "\n" .
            "کل آیتم‌ها: " . count($items) . "\n" .
            "موجودی فعلی: {$available}\n" .
            "مصرف‌شده: {$used}",
            inventoryManageKeyboard($pid)
        );
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^inventory_clear_(\d+)$/', $data, $m)) {
        setState((int)$user['id'], 'admin_inventory_clear_product');
        sendMessage($chatId, "برای پاک کردن موجودی استفاده‌نشده، آیدی همین محصول را دوباره بفرست:\n<code>{$m[1]}</code>");
        answerCallback($callbackId);
        return;
    }

    /* lotteries */
    if ($data === 'admin_add_lottery') {
        setState((int)$user['id'], 'admin_add_lottery_title');
        sendMessage($chatId, 'عنوان قرعه‌کشی را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if ($data === 'admin_list_lotteries') {
        $rows = getLotteries();
        usort($rows, fn($a, $b) => (int)$b['id'] <=> (int)$a['id']);
        if (!$rows) {
            editMessageText($chatId, $messageId, "🎉 قرعه‌کشی‌ای وجود ندارد.", adminLotteriesMenu());
            answerCallback($callbackId);
            return;
        }
        $lines = [];
        foreach ($rows as $l) {
            $usersCount = count(array_filter(getLotteryEntries(), fn($x) => (int)$x['lottery_id'] === (int)$l['id']));
            $lines[] = "#{$l['id']} | " . esc($l['title']) . " | users={$usersCount} | " . ((int)$l['is_active'] ? 'فعال' : 'بسته');
        }
        $kbRows = [];
        foreach ($rows as $l) {
            $kbRows[] = [[
                'text' => 'مدیریت قرعه #' . $l['id'] . ' - ' . $l['title'],
                'callback_data' => 'lottery_manage_' . $l['id']
            ]];
        }
        $kbRows[] = [['text' => '🔙 مدیریت قرعه‌کشی', 'callback_data' => 'admin_lotteries']];
        editMessageText($chatId, $messageId, "🎉 <b>لیست قرعه‌کشی‌ها</b>\n\n" . implode("\n", $lines), ['inline_keyboard' => $kbRows]);
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^lottery_manage_(\d+)$/', $data, $m)) {
        $row = getLottery((int)$m[1]);
        if (!$row) { answerCallback($callbackId, 'قرعه‌کشی پیدا نشد.', true); return; }
        $usersCount = count(array_filter(getLotteryEntries(), fn($x) => (int)$x['lottery_id'] === (int)$row['id']));
        $text = "🎉 <b>مدیریت قرعه‌کشی</b>\n\n" .
            "🆔 <code>{$row['id']}</code>\n" .
            "عنوان: " . esc($row['title']) . "\n" .
            "جایزه: " . esc($row['reward']) . "\n" .
            "حداقل رفرال: {$row['min_referrals']}\n" .
            "سهمیه هر کاربر: {$row['max_entries_per_user']}\n" .
            "تعداد برنده: {$row['winners_count']}\n" .
            "شرکت‌کننده: {$usersCount}\n" .
            "پایان: " . esc($row['ends_at'] ?: 'ندارد') . "\n" .
            "وضعیت: " . ((int)$row['is_active'] ? 'فعال' : 'بسته');
        editMessageText($chatId, $messageId, $text, lotteryManageKeyboard((int)$row['id']));
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^lot_edit_title_(\d+)$/', $data, $m)) {
        setState((int)$user['id'], 'admin_edit_lottery_title', ['id' => (int)$m[1]]);
        sendMessage($chatId, 'عنوان جدید قرعه‌کشی را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^lot_edit_desc_(\d+)$/', $data, $m)) {
        setState((int)$user['id'], 'admin_edit_lottery_desc', ['id' => (int)$m[1]]);
        sendMessage($chatId, 'توضیح جدید را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^lot_edit_reward_(\d+)$/', $data, $m)) {
        setState((int)$user['id'], 'admin_edit_lottery_reward', ['id' => (int)$m[1]]);
        sendMessage($chatId, 'جایزه جدید را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^lot_edit_minref_(\d+)$/', $data, $m)) {
        setState((int)$user['id'], 'admin_edit_lottery_min_ref', ['id' => (int)$m[1]]);
        sendMessage($chatId, 'حداقل رفرال جدید را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^lot_edit_max_(\d+)$/', $data, $m)) {
        setState((int)$user['id'], 'admin_edit_lottery_max_entries', ['id' => (int)$m[1]]);
        sendMessage($chatId, 'سهمیه هر کاربر را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^lot_edit_winners_(\d+)$/', $data, $m)) {
        setState((int)$user['id'], 'admin_edit_lottery_winners', ['id' => (int)$m[1]]);
        sendMessage($chatId, 'تعداد برنده جدید را بفرست:');
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^lot_edit_days_(\d+)$/', $data, $m)) {
        setState((int)$user['id'], 'admin_edit_lottery_days', ['id' => (int)$m[1]]);
        sendMessage($chatId, 'چند روز بعد تمام شود؟ فقط عدد:');
        answerCallback($callbackId);
        return;
    }

    if (preg_match('/^lot_toggle_(\d+)$/', $data, $m)) {
        $row = getLottery((int)$m[1]);
        if (!$row) { answerCallback($callbackId, 'قرعه‌کشی پیدا نشد.', true); return; }
        $row['is_active'] = (int)$row['is_active'] ? 0 : 1;
        $row['updated_at'] = now();
        saveLotteryRow($row);
        answerCallback($callbackId, (int)$row['is_active'] ? 'فعال شد.' : 'غیرفعال شد.');
        return;
    }

    if (preg_match('/^lot_delete_(\d+)$/', $data, $m)) {
        deleteLotteryById((int)$m[1]);
        answerCallback($callbackId, 'قرعه‌کشی حذف شد.');
        return;
    }

    if ($data === 'admin_pick_winner') {
        $res = pickLatestLotteryWinners((int)$user['id']);
        editMessageText($chatId, $messageId, $res['message'], adminLotteriesMenu());
        answerCallback($callbackId, $res['ok'] ? 'انجام شد.' : $res['message'], !$res['ok']);
        return;
    }

    answerCallback($callbackId);
}

/* =========================================================
   ENTRY
   ========================================================= */
boot_storage();

try {
    $raw = file_get_contents('php://input');
    $update = json_decode((string)$raw, true);

    if (!is_array($update)) {
        http_response_code(200);
        exit('OK');
    }

    if (isset($update['message'])) {
        handleMessage($update['message']);
    } elseif (isset($update['callback_query'])) {
        handleCallback($update['callback_query']);
    }

    http_response_code(200);
    echo 'OK';
} catch (Throwable $e) {
    log_debug('fatal: ' . $e->getMessage());
    http_response_code(200);
    echo 'OK';
}