<?php
namespace Controllers;

use Config\Database;
use PDO;

class LicenseController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // Public Query
    public function query() {
        if (!isset($_GET['qq']) || !isset($_GET['owner'])) {
            http_response_code(400);
            echo json_encode(["message" => "Missing parameters"]);
            return;
        }

        $qq = $_GET['qq'];
        $owner = $_GET['owner'];

        $query = "SELECT * FROM licenses WHERE qq = :qq AND owner_name = :owner LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":qq", $qq);
        $stmt->bindParam(":owner", $owner);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            // Check if expired logic? The prompt says just show info.
            // But prompt also lists reasons for failure: "1.授权开通不足60分钟内" (implies < 60 mins from creation?) - this is weird, maybe it means 'just created'? or 'not synced'?
            // Usually "Authorization not found" reasons are generic boilerplate.
            // Let's just return the data.
            
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "data" => [
                    "qq" => $row['qq'],
                    "owner" => $row['owner_name'],
                    "product" => $row['product_name'],
                    "upline" => $row['upline'],
                    "expiration" => $row['expiration_date'],
                    "created_at" => $row['created_at']
                ]
            ]);
        } else {
            // Failure with specific message
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "暂未查询到您的授权信息 请查证后再次查询！",
                "reasons" => [
                    "1.授权开通不足60分钟内",
                    "2.未购买正版授权，可能是盗版程序授权",
                    "3.恭喜你，被圈钱了！"
                ]
            ]);
        }
    }

    // Admin: List All
    public function listAll() {
        $query = "SELECT * FROM licenses ORDER BY created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
    }

    // Admin: Create
    public function create() {
        $data = json_decode(file_get_contents("php://input"));
        // Need: qq, owner_name, product_name, upline, expiration_date
        $query = "INSERT INTO licenses (qq, owner_name, product_name, upline, expiration_date) VALUES (:qq, :owner, :product, :upline, :exp)";
        $stmt = $this->db->prepare($query);
        
        $params = [
            ":qq" => $data->qq,
            ":owner" => $data->owner_name,
            ":product" => $data->product_name,
            ":upline" => $data->upline,
            ":exp" => $data->expiration_date
        ];
        
        if($stmt->execute($params)) {
             echo json_encode(["message" => "Created successfully"]);
        } else {
             http_response_code(500);
             echo json_encode(["message" => "Create failed"]);
        }
    }
    
    // Admin: Delete
    public function delete() {
         $data = json_decode(file_get_contents("php://input"));
         if(!isset($data->id)) { return; }
         $query = "DELETE FROM licenses WHERE id = :id";
         $stmt = $this->db->prepare($query);
         $stmt->bindParam(":id", $data->id);
         $stmt->execute();
         echo json_encode(["message" => "Deleted"]);
    }

    // Admin: Import Preview (parse uploaded CSV, validate, detect duplicates)
    public function importPreview() {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(["message" => "请上传 CSV 文件"]);
            return;
        }

        $originalName = $_FILES['file']['name'] ?? '';
        if (!preg_match('/\.csv$/i', $originalName) && $_FILES['file']['type'] !== 'text/csv') {
            http_response_code(400);
            echo json_encode(["message" => "仅支持 .csv 格式文件"]);
            return;
        }

        $raw = file_get_contents($_FILES['file']['tmp_name']);
        // Strip UTF-8 BOM so the first header matches
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $lines = array_filter($lines, fn($l) => trim($l) !== '');
        if (count($lines) < 2) {
            http_response_code(400);
            echo json_encode(["message" => "CSV 文件为空或没有数据行"]);
            return;
        }

        $headerLine = array_shift($lines);
        $headers = str_getcsv($headerLine);
        $headers = array_map('trim', $headers);

        $columnMap = $this->mapCsvColumns($headers);
        if ($columnMap === null) {
            http_response_code(400);
            echo json_encode(["message" => "无法识别表头，请确保包含：授权QQ、授权主人、产品、有效期 等列"]);
            return;
        }

        $rows = [];
        $index = 1;
        foreach ($lines as $line) {
            $index++;
            $data = str_getcsv($line);
            if (count($data) === 1 && trim($data[0]) === '') {
                continue;
            }
            $rows[] = $this->parseCsvRow($data, $columnMap, $index);
        }

        // Detect duplicates against existing licenses
        $qqs = array_unique(array_filter(array_column($rows, 'qq')));
        $existing = [];
        if (!empty($qqs)) {
            $placeholders = implode(',', array_fill(0, count($qqs), '?'));
            $stmt = $this->db->prepare("SELECT id, qq, owner_name, product_name, upline, expiration_date FROM licenses WHERE qq IN ($placeholders)");
            $stmt->execute(array_values($qqs));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $existing[$r['qq']] = $r;
            }
        }

        $validCount = 0;
        $invalidCount = 0;
        $dupCount = 0;
        foreach ($rows as &$row) {
            if (empty($row['errors'])) {
                $validCount++;
                if (isset($existing[$row['qq']])) {
                    $row['is_duplicate'] = true;
                    $row['existing'] = $existing[$row['qq']];
                    $dupCount++;
                }
            } else {
                $invalidCount++;
            }
        }
        unset($row);

        echo json_encode([
            "message" => "解析完成",
            "file_name" => $originalName,
            "headers" => array_values(array_filter($headers, fn($h) => $h !== '')),
            "total" => count($rows),
            "valid_count" => $validCount,
            "invalid_count" => $invalidCount,
            "duplicate_count" => $dupCount,
            "preview" => array_slice($rows, 0, 5),
            "rows" => $rows,
        ]);
    }

    // Admin: Import Confirm (write rows with duplicate mode: skip | overwrite)
    public function importConfirm() {
        $data = json_decode(file_get_contents("php://input"));
        $rows = $data->rows ?? [];
        $mode = ($data->duplicate_mode ?? 'skip') === 'overwrite' ? 'overwrite' : 'skip';

        if (!is_array($rows) || count($rows) === 0) {
            http_response_code(400);
            echo json_encode(["message" => "没有可导入的数据"]);
            return;
        }

        $checkStmt = $this->db->prepare("SELECT id FROM licenses WHERE qq = :qq LIMIT 1");
        $insertStmt = $this->db->prepare(
            "INSERT INTO licenses (qq, owner_name, product_name, upline, expiration_date)
             VALUES (:qq, :owner, :product, :upline, :exp)"
        );
        $updateStmt = $this->db->prepare(
            "UPDATE licenses SET owner_name = :owner, product_name = :product, upline = :upline, expiration_date = :exp
             WHERE qq = :qq"
        );

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $failures = [];

        foreach ($rows as $row) {
            $qq = trim($row->qq ?? '');
            $owner = trim($row->owner_name ?? '');
            $product = trim($row->product_name ?? '');
            $upline = trim($row->upline ?? '') ?: '官方';
            $expRaw = trim($row->expiration_date ?? ($row->expiration_raw ?? ''));

            $errors = [];
            if ($qq === '') {
                $errors[] = '缺少授权QQ';
            } elseif (mb_strlen($qq) > 20) {
                $errors[] = 'QQ长度超出限制';
            }
            if ($owner === '') {
                $errors[] = '缺少授权主人';
            }
            if ($product === '') {
                $errors[] = '缺少产品';
            }
            $exp = $this->parseCsvDate($expRaw);
            if ($exp === null) {
                $errors[] = '有效期缺失或格式不正确';
            }

            if (!empty($errors)) {
                $failed++;
                $failures[] = [
                    "row" => $row->row_index ?? null,
                    "qq" => $qq,
                    "errors" => $errors,
                ];
                continue;
            }

            $checkStmt->execute([':qq' => $qq]);
            $exists = $checkStmt->rowCount() > 0;

            if ($exists && $mode === 'skip') {
                $skipped++;
                continue;
            }

            $params = [
                ':qq' => $qq,
                ':owner' => $owner,
                ':product' => $product,
                ':upline' => $upline,
                ':exp' => $exp,
            ];

            if ($exists) {
                $updateStmt->execute($params);
                $updated++;
            } else {
                $insertStmt->execute($params);
                $inserted++;
            }
        }

        echo json_encode([
            "message" => "导入完成",
            "success_count" => $inserted + $updated,
            "inserted_count" => $inserted,
            "updated_count" => $updated,
            "skipped_count" => $skipped,
            "failed_count" => $failed,
            "failures" => $failures,
        ]);
    }

    // Map CSV header names (Chinese / English) to column indices
    private function mapCsvColumns($headers) {
        $patterns = [
            'qq'         => ['qq', 'qq号', 'qq号码', '授权qq', '授权qq号'],
            'owner'      => ['主人', '授权主人', 'owner', 'owner_name', 'ownername', '姓名', '名字', '持有人', '户主'],
            'product'    => ['产品', '产品名称', '产品名', '所属产品', '授权产品', 'product', 'product_name', 'productname'],
            'expiration' => ['有效期', '过期时间', '到期时间', '失效时间', '授权有效期', 'expiration', 'expiration_date', 'expirationdate', '到期', '过期日期', '到期日期', '授权到期时间'],
            'upline'     => ['上级', '上级代理', '授权上级', 'upline', '上线', '上级名称'],
        ];

        $map = ['qq' => null, 'owner' => null, 'product' => null, 'expiration' => null, 'upline' => null];

        foreach ($headers as $i => $col) {
            $key = mb_strtolower(trim($col));
            if ($key === '') {
                continue;
            }
            foreach ($patterns as $field => $pats) {
                if (in_array($key, $pats, true)) {
                    $map[$field] = $i;
                }
            }
        }

        // Required columns
        if ($map['qq'] === null || $map['owner'] === null || $map['product'] === null || $map['expiration'] === null) {
            return null;
        }
        return $map;
    }

    // Parse one CSV row into a structured license record with validation
    private function parseCsvRow($data, $columnMap, $index) {
        $qq = isset($columnMap['qq']) ? trim($data[$columnMap['qq']] ?? '') : '';
        $owner = isset($columnMap['owner']) ? trim($data[$columnMap['owner']] ?? '') : '';
        $product = isset($columnMap['product']) ? trim($data[$columnMap['product']] ?? '') : '';
        $expRaw = isset($columnMap['expiration']) ? trim($data[$columnMap['expiration']] ?? '') : '';
        $upline = isset($columnMap['upline']) ? trim($data[$columnMap['upline']] ?? '') : '';

        $errors = [];
        if ($qq === '') {
            $errors[] = '缺少授权QQ';
        } elseif (mb_strlen($qq) > 20) {
            $errors[] = 'QQ长度超出限制';
        }
        if ($owner === '') {
            $errors[] = '缺少授权主人';
        }
        if ($product === '') {
            $errors[] = '缺少产品';
        }
        if ($expRaw === '') {
            $errors[] = '缺少有效期';
        }

        $exp = $expRaw !== '' ? $this->parseCsvDate($expRaw) : null;
        if ($expRaw !== '' && $exp === null) {
            $errors[] = '有效期格式不正确';
        }

        return [
            'row_index' => $index,
            'qq' => $qq,
            'owner_name' => $owner,
            'product_name' => $product,
            'upline' => $upline !== '' ? $upline : '官方',
            'expiration_raw' => $expRaw,
            'expiration_date' => $exp,
            'errors' => $errors,
            'is_duplicate' => false,
            'existing' => null,
        ];
    }

    // Parse various date formats into MySQL datetime (Y-m-d H:i:s)
    private function parseCsvDate($raw) {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $formats = [
            'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d',
            'Y/m/d H:i:s', 'Y/m/d H:i', 'Y/m/d',
            'Y年m月d日 H:i:s', 'Y年m月d日 H:i', 'Y年m月d日',
            'Y.m.d H:i:s', 'Y.m.d H:i', 'Y.m.d',
        ];
        foreach ($formats as $f) {
            $dt = \DateTime::createFromFormat($f, $raw);
            if ($dt !== false) {
                return $dt->format('Y-m-d H:i:s');
            }
        }
        $ts = strtotime($raw);
        if ($ts !== false && $ts > 0) {
            return date('Y-m-d H:i:s', $ts);
        }
        return null;
    }

    // Update Flow: Step 1 - Send Code
    public function sendVerificationCode() {
        $data = json_decode(file_get_contents("php://input"));
        $qq = $data->qq;
        $email = $qq . "@qq.com";
        
        $code = rand(100000, 999999);
        
        // Save code
        $stmt = $this->db->prepare("INSERT INTO verification_codes (type, identifier, code, expires_at) VALUES ('update_license', :email, :code, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
        $stmt->execute([':email' => $email, ':code' => $code]);
        
        // Real Email Sending via PHPMailer
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            //Server settings
            $mail->SMTPDebug = 2; // Enable verbose debug output
            $mail->Debugoutput = 'error_log'; // Output to stderr
            $mail->isSMTP();
            $mail->Host       = 'smtp.163.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'yuwangifeng@163.com';
            $mail->Password   = 'LRZMA358wePVGa8F'; 
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            $mail->CharSet    = 'UTF-8';

            // Allow self-signed certs (matches Node.js permissive behavior)
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            //Recipients - Name removed to match Node example exactly
            $mail->setFrom('yuwangifeng@163.com');
            $mail->addAddress($email);
            
            // Set HELO to localhost to avoid Docker container ID rejection
            $mail->Hostname = 'localhost';

            //Content
            $mail->isHTML(true);
            $mail->Subject = '【授权系统】验证码';
            $mail->Body    = "您的验证码是 <b>$code</b>，请在10分钟内完成验证。<br>如非本人操作请忽略。";

            $mail->send();
            echo json_encode(["message" => "验证码已发送至QQ邮箱"]);
        } catch (\Exception $e) {
            // Fallback for demo/dev if SMTP fails
            error_log("SMTP Error: {$mail->ErrorInfo}");
            echo json_encode([
                 "message" => "邮件发送失败 (转为模拟模式)", 
                 "mock_code" => $code,
                 "debug_error" => $mail->ErrorInfo
            ]);
        }
    }

    // Update Flow: Step 2 - Verify & Update
    public function update() {
        $data = json_decode(file_get_contents("php://input"));
        // Expect: qq, code, new_owner, new_product...
        
        $email = $data->qq . "@qq.com";
        $code = $data->code;
        
        // Verify Code
        $stmt = $this->db->prepare("SELECT * FROM verification_codes WHERE identifier=:email AND code=:code AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->execute([':email' => $email, ':code' => $code]);
        
        if ($stmt->rowCount() == 0) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid or expired code"]);
            return;
        }
        
        // Update License
        // For demo, assume we update the owner name for this QQ
        $updateQ = "UPDATE licenses SET owner_name = :new_owner WHERE qq = :qq";
        $ustmt = $this->db->prepare($updateQ);
        $ustmt->execute([':new_owner' => $data->owner_name, ':qq' => $data->qq]); // assuming we update owner
        
        echo json_encode(["message" => "Update successful"]);
    }
}
