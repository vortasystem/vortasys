<?php
// تفعيل عرض الأخطاء للمساعدة في التصحيح (يمكنك حذفها في الإنتاج)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// تضمين ملف الإعدادات الذي يحتوي على الرابط السري
require_once 'config.php';

// السماح للطلبات من أي مصدر (CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// إذا كان الطلب من نوع OPTIONS، ننهي التنفيذ هنا (هذا جزء من بروتوكول CORS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// ضبط نوع المحتوى للردود ليكون دائمًا JSON
header('Content-Type: application/json');

// --- بناء عنوان URL المستهدف لـ SheetDB ---
$sheet = $_GET['sheet'] ?? '';
if (!$sheet) {
    http_response_code(400);
    echo json_encode(['error' => 'Sheet parameter is required']);
    exit;
}

// بناء المسار بناءً على الطلب
$path = '';
if (isset($_GET['id'])) {
    $path = '/id/' . urlencode($_GET['id']);
} elseif (isset($_GET['key'])) {
    $path = '/key/' . urlencode($_GET['key']);
}

$target_url = SHEETDB_API_URL . $path . '?sheet=' . urlencode($sheet);

// --- إعداد طلب cURL ---
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $target_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // إرجاع الرد كنص بدلاً من طباعته
curl_setopt($ch, CURLOPT_HEADER, false); // لا تقم بتضمين الهيدر في الرد

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        curl_setopt($ch, CURLOPT_POST, true);
        // أخذ جسم الطلب القادم من الواجهة الأمامية وتمريره كما هو
        $postData = file_get_contents('php://input');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        break;
    case 'PATCH':
    case 'DELETE':
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($method == 'PATCH') {
            $postData = file_get_contents('php://input');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        }
        break;
    case 'GET':
        // لا يوجد إعدادات إضافية لـ GET
        break;
    default:
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Method not allowed']);
        exit;
}

// تنفيذ الطلب إلى SheetDB
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// التحقق من وجود أخطاء في cURL
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(['error' => 'cURL Error: ' . curl_error($ch)]);
} else {
    // إرجاع نفس حالة HTTP ونفس الرد الذي تم تلقيه من SheetDB
    http_response_code($http_code);
    echo $response;
}

// إغلاق الاتصال
curl_close($ch);

?>
