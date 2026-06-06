<?php
// ジュアサリレーションズ 問い合わせフォーム送信ハンドラ
// さくら mail() 送信 + Cloudflare Turnstile(スパム対策) + ハニーポット
// Turnstileシークレットはリポジトリに含めず、サーバー上の公開外パスから読む。

mb_internal_encoding('UTF-8');
header('Content-Type: application/json; charset=utf-8');

// 宛先・差出人（機密ではない。juasarel@gmail.com はサイトにも公開済）
const MAIL_TO         = 'juasarel@gmail.com';
const MAIL_FROM_EMAIL = 'noreply@juasa-relations.jp';
const MAIL_FROM_NAME  = 'ジュアサリレーションズ';

// --- Turnstileシークレット読み込み（リポジトリ外・サーバー上のみ） ---
$configPath = '/home/kkkblog/secrets/juasa-config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_config_missing']);
    exit;
}
$cfg = require $configPath;

// --- POST以外は拒否 ---
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

// --- 入力取得 ---
$name    = trim($_POST['name']    ?? '');
$company = trim($_POST['company'] ?? '');
$email   = trim($_POST['email']   ?? '');
$tel     = trim($_POST['tel']     ?? '');
$topic   = trim($_POST['topic']   ?? '');
$message = trim($_POST['message'] ?? '');
$consent = $_POST['consent']      ?? '';
$website = trim($_POST['website'] ?? '');               // ハニーポット
$token   = $_POST['cf-turnstile-response'] ?? '';

// --- ハニーポット：入力があればボット。成功を装って静かに終了 ---
if ($website !== '') {
    echo json_encode(['ok' => true]);
    exit;
}

// --- 必須チェック ---
if ($name === '' || $email === '' || $topic === '' || $message === '' || $consent === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'missing_fields']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'invalid_email']);
    exit;
}
// メールヘッダインジェクション対策（改行混入を拒否）
foreach ([$name, $company, $email, $tel, $topic] as $v) {
    if (preg_match('/[\r\n]/', $v)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'invalid_input']);
        exit;
    }
}

// --- Turnstile 検証（サーバー側） ---
$verify = juasa_post(
    'https://challenges.cloudflare.com/turnstile/v0/siteverify',
    [
        'secret'   => $cfg['turnstile_secret'],
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]
);
if (empty($verify['success'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'turnstile_failed']);
    exit;
}

// --- メール明細（共通） ---
$lines = [
    '■ お名前：' . $name,
    '■ 会社名・団体名：' . ($company !== '' ? $company : '（未記入）'),
    '■ メールアドレス：' . $email,
    '■ 電話番号：' . ($tel !== '' ? $tel : '（未記入）'),
    '■ ご相談の種類：' . $topic,
    '',
    '■ ご相談内容：',
    $message,
];

// --- 通知メール（壽淺さん宛・返信先＝相談者） ---
$notifySubject = '【無料相談】' . ($company !== '' ? $company . ' ' : '') . $name . ' 様より';
$notifyBody    = "ホームページの相談フォームよりお問い合わせがありました。\n\n" . implode("\n", $lines) . "\n";
$sent = juasa_mail(MAIL_TO, $notifySubject, $notifyBody, $email);

// --- 自動返信（相談者宛） ---
$replySubject = '【ジュアサリレーションズ】お問い合わせを受け付けました';
$replyBody    = $name . " 様\n\n"
    . "この度はジュアサリレーションズへお問い合わせいただき、ありがとうございます。\n"
    . "以下の内容で受け付けいたしました。担当より折り返しご連絡いたします。\n\n"
    . "--------------------\n" . implode("\n", $lines) . "\n--------------------\n\n"
    . "※本メールは自動送信です。お心当たりがない場合は破棄してください。\n\n"
    . "ジュアサリレーションズ / JUASA RELATIONS\n"
    . "https://juasa-relations.jp\n";
juasa_mail($email, $replySubject, $replyBody);

if (!$sent) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'send_failed']);
    exit;
}

echo json_encode(['ok' => true]);

// ------------------------------------------------------------------
// UTF-8メール送信。$replyTo を渡すと Reply-To を設定。
function juasa_mail(string $to, string $subject, string $body, string $replyTo = ''): bool
{
    $fromHeader = mb_encode_mimeheader(MAIL_FROM_NAME, 'UTF-8', 'B')
        . ' <' . MAIL_FROM_EMAIL . '>';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . $fromHeader,
    ];
    if ($replyTo !== '') {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B');
    // 第5引数 -f で envelope-from を差出人ドメインに揃える（SPF整合）
    return mail($to, $encodedSubject, $body, implode("\r\n", $headers), '-f' . MAIL_FROM_EMAIL);
}

function juasa_post(string $url, array $data): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $decoded = json_decode((string) $resp, true);
    return is_array($decoded) ? $decoded : [];
}