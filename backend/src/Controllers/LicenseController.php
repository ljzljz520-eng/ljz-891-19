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

    // ================= CSV 批量导入 =================

    // Step 1: 上传 CSV 解析并预览（不落库）
    public function importPreview() {
        $parsed = $this->parseCsvUpload();
        if ($parsed === null) {
            http_response_code(400);
            echo json_encode(["message" => "请上传 CSV 文件"]);
            return;
        }
        if (isset($parsed['error'])) {
            http_response_code(400);
            echo json_encode(["message" => $parsed['error']]);
            return;
        }

        $rows = $parsed['rows'];
        if (empty($rows)) {
            http_response_code(400);
            echo json_encode(["message" => "CSV 中没有可导入的数据行"]);
            return;
        }

        // 标记数据库中已存在的重复记录（授权QQ + 产品）
        $this->markDuplicates($rows);

        $total = count($rows);
        $valid = 0; $invalid = 0; $dupCount = 0;
        foreach ($rows as $r) {
            if (!empty($r['errors'])) { $invalid++; } else { $valid++; }
            if ($r['duplicate']) { $dupCount++; }
        }

        echo json_encode([
            "status" => "success",
            "total" => $total,
            "valid" => $valid,
            "invalid" => $invalid,
            "duplicates" => $dupCount,
            "preview" => array_slice($rows, 0, 5), // 只回传前 5 行用于预览
            "rows" => $rows                         // 完整解析结果供确认时直接写回
        ]);
    }

    // Step 2: 确认写入（rows 为预览接口返回的解析结果，mode: skip/overwrite）
    public function import() {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!isset($data['rows']) || !is_array($data['rows'])) {
            http_response_code(400);
            echo json_encode(["message" => "缺少导入数据"]);
            return;
        }
        $mode = ($data['mode'] ?? 'skip') === 'overwrite' ? 'overwrite' : 'skip';

        $insertQ = "INSERT INTO licenses (qq, owner_name, product_name, upline, expiration_date)
                    VALUES (:qq, :owner, :product, :upline, :exp)";
        $insertStmt = $this->db->prepare($insertQ);
        $findQ = "SELECT id FROM licenses WHERE qq = :qq AND product_name = :product LIMIT 1";
        $findStmt = $this->db->prepare($findQ);
        $updateQ = "UPDATE licenses SET owner_name = :owner, upline = :upline, expiration_date = :exp
                    WHERE qq = :qq AND product_name = :product";
        $updateStmt = $this->db->prepare($updateQ);

        $success = 0; $inserted = 0; $overwritten = 0;
        $skipped = 0; $failed = 0;
        $failures = [];
        $seen = []; // 处理同一文件内部的重复

        $this->db->beginTransaction();
        $rowNum = 0;
        try {
            foreach ($data['rows'] as $index => $r) {
                $line = ($index + 2); // +2: 表头占第1行，下标从0开始
                $qq = trim($r['qq'] ?? '');
                $owner = trim($r['owner_name'] ?? '');
                $product = trim($r['product_name'] ?? '');
                $upline = trim($r['upline'] ?? '');
                if ($upline === '') { $upline = '官方'; }
                $expiration = trim($r['expiration_date'] ?? '');

                // 再次校验必填项（防止绕过预览）
                $errors = [];
                if ($qq === '') { $errors[] = '授权QQ缺失'; }
                if ($owner === '') { $errors[] = '授权主人缺失'; }
                if ($product === '') { $errors[] = '产品缺失'; }
                if ($expiration === '') { $errors[] = '有效期缺失'; }

                if (!empty($errors)) {
                    $failed++;
                    $failures[] = ["line" => $line, "qq" => $qq, "reason" => implode('；', $errors)];
                    continue;
                }

                $key = $qq . '|' . $product;
                $findStmt->execute([':qq' => $qq, ':product' => $product]);
                $existing = $findStmt->fetch(PDO::FETCH_ASSOC);
                $isDup = $existing || isset($seen[$key]);

                if ($isDup && $mode === 'skip') {
                    $skipped++;
                    continue;
                }

                // 保存点保证单行写入失败不污染整个事务
                $sp = 'sp' . $rowNum++;
                $this->db->exec("SAVEPOINT $sp");
                try {
                    if ($existing) {
                        $updateStmt->execute([
                            ':owner' => $owner,
                            ':upline' => $upline,
                            ':exp' => $expiration,
                            ':qq' => $qq,
                            ':product' => $product
                        ]);
                        $overwritten++;
                    } else {
                        $insertStmt->execute([
                            ':qq' => $qq,
                            ':owner' => $owner,
                            ':product' => $product,
                            ':upline' => $upline,
                            ':exp' => $expiration
                        ]);
                        $inserted++;
                    }
                    $seen[$key] = true;
                    $success++;
                } catch (\Exception $e) {
                    // 回滚到保存点，使外层事务仍可提交其他行
                    $this->db->exec("ROLLBACK TO SAVEPOINT $sp");
                    $failed++;
                    $failures[] = ["line" => $line, "qq" => $qq, "reason" => '写入失败: ' . $e->getMessage()];
                }
            }
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode(["message" => "导入事务失败: " . $e->getMessage()]);
            return;
        }

        echo json_encode([
            "status" => "success",
            "total" => count($data['rows']),
            "success" => $success,
            "inserted" => $inserted,
            "overwritten" => $overwritten,
            "skipped" => $skipped,
            "failed" => $failed,
            "failures" => array_slice($failures, 0, 100)
        ]);
    }

    // 读取并解析上传的 CSV，返回带校验信息的行数组
    private function parseCsvUpload() {
        if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'] ?? '')) {
            return null;
        }
        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            return ['error' => '文件上传失败（错误码 ' . $_FILES['file']['error'] . '）'];
        }

        $content = file_get_contents($_FILES['file']['tmp_name']);

        // 兼容 GBK/GB2312 旧表导出
        if (!mb_check_encoding($content, 'UTF-8')) {
            $converted = @iconv('GB18030', 'UTF-8//IGNORE', $content);
            if ($converted !== false) { $content = $converted; }
        }
        // 去掉 UTF-8 BOM
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $lines = preg_split('/\r\n|\r|\n/', $content);
        // 去尾部空行（按 CSV 解析后判断，避免误删“以逗号开头”的缺字段行）
        while (!empty($lines)) {
            $lastCells = str_getcsv(end($lines));
            $isEmpty = true;
            foreach ($lastCells as $c) { if (trim($c) !== '') { $isEmpty = false; break; } }
            if ($isEmpty) { array_pop($lines); } else { break; }
        }
        if (empty($lines)) { return ['error' => 'CSV 文件内容为空']; }

        // 表头别名映射
        $aliases = [
            'qq' => ['授权qq', 'qq', 'qq号', '授权qq号', 'qq号码'],
            'owner_name' => ['授权主人', '主人', '归属', '归属人', '持有人', 'owner', 'ownername', 'owner_name'],
            'product_name' => ['产品', '所属产品', '产品名称', '授权产品', 'product', 'productname', 'product_name'],
            'upline' => ['上级', '上级代理', '代理', 'upline'],
            'expiration_date' => ['有效期', '到期时间', '过期时间', '有效期至', '到期日期', 'expiration', 'expirationdate', 'expiration_date'],
        ];

        $firstLine = array_shift($lines);
        $header = str_getcsv($firstLine);
        $header = array_map(fn($h) => $this->normalizeHeader($h), $header);

        $columnMap = [];
        // 首行第一列是纯数字（QQ号）说明旧表没有表头，按固定列序解析
        $firstCellIsQq = isset($header[0]) && preg_match('/^\d{4,12}$/', $header[0]);
        if (!$firstCellIsQq) {
            foreach ($header as $i => $name) {
                foreach ($aliases as $field => $names) {
                    if (in_array($name, $names, true) && !isset($columnMap[$field])) {
                        $columnMap[$field] = $i;
                    }
                }
            }
        }
        // 未识别到表头时，按旧授权表固定列序兜底：QQ,主人,产品,上级,有效期
        if (empty($columnMap) || !isset($columnMap['qq'])) {
            $columnMap = ['qq' => 0, 'owner_name' => 1, 'product_name' => 2, 'upline' => 3, 'expiration_date' => 4];
            // 仅当首行确实是数据行（纯数字QQ）时才放回；识别到部分中文表头时首行仍为表头
            if ($firstCellIsQq) {
                array_unshift($lines, $firstLine);
            }
        }

        $rows = [];
        foreach ($lines as $raw) {
            if (trim($raw) === '') { continue; }
            $cells = str_getcsv($raw);
            $pick = function($field) use ($cells, $columnMap) {
                $idx = $columnMap[$field] ?? null;
                return ($idx !== null && isset($cells[$idx])) ? trim($cells[$idx]) : '';
            };

            $qq = $pick('qq');
            $owner = $pick('owner_name');
            $product = $pick('product_name');
            $upline = $pick('upline');
            $expirationRaw = $pick('expiration_date');
            $expiration = $this->normalizeDate($expirationRaw);

            $errors = [];
            if ($qq === '') { $errors[] = '授权QQ缺失'; }
            if ($owner === '') { $errors[] = '授权主人缺失'; }
            if ($product === '') { $errors[] = '产品缺失'; }
            if ($expirationRaw === '') {
                $errors[] = '有效期缺失';
            } elseif ($expiration === null) {
                $errors[] = '有效期格式无法识别';
            }

            $rows[] = [
                'qq' => $qq,
                'owner_name' => $owner,
                'product_name' => $product,
                'upline' => $upline === '' ? '官方' : $upline,
                'expiration_date' => $expiration ?? $expirationRaw,
                'expiration_raw' => $expirationRaw,
                'errors' => $errors,
                'duplicate' => false
            ];
        }

        return ['rows' => $rows];
    }

    private function normalizeHeader($h) {
        $h = trim($h);
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
        $h = str_replace([' ', '-', '/', '\\', '_', '　'], '', $h);
        return mb_strtolower($h, 'UTF-8');
    }

    // 支持 2026-12-31 / 2026/12/31 / 2026年12月31日 / 带时分秒 等格式；纯日期补到当天 23:59:59
    private function normalizeDate($value) {
        $value = trim($value);
        if ($value === '') { return null; }
        $hasChinese = str_contains($value, '年');
        $hasTime = (bool) preg_match('/\d{1,2}:\d{2}/', $value);

        $normalized = $value;
        if ($hasChinese) {
            $normalized = str_replace(['年', '月'], '-', $normalized);
            $normalized = str_replace('日', '', $normalized);
        }
        $normalized = str_replace('/', '-', $normalized);
        $normalized = trim($normalized);

        if (!$hasTime && preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $normalized)) {
            $normalized .= ' 23:59:59';
        }
        $ts = strtotime($normalized);
        if ($ts === false) { return null; }
        return date('Y-m-d H:i:s', $ts);
    }

    // 批量查询并标记已存在的记录（授权QQ + 产品）
    private function markDuplicates(array &$rows) {
        $qqs = array_values(array_unique(array_map(fn($r) => $r['qq'], array_filter($rows, fn($r) => $r['qq'] !== ''))));
        if (empty($qqs)) { return; }

        $placeholders = implode(',', array_fill(0, count($qqs), '?'));
        $stmt = $this->db->prepare("SELECT qq, product_name FROM licenses WHERE qq IN ($placeholders)");
        $stmt->execute($qqs);

        $existing = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existing[$row['qq'] . '|' . $row['product_name']] = true;
        }

        $seenInFile = [];
        foreach ($rows as &$r) {
            $key = $r['qq'] . '|' . $r['product_name'];
            if (isset($existing[$key]) || isset($seenInFile[$key])) {
                $r['duplicate'] = true;
            }
            $seenInFile[$key] = true;
        }
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
