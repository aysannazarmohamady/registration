<?php

// بارگذاری تنظیمات از فایل .env
if (file_exists('.env')) {
    include '.env'; 
}

define('BOT_TOKEN', getenv('BOT_TOKEN'));
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('CHANNEL_ID', getenv('CHANNEL_ID'));
define('REVIEW_GROUP_ID', getenv('REVIEW_GROUP_ID'));
define('USERS_FILE', 'users.json');
define('STATES_FILE', 'states.json');
define('GROUP_LINK', getenv('GROUP_LINK'));

// توابع کار با فایل‌های JSON
function loadJSON($filename) {
    if (!file_exists($filename)) {
        file_put_contents($filename, json_encode([]));
        return [];
    }
    $content = file_get_contents($filename);
    $data = json_decode($content, true);
    return $data ?: [];
}

function saveJSON($filename, $data) {
    $result = file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // لاگ برای دیباگ
    file_put_contents('json_save_log.txt', 
        date('Y-m-d H:i:s') . ": Saving to {$filename}, result=" . ($result !== false ? 'success' : 'failed') . 
        ", data_size=" . strlen(json_encode($data)) . "\n", 
        FILE_APPEND
    );
    
    return $result !== false;
}

// توابع کار با کاربران
function getUserState($chat_id) {
    $states = loadJSON(STATES_FILE);
    return $states[$chat_id] ?? null;
}

function setUserState($chat_id, $state) {
    $states = loadJSON(STATES_FILE);
    if ($state === null) {
        unset($states[$chat_id]);
    } else {
        $states[$chat_id] = $state;
    }
    return saveJSON(STATES_FILE, $states);
}

