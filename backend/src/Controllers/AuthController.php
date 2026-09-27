<?php
namespace Controllers;

use Config\Database;
use PDO;

class AuthController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function login() {
        $data = json_decode(file_get_contents("php://input"));
        
        if (!isset($data->username) || !isset($data->password)) {
            http_response_code(400);
            echo json_encode(["message" => "Missing credentials"]);
            return;
        }

        $query = "SELECT id, username, password FROM admins WHERE username = :username LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":username", $data->username);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($data->password, $row['password'])) {
                // ... success ...
                http_response_code(200);
                echo json_encode([
                    "message" => "Login successful",
                    "user" => $row['username'],
                    "token" => base64_encode($row['username'] . ":" . time())
                ]);
                return;
            }
        }

        http_response_code(401);
        echo json_encode(["message" => "Login failed"]);
    }
    public function list() {
        $query = "SELECT id, username FROM admins";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function create() {
        $data = json_decode(file_get_contents("php://input"));
        if (!isset($data->username) || !isset($data->password)) {
            http_response_code(400); return;
        }

        // Check exists
        $check = $this->db->prepare("SELECT id FROM admins WHERE username = :u");
        $check->execute([':u' => $data->username]);
        if($check->rowCount() > 0) {
            http_response_code(409); 
            echo json_encode(["message" => "Username exists"]);
            return;
        }

        $hash = password_hash($data->password, PASSWORD_BCRYPT);
        $query = "INSERT INTO admins (username, password) VALUES (:u, :p)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':u' => $data->username, ':p' => $hash]);

        echo json_encode(["message" => "Admin created"]);
    }

    public function delete() {
        $data = json_decode(file_get_contents("php://input"));
        if (!isset($data->id)) return;

        // Prevent deleting self? Frontend can handle, but backend safe guard is good.
        // Simplified for now.
        $stmt = $this->db->prepare("DELETE FROM admins WHERE id = :id");
        $stmt->execute([':id' => $data->id]);
        echo json_encode(["message" => "Admin deleted"]);
    }
}