function saveUserData($chat_id, $field, $value) {
    $users = loadJSON(USERS_FILE);
    
    // اگر کاربر وجود نداشت، ایجاد می‌کنیم
    if (!isset($users[$chat_id])) {
        $users[$chat_id] = [
            'chat_id' => $chat_id,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // به‌روزرسانی فیلد
    $users[$chat_id][$field] = $value;
    $users[$chat_id]['updated_at'] = date('Y-m-d H:i:s');
    
    $result = saveJSON(USERS_FILE, $users);
    
    // لاگ برای دیباگ
    file_put_contents('save_data_log.txt', 
        date('Y-m-d H:i:s') . ": Saved field={$field}, value={$value}, chat_id={$chat_id}, result=" . 
        ($result ? 'success' : 'failed') . "\n", 
        FILE_APPEND
    );
    
    return $result;
}

function getUserData($chat_id, $field = null) {
    $users = loadJSON(USERS_FILE);
    
    if (!isset($users[$chat_id])) {
        return null;
    }
    
    if ($field === null) {
        return $users[$chat_id];
    }
    
    return $users[$chat_id][$field] ?? null;
}

function saveVerificationData($chat_id, $type, $value, $ref_name = null) {
    $users = loadJSON(USERS_FILE);
    
    // اگر کاربر وجود نداشت، ایجاد می‌کنیم
    if (!isset($users[$chat_id])) {
        $users[$chat_id] = [
            'chat_id' => $chat_id,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // به‌روزرسانی اطلاعات احراز هویت
    $users[$chat_id]['verification_type'] = $type;
    $users[$chat_id]['verification_value'] = $value;
    $users[$chat_id]['verification_ref_name'] = $ref_name;
    $users[$chat_id]['updated_at'] = date('Y-m-d H:i:s');
    
    $result = saveJSON(USERS_FILE, $users);
    
    // لاگ برای دیباگ
    file_put_contents('verification_log.txt', 
        date('Y-m-d H:i:s') . ": Saved verification - chat_id={$chat_id}, type={$type}, value={$value}, ref_name={$ref_name}, result=" . 
        ($result ? 'success' : 'failed') . "\n", 
        FILE_APPEND
    );
    
    return $result;
}

// توابع ارتباط با API تلگرام
function makeHTTPRequest($method, $params = []) {
    $url = API_URL . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function sendMessage($chat_id, $text, $keyboard = null) {
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($keyboard !== null) {
        $params['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
    }
    
    return makeHTTPRequest('sendMessage', $params);
}

function editMessageText($chat_id, $message_id, $text, $keyboard = null) {
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($keyboard !== null) {
        $params['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
    }
    
    return makeHTTPRequest('editMessageText', $params);
}

function checkChannelMembership($chat_id, $user_id) {
    $result = makeHTTPRequest('getChatMember', [
        'chat_id' => CHANNEL_ID,
        'user_id' => $user_id
    ]);
    
    return isset($result['result']['status']) && 
           in_array($result['result']['status'], ['member', 'administrator', 'creator']);
}

function checkReviewGroupMembership($user_id) {
    $result = makeHTTPRequest('getChatMember', [
        'chat_id' => REVIEW_GROUP_ID,
        'user_id' => $user_id
    ]);
    
    return isset($result['result']['status']) && 
           in_array($result['result']['status'], ['member', 'administrator', 'creator']);
}

function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function isValidLinkedInUrl($url) {
    return isValidUrl($url) && strpos($url, 'linkedin.com') !== false;
}

function showUserProfile($chat_id) {
    $userData = getUserData($chat_id);
    
    if (!$userData) {
        sendMessage($chat_id, "اطلاعاتی برای شما ثبت نشده است. لطفاً با استفاده از دستور /start ثبت‌نام کنید.");
        return;
    }
    
    $profileText = "📖 <b>پروفایل شما</b>\n\n" .
                   "👤 <b>نام و نام خانوادگی:</b> " . ($userData['name'] ?? 'ثبت نشده') . "\n" .
                   "🏢 <b>شرکت:</b> " . ($userData['company'] ?? 'ثبت نشده') . "\n" .
                   "💼 <b>تخصص:</b> " . ($userData['expertise'] ?? 'ثبت نشده') . "\n" .
                   "📧 <b>ایمیل:</b> " . ($userData['email'] ?? 'ثبت نشده') . "\n\n" .
                   "📋 <b>انگیزه‌نامه:</b>\n" . ($userData['motivation'] ?? 'ثبت نشده') . "\n\n";

    if (isset($userData['verification_type'])) {
        if ($userData['verification_type'] === 'linkedin') {
            $profileText .= "🔗 <b>لینک LinkedIn:</b>\n" . ($userData['verification_value'] ?? '') . "\n";
        } elseif ($userData['verification_type'] === 'resume') {
            $profileText .= "📄 <b>لینک رزومه:</b>\n" . ($userData['verification_value'] ?? '') . "\n";
        } elseif ($userData['verification_type'] === 'referral') {
            $profileText .= "👥 <b>معرف:</b>\n" . ($userData['verification_ref_name'] ?? '') . " (" . ($userData['verification_value'] ?? '') . ")\n";
        }
    }
    
    $status = $userData['status'] ?? 'در انتظار بررسی';
    $profileText .= "\n🔍 <b>وضعیت درخواست:</b> {$status}";
    
    if ($status === 'رد شده' && isset($userData['rejection_reason']) && $userData['rejection_reason']) {
        $profileText .= "\n<b>دلیل رد درخواست:</b> {$userData['rejection_reason']}";
        
        if (isset($userData['reviewed_by_username']) && $userData['reviewed_by_username']) {
            $profileText .= "\n<b>بررسی شده توسط:</b> @{$userData['reviewed_by_username']}";
        }
    } elseif ($status === 'تایید شده' && isset($userData['reviewed_by_username']) && $userData['reviewed_by_username']) {
        $profileText .= "\n<b>تایید شده توسط:</b> @{$userData['reviewed_by_username']}";
        if (isset($userData['rejection_reason']) && $userData['rejection_reason']) {
            $profileText .= "\n<b>دلیل تایید:</b> {$userData['rejection_reason']}";
        }
    }
    
    $keyboard = [
        [['text' => 'ویرایش پروفایل', 'callback_data' => 'edit_profile']],
        [['text' => 'ارسال مجدد برای بررسی', 'callback_data' => 'resubmit_profile']]
    ];
    
    sendMessage($chat_id, $profileText, $keyboard);
}

function finalizeRegistration($chat_id) {
    setUserState($chat_id, 'COMPLETED');
    saveUserData($chat_id, 'status', 'در انتظار بررسی');
    
    $userData = getUserData($chat_id);
    
    $groupMessage = "🔍 درخواست عضویت جدید:\n\n" .
                   "👤 نام: " . ($userData['name'] ?? '') . "\n" .
                   "🏢 شرکت: " . ($userData['company'] ?? '') . "\n" .
                   "💼 تخصص: " . ($userData['expertise'] ?? '') . "\n" .
                   "📧 ایمیل: " . ($userData['email'] ?? '') . "\n\n" .
                   "📋 انگیزه‌نامه:\n" . ($userData['motivation'] ?? '') . "\n\n";

    if (isset($userData['verification_type'])) {
        if ($userData['verification_type'] === 'linkedin') {
            $groupMessage .= "🔗 لینک LinkedIn:\n" . ($userData['verification_value'] ?? '') . "\n";
        } elseif ($userData['verification_type'] === 'resume') {
            $groupMessage .= "📄 لینک رزومه:\n" . ($userData['verification_value'] ?? '') . "\n";
        } elseif ($userData['verification_type'] === 'referral') {
            $groupMessage .= "👥 معرف:\n" . ($userData['verification_ref_name'] ?? '') . " (" . ($userData['verification_value'] ?? '') . ")\n";
        }
    }
    
    $keyboard = [
        [
            ['text' => '✅ تایید درخواست', 'callback_data' => 'approve_' . $chat_id],
            ['text' => '❌ رد درخواست', 'callback_data' => 'reject_' . $chat_id]
        ]
    ];
    
    $groupResult = sendMessage(REVIEW_GROUP_ID, $groupMessage, $keyboard);
    
    file_put_contents('group_message_log.txt', date('Y-m-d H:i:s') . ': ' . print_r($groupResult, true) . "\n", FILE_APPEND);
    
    sendMessage($chat_id, 
        "✅ اطلاعات شما با موفقیت ثبت شد.\n\n" .
        "درخواست شما توسط تیم PC بررسی خواهد شد و پس از تأیید، لینک گروه برای شما ارسال می‌شود.\n\n" .
        "با تشکر از عضویت شما در IRNOG 🌟");
        
    showUserProfile($chat_id);
}

function handleApplicationResponse($action, $user_id, $reviewer_chat_id, $message_id = null, $reason = null) {
    file_put_contents('debug_actions_log.txt', date('Y-m-d H:i:s') . ': action=' . $action . ', user_id=' . $user_id . ', reviewer=' . $reviewer_chat_id . "\n", FILE_APPEND);
    
    $isApproved = ($action === 'approve');
    $userData = getUserData($user_id);
    
    if (!$userData) {
        sendMessage($reviewer_chat_id, "❌ خطا: اطلاعات کاربر یافت نشد.");
        return;
    }
    
    $reviewerInfo = makeHTTPRequest('getChat', ['chat_id' => $reviewer_chat_id]);
    $reviewerUsername = '';
    
    if (isset($reviewerInfo['result']['username'])) {
        $reviewerUsername = $reviewerInfo['result']['username'];
    } elseif (isset($reviewerInfo['result']['first_name'])) {
        $reviewerUsername = $reviewerInfo['result']['first_name'];
    }
    
    $name = $userData['name'] ?? 'نامشخص';
    
    if ($isApproved) {
        saveUserData($user_id, 'status', 'تایید شده');
        saveUserData($user_id, 'rejection_reason', $reason);
        saveUserData($user_id, 'reviewed_by_user_id', $reviewer_chat_id);
        saveUserData($user_id, 'reviewed_by_username', $reviewerUsername);
        saveUserData($user_id, 'review_decision', 'approved');
        
        $userMessage = "🎉 <b>تبریک!</b>\n\n" .
                      "درخواست عضویت شما در IRNOG تایید شد.\n\n";
        
        if ($reason && trim($reason) !== '' && strtolower(trim($reason)) !== 'تایید') {
            $userMessage .= "<b>دلیل تایید:</b> {$reason}\n\n";
        }
        
        $userMessage .= "برای ورود به گروه اصلی می‌توانید از لینک زیر استفاده کنید:\n" . GROUP_LINK;
        
        $keyboardUser = [[['text' => 'ورود به گروه', 'url' => GROUP_LINK]]];
        sendMessage($user_id, $userMessage, $keyboardUser);
        
        sendMessage($reviewer_chat_id, "✅ درخواست {$name} با موفقیت تایید شد و لینک گروه برای ایشان ارسال گردید.");
        
        $reportMessage = "✅ <b>گزارش تایید درخواست</b>\n\n" .
                        "👤 <b>متقاضی:</b> {$name}\n" .
                        "👨‍💼 <b>تایید شده توسط:</b> @{$reviewerUsername}\n";
        
        if ($reason && trim($reason) !== '' && strtolower(trim($reason)) !== 'تایید') {
            $reportMessage .= "📝 <b>دلیل تایید:</b> {$reason}\n";
        }
        
        $reportMessage .= "\n🔗 لینک گروه برای متقاضی ارسال شد.";
        
        sendMessage(REVIEW_GROUP_ID, $reportMessage);
        
    } else {
        saveUserData($user_id, 'status', 'رد شده');
        saveUserData($user_id, 'reviewed_by_user_id', $reviewer_chat_id);
        saveUserData($user_id, 'reviewed_by_username', $reviewerUsername);
        saveUserData($user_id, 'review_decision', 'rejected');
        
        if ($reason) {
            saveUserData($user_id, 'rejection_reason', $reason);
        }
        
        $userMessage = "❌ <b>اطلاعیه</b>\n\n" .
                      "متأسفانه درخواست عضویت شما در IRNOG در این مرحله تایید نشد.";
        
        if ($reason) {
            $userMessage .= "\n\n<b>دلیل:</b> {$reason}";
        }
        
        $userMessage .= "\n\nشما می‌توانید پس از تکمیل اطلاعات خود، مجدداً درخواست خود را ارسال نمایید.";
        
        sendMessage($user_id, $userMessage);
        
        sendMessage($reviewer_chat_id, "❌ درخواست {$name} با موفقیت رد شد و به کاربر اطلاع داده شد.");
        
        $reportMessage = "❌ <b>گزارش رد درخواست</b>\n\n" .
                        "👤 <b>متقاضی:</b> {$name}\n" .
                        "👨‍💼 <b>رد شده توسط:</b> @{$reviewerUsername}\n";
        
        if ($reason) {
            $reportMessage .= "📝 <b>دلیل رد:</b> {$reason}\n";
        }
        
        $reportMessage .= "\n📢 متقاضی می‌تواند پس از اصلاح مجدداً درخواست دهد.";
        
        sendMessage(REVIEW_GROUP_ID, $reportMessage);
    }
    
    $logEntry = date('Y-m-d H:i:s') . ': درخواست ' . $user_id . ' (' . $name . ') ' . 
               ($isApproved ? 'تایید' : 'رد') . ' شده توسط ' . $reviewerUsername;
    
    if ($reason) {
        $logEntry .= " با دلیل: " . $reason;
    }
    
    $logEntry .= "\n";
    file_put_contents('group_review_actions_log.txt', $logEntry, FILE_APPEND);
    
    return true;
}

// دریافت و پردازش پیام‌های ورودی
$update = json_decode(file_get_contents('php://input'), true);

file_put_contents('request_log.txt', date('Y-m-d H:i:s') . ': ' . print_r($update, true) . "\n", FILE_APPEND);

if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $user_id = $message['from']['id'];
    
    $existingUser = getUserData($chat_id);
    $user_state = getUserState($chat_id);
    
    if (preg_match('/^AWAIT_(APPROVE|REJECT)_REASON_(.+)$/', $user_state, $matches)) {
        $action = strtolower($matches[1]);
        $applicant_user_id = $matches[2];
        handleApplicationResponse($action, $applicant_user_id, $chat_id, null, $text);
        setUserState($chat_id, null);
        return;
    }
    
    switch ($text) {
        case '/start':
            if (!checkChannelMembership($chat_id, $user_id)) {
                $keyboard = [[['text' => 'عضویت در کانال', 'url' => 't.me/irnog']]];
                sendMessage($chat_id, 
                    "سلام 👋\n" .
                    "به ربات احراز هویت و درخواست عضویت IRNOG خوش‌آمدید! 🌟\n\n" .
                    "گروه گردانندگان شبکۀ اینترنت ایران (Iranian Internet Network Operators Group)، یک اجتماع فنی، غیرانتفاعی و مستقل، از فعالان حوزۀ زیرساخت، شبکه و اینترنت در ایران است که با هدف ارتقاء دانش فنی، تسهیل ارتباطات بین‌اپراتوری، و ترویج فرهنگ همکاری و اشتراک‌گذاری دانش شکل گرفته است.\n\n" .
                    "در همین راستا، این گروه با گرد هم آوردن مجموعه‌ای از متخصصان و مهندسان شبکه و زیرساخت، ارائه‌دهندگان خدمات اینترنت و مراکز داده و سایر فعالان فنی تلاش می‌کند بستری حرفه‌ای برای تبادل تجربیات، اشتراک‌گذاری دانش و بررسی چالش‌های فنی و زیرساختی فراهم آورد.\n\n" .
                    "⚠️ پیش از ادامۀ فرآیند ثبت‌نام، لطفاً در کانال تلگرام IRNOG عضو شوید و پس از عضویت، مجدداً بات را استارت کنید.", $keyboard);
                return;
            }
            
            if ($existingUser && isset($existingUser['name']) && $existingUser['name']) {
                $name = $existingUser['name'];
                sendMessage($chat_id, "سلام {$name} عزیز 👋\n\nخوش‌آمدید. از منوی زیر می‌توانید گزینه مورد نظر خود را انتخاب کنید:", [
                    [['text' => 'مشاهده پروفایل', 'callback_data' => 'view_profile']],
                    [['text' => 'ویرایش پروفایل', 'callback_data' => 'edit_profile']],
                    [['text' => 'ارسال مجدد برای بررسی', 'callback_data' => 'resubmit_profile']]
                ]);
                return;
            }
            
            setUserState($chat_id, 'AWAIT_NAME');
            sendMessage($chat_id, "لطفاً نام و نام خانوادگی خود را وارد کنید:");
            break;
            
        case '/profile':
            showUserProfile($chat_id);
            break;
            
        default:
            switch ($user_state) {
                case 'AWAIT_NAME':
                    saveUserData($chat_id, 'name', $text);
                    setUserState($chat_id, 'AWAIT_COMPANY_INPUT');
                    sendMessage($chat_id, " لطفاً نام شرکت خود را وارد کنید یا در صورتی که به صورت فریلنسری فعالیت می‌کنید، عنوان «فریلنسر» را وارد نمایید:");
                    break;
                    
                case 'AWAIT_COMPANY_INPUT':
                    saveUserData($chat_id, 'company', $text);
                    setUserState($chat_id, 'AWAIT_EXPERTISE');
                    sendMessage($chat_id, "لطفاً حوزه تخصصی خود را وارد کنید:");
                    break;
                    
                case 'AWAIT_EXPERTISE':
                    saveUserData($chat_id, 'expertise', $text);
                    setUserState($chat_id, 'AWAIT_EMAIL');
                    sendMessage($chat_id, "لطفاً ایمیل سازمانی خود را وارد کنید:");
                    break;
                    
                case 'AWAIT_EMAIL':
                    saveUserData($chat_id, 'email', $text);
                    setUserState($chat_id, 'AWAIT_MOTIVATION');
                    sendMessage($chat_id, 
                        "هدف شما از عضویت در کامیونیتی ایرناگ چیست؟\n\n" );
                    break;
                    
                case 'AWAIT_MOTIVATION':
                    saveUserData($chat_id, 'motivation', $text);
                    setUserState($chat_id, 'AWAIT_VERIFICATION');
                    $keyboard = [
                        [['text' => 'LinkedIn پروفایل', 'callback_data' => 'verify_linkedin']],
                        [['text' => 'آپلود رزومه', 'callback_data' => 'verify_resume']],
                        [['text' => 'معرفی توسط اعضای تیم پی سی', 'callback_data' => 'verify_member']]
                    ];
                    sendMessage($chat_id, "لطفاً روش احراز هویت را انتخاب کنید:", $keyboard);
                    break;
                
                case 'AWAIT_LINKEDIN':
                    if (!isValidLinkedInUrl($text)) {
                        sendMessage($chat_id, "❌ لینک وارد شده معتبر نیست. لطفاً یک لینک LinkedIn معتبر وارد کنید:");
                        return;
                    }
                    
                    saveVerificationData($chat_id, 'linkedin', $text);
                    finalizeRegistration($chat_id);
                    break;
                    
                case 'AWAIT_RESUME':
                    if (!isValidUrl($text)) {
                        sendMessage($chat_id, "❌ لینک وارد شده معتبر نیست. لطفاً یک لینک معتبر برای رزومه خود وارد کنید:");
                        return;
                    }
                    
                    saveVerificationData($chat_id, 'resume', $text);
                    finalizeRegistration($chat_id);
                    break;
                    
                case 'AWAIT_REFERRAL_NAME':
                    saveUserData($chat_id, 'verification_ref_name', $text);
                    setUserState($chat_id, 'AWAIT_REFERRAL_ID');
                    sendMessage($chat_id, "لطفاً آیدی تلگرام یا شماره تماس عضو معرف را وارد کنید:");
                    break;
                
                case 'AWAIT_REFERRAL_ID':
                    $refName = getUserData($chat_id, 'verification_ref_name');
                    saveVerificationData($chat_id, 'referral', $text, $refName);
                    finalizeRegistration($chat_id);
                    break;
                    
                case 'EDIT_NAME':
                    saveUserData($chat_id, 'name', $text);
                    setUserState($chat_id, 'PROFILE_EDIT');
                    showUserProfile($chat_id);
                    break;
                    
                case 'EDIT_COMPANY':
                    saveUserData($chat_id, 'company', $text);
                    setUserState($chat_id, 'PROFILE_EDIT');
                    showUserProfile($chat_id);
                    break;
                    
                case 'EDIT_EXPERTISE':
                    saveUserData($chat_id, 'expertise', $text);
                    setUserState($chat_id, 'PROFILE_EDIT');
                    showUserProfile($chat_id);
                    break;
                    
                case 'EDIT_EMAIL':
                    saveUserData($chat_id, 'email', $text);
                    setUserState($chat_id, 'PROFILE_EDIT');
                    showUserProfile($chat_id);
                    break;
                    
                case 'EDIT_MOTIVATION':
                    saveUserData($chat_id, 'motivation', $text);
                    setUserState($chat_id, 'PROFILE_EDIT');
                    showUserProfile($chat_id);
                    break;
            }
    }
}

if (isset($update['callback_query'])) {
    $callback_query = $update['callback_query'];
    $chat_id = $callback_query['message']['chat']['id'];
    $data = $callback_query['data'];
    $message_id = $callback_query['message']['message_id'];
    $from_user = $callback_query['from'];
    $from_user_id = $from_user['id'];
    $from_username = $from_user['username'] ?? $from_user['first_name'];
    
    if (strpos($data, 'approve_') === 0) {
        $user_id = substr($data, 8);
        
        if (!checkReviewGroupMembership($from_user_id)) {
            sendMessage($from_user_id, "❌ شما مجاز به انجام این عمل نیستید.");
            return;
        }
        
        setUserState($from_user_id, 'AWAIT_APPROVE_REASON_' . $user_id);
        sendMessage($from_user_id, "لطفاً دلیل تایید درخواست را وارد کنید (اختیاری - می‌توانید فقط 'تایید' بنویسید):");
        
        $userData = getUserData($user_id);
        $updatedMessage = "🔄 <b>در حال بررسی توسط @{$from_username}</b>\n\n" .
                         "👤 نام: " . ($userData['name'] ?? '') . "\n" .
                         "🏢 شرکت: " . ($userData['company'] ?? '') . "\n" .
                         "💼 تخصص: " . ($userData['expertise'] ?? '') . "\n" .
                         "📧 ایمیل: " . ($userData['email'] ?? '') . "\n\n" .
                         "📋 انگیزه‌نامه:\n" . ($userData['motivation'] ?? '') . "\n\n";
        
        if (isset($userData['verification_type'])) {
            if ($userData['verification_type'] === 'linkedin') {
                $updatedMessage .= "🔗 لینک LinkedIn:\n" . ($userData['verification_value'] ?? '') . "\n";
            } elseif ($userData['verification_type'] === 'resume') {
                $updatedMessage .= "📄 لینک رزومه:\n" . ($userData['verification_value'] ?? '') . "\n";
            } elseif ($userData['verification_type'] === 'referral') {
                $updatedMessage .= "👥 معرف:\n" . ($userData['verification_ref_name'] ?? '') . " (" . ($userData['verification_value'] ?? '') . ")\n";
            }
        }
        
        editMessageText($chat_id, $message_id, $updatedMessage);
        return;
        
    } elseif (strpos($data, 'reject_') === 0 && strpos($data, 'reject_reason_') !== 0) {
        $user_id = substr($data, 7);
        
        if (!checkReviewGroupMembership($from_user_id)) {
            sendMessage($from_user_id, "❌ شما مجاز به انجام این عمل نیستید.");
            return;
        }
        
        setUserState($from_user_id, 'AWAIT_REJECT_REASON_' . $user_id);
        sendMessage($from_user_id, "لطفاً دلیل رد درخواست کاربر را وارد کنید:");
        
        $userData = getUserData($user_id);
        $updatedMessage = "🔄 <b>در حال بررسی توسط @{$from_username}</b>\n\n" .
                         "👤 نام: " . ($userData['name'] ?? '') . "\n" .
                         "🏢 شرکت: " . ($userData['company'] ?? '') . "\n" .
                         "💼 تخصص: " . ($userData['expertise'] ?? '') . "\n" .
                         "📧 ایمیل: " . ($userData['email'] ?? '') . "\n\n" .
                         "📋 انگیزه‌نامه:\n" . ($userData['motivation'] ?? '') . "\n\n";
        
        if (isset($userData['verification_type'])) {
            if ($userData['verification_type'] === 'linkedin') {
                $updatedMessage .= "🔗 لینک LinkedIn:\n" . ($userData['verification_value'] ?? '') . "\n";
            } elseif ($userData['verification_type'] === 'resume') {
                $updatedMessage .= "📄 لینک رزومه:\n" . ($userData['verification_value'] ?? '') . "\n";
            } elseif ($userData['verification_type'] === 'referral') {
                $updatedMessage .= "👥 معرف:\n" . ($userData['verification_ref_name'] ?? '') . " (" . ($userData['verification_value'] ?? '') . ")\n";
            }
        }
        
        editMessageText($chat_id, $message_id, $updatedMessage);
        return;
    }
    
    switch ($data) {
        case 'verify_linkedin':
            setUserState($chat_id, 'AWAIT_LINKEDIN');
            sendMessage($chat_id, "لطفاً لینک پروفایل LinkedIn خود را ارسال کنید (فرمت لینک باید معتبر باشد):");
            break;
            
        case 'verify_resume':
            setUserState($chat_id, 'AWAIT_RESUME');
            sendMessage($chat_id, "لطفاً لینک رزومه خود را ارسال کنید (فرمت لینک باید معتبر باشد):");
            break;
            
        case 'verify_member':
            setUserState($chat_id, 'AWAIT_REFERRAL_NAME');
            sendMessage($chat_id, "لطفاً نام و نام خانوادگی عضو معرف را وارد کنید:");
            break;
            
        case 'view_profile':
            showUserProfile($chat_id);
            break;
            
        case 'edit_profile':
            $keyboard = [
                [['text' => 'نام و نام خانوادگی', 'callback_data' => 'edit_name']],
                [['text' => 'شرکت', 'callback_data' => 'edit_company']],
                [['text' => 'تخصص', 'callback_data' => 'edit_expertise']],
                [['text' => 'ایمیل', 'callback_data' => 'edit_email']],
                [['text' => 'انگیزه‌نامه', 'callback_data' => 'edit_motivation']],
                [['text' => 'روش احراز هویت', 'callback_data' => 'edit_verification']],
                [['text' => 'بازگشت', 'callback_data' => 'view_profile']]
            ];
            
            sendMessage($chat_id, "لطفاً فیلدی که می‌خواهید ویرایش کنید را انتخاب کنید:", $keyboard);
            break;
            
        case 'edit_name':
            setUserState($chat_id, 'EDIT_NAME');
            sendMessage($chat_id, "لطفاً نام و نام خانوادگی جدید خود را وارد کنید:");
            break;
            
        case 'edit_company':
            setUserState($chat_id, 'EDIT_COMPANY');
            sendMessage($chat_id, "لطفاً نام شرکت جدید خود را وارد کنید:");
            break;
            
        case 'edit_expertise':
            setUserState($chat_id, 'EDIT_EXPERTISE');
            sendMessage($chat_id, "لطفاً حوزه تخصصی جدید خود را وارد کنید:");
            break;
            
        case 'edit_email':
            setUserState($chat_id, 'EDIT_EMAIL');
            sendMessage($chat_id, "لطفاً ایمیل جدید خود را وارد کنید:");
            break;
            
        case 'edit_motivation':
            setUserState($chat_id, 'EDIT_MOTIVATION');
            sendMessage($chat_id, 
                "لطفاً انگیزه‌نامه جدید خود را با پاسخ به سوالات زیر بنویسید:\n\n" .
                "1. چه تجربیاتی در زمینه مدیریت و توسعه شبکه‌های اینترنتی دارید؟\n" .
                "2. چگونه می‌توانید به بهبود شرایط استفاده از اینترنت در ایران کمک کنید؟\n" .
                "3. دیدگاه شما درباره چالش‌های فعلی اینترنت ایران و راهکارهای پیشنهادی چیست؟\n" .
                "4. چگونه می‌توانید در فعالیت‌های مشورتی و راهبردی IRNOG مشارکت کنید?");
            break;
            
        case 'edit_verification':
            setUserState($chat_id, 'AWAIT_VERIFICATION');
            $keyboard = [
                [['text' => 'LinkedIn پروفایل', 'callback_data' => 'verify_linkedin']],
                [['text' => 'آپلود رزومه', 'callback_data' => 'verify_resume']],
                [['text' => 'معرفی توسط اعضای تیم پی سی', 'callback_data' => 'verify_member']]
            ];
            sendMessage($chat_id, "لطفاً روش احراز هویت جدید را انتخاب کنید:", $keyboard);
            break;
            
        case 'resubmit_profile':
            $userData = getUserData($chat_id);
            
            if (!$userData) {
                sendMessage($chat_id, "اطلاعاتی برای ارسال مجدد وجود ندارد. لطفاً ابتدا ثبت‌نام کنید.");
                return;
            }
            
            saveUserData($chat_id, 'status', 'در انتظار بررسی');
            saveUserData($chat_id, 'rejection_reason', null);
            saveUserData($chat_id, 'reviewed_by_user_id', null);
            saveUserData($chat_id, 'reviewed_by_username', null);
            saveUserData($chat_id, 'review_decision', null);
            
            $groupMessage = "🔍 درخواست عضویت مجدد:\n\n" .
                           "👤 نام: " . ($userData['name'] ?? '') . "\n" .
                           "🏢 شرکت: " . ($userData['company'] ?? '') . "\n" .
                           "💼 تخصص: " . ($userData['expertise'] ?? '') . "\n" .
                           "📧 ایمیل: " . ($userData['email'] ?? '') . "\n\n" .
                           "📋 انگیزه‌نامه:\n" . ($userData['motivation'] ?? '') . "\n\n";

            if (isset($userData['verification_type'])) {
                if ($userData['verification_type'] === 'linkedin') {
                    $groupMessage .= "🔗 لینک LinkedIn:\n" . ($userData['verification_value'] ?? '') . "\n";
                } elseif ($userData['verification_type'] === 'resume') {
                    $groupMessage .= "📄 لینک رزومه:\n" . ($userData['verification_value'] ?? '') . "\n";
                } elseif ($userData['verification_type'] === 'referral') {
                    $groupMessage .= "👥 معرف:\n" . ($userData['verification_ref_name'] ?? '') . " (" . ($userData['verification_value'] ?? '') . ")\n";
                }
            }
            
            $keyboard = [
                [
                    ['text' => '✅ تایید درخواست', 'callback_data' => 'approve_' . $chat_id],
                    ['text' => '❌ رد درخواست', 'callback_data' => 'reject_' . $chat_id]
                ]
            ];
            
            sendMessage(REVIEW_GROUP_ID, $groupMessage, $keyboard);
            
            sendMessage($chat_id, "✅ درخواست شما مجددا برای بررسی ارسال شد. نتیجه بررسی به شما اطلاع داده خواهد شد.");
            break;
    }
}

?>
