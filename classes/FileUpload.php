<?php
// classes/FileUpload.php - File Upload Management Class

// Prevent direct access
if (!defined('SSJBOX_ACCESS')) {
    http_response_code(403);
    exit('Access Denied');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/encryption.php';

class FileUpload {
    private $db;
    private $table = 'uploadfiles';
    private $upload_path;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->upload_path = UPLOAD_PATH;
    }
    
    /**
     * Upload file
     */
    public function uploadFile($user_id, $file_data, $file_type, $data_date) {
        try {
            // Validate file
            $validation = $this->validateFile($file_data);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }
            
            // Get user info for filename
            $stmt = $this->db->prepare("SELECT hosp_code FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            // Generate filename and path
            $random_string = Encryption::generateRandomString(20);
            $date_string = date('Ymd', strtotime($data_date));
            $filename = $file_type . '_' . $user['hosp_code'] . '_' . $date_string . '_' . $random_string;
            $file_extension = pathinfo($file_data['name'], PATHINFO_EXTENSION);
            $full_filename = $filename . '.' . $file_extension;
            
            // Create directory structure
            $year = date('Y', strtotime($data_date));
            $month = date('m', strtotime($data_date));
            $relative_path = $file_type . '/' . $year . '/' . $month . '/';
            $full_path = $this->upload_path . $relative_path;
            
            if (!$this->createDirectory($full_path)) {
                return ['success' => false, 'message' => 'Failed to create upload directory'];
            }
            
            $file_path = $full_path . $full_filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file_data['tmp_name'], $file_path)) {
                return ['success' => false, 'message' => 'Failed to move uploaded file'];
            }
            
            // Generate file hash
            $file_hash = Encryption::generateFileHash($file_path);
            
            // Check for duplicates
            if ($this->isDuplicate($user_id, $file_hash, $file_type, $data_date)) {
                unlink($file_path); // Delete the uploaded file
                return ['success' => false, 'message' => 'Duplicate file detected'];
            }
            
            // Save to database
            $stmt = $this->db->prepare("
                INSERT INTO {$this->table} (
                    user_id, filename, original_filename, file_type, file_path, 
                    file_size, data_date, file_hash, status, ip_address
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)
            ");
            
            $result = $stmt->execute([
                $user_id,
                $full_filename,
                $file_data['name'],
                $file_type,
                $relative_path . $full_filename,
                $file_data['size'],
                $data_date,
                $file_hash,
                $this->getClientIP()
            ]);
            
            if ($result) {
                $file_id = $this->db->lastInsertId();
                return [
                    'success' => true, 
                    'message' => 'File uploaded successfully',
                    'file_id' => $file_id,
                    'filename' => $full_filename
                ];
            } else {
                unlink($file_path); // Delete the uploaded file
                return ['success' => false, 'message' => 'Failed to save file information'];
            }
            
        } catch (Exception $e) {
            error_log("FileUpload error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get user files
     */
    public function getUserFiles($user_id, $file_type = null, $limit = 50, $offset = 0) {
        $where_clause = "WHERE user_id = ? AND status != 'deleted'";
        $params = [$user_id];
        
        if ($file_type) {
            $where_clause .= " AND file_type = ?";
            $params[] = $file_type;
        }
        
        $sql = "
            SELECT file_id, filename, original_filename, file_type, file_size, 
                   data_date, upload_date, status
            FROM {$this->table}
            {$where_clause}
            ORDER BY upload_date DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Delete file
     */
    public function deleteFile($file_id, $user_id) {
        try {
            $this->db->beginTransaction();
            
            // Get file info
            $stmt = $this->db->prepare("
                SELECT file_path FROM {$this->table} 
                WHERE file_id = ? AND user_id = ? AND status != 'deleted'
            ");
            $stmt->execute([$file_id, $user_id]);
            $file = $stmt->fetch();
            
            if (!$file) {
                throw new Exception("File not found");
            }
            
            // Mark as deleted in database
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET status = 'deleted' 
                WHERE file_id = ? AND user_id = ?
            ");
            $stmt->execute([$file_id, $user_id]);
            
            // Delete physical file
            $physical_path = $this->upload_path . $file['file_path'];
            if (file_exists($physical_path)) {
                unlink($physical_path);
            }
            
            $this->db->commit();
            return ['success' => true, 'message' => 'File deleted successfully'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Failed to delete file: ' . $e->getMessage()];
        }
    }
    
    /**
     * Download file
     */
    public function downloadFile($file_id, $user_id) {
        $stmt = $this->db->prepare("
            SELECT filename, original_filename, file_path, file_size 
            FROM {$this->table} 
            WHERE file_id = ? AND user_id = ? AND status = 'completed'
        ");
        $stmt->execute([$file_id, $user_id]);
        $file = $stmt->fetch();
        
        if (!$file) {
            return false;
        }
        
        $physical_path = $this->upload_path . $file['file_path'];
        
        if (!file_exists($physical_path)) {
            return false;
        }
        
        return [
            'path' => $physical_path,
            'filename' => $file['original_filename'],
            'size' => $file['file_size']
        ];
    }
    
    /**
     * Get upload statistics
     */
    public function getUploadStats($days = 30) {
        $stmt = $this->db->prepare("
            SELECT 
                file_type,
                COUNT(*) as total_files,
                SUM(file_size) as total_size,
                DATE(upload_date) as upload_day
            FROM {$this->table}
            WHERE upload_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND status = 'completed'
            GROUP BY file_type, DATE(upload_date)
            ORDER BY upload_day DESC, file_type
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get hospital upload status
     */
    public function getHospitalUploadStatus($date = null) {
        $date = $date ?: date('Y-m-d');
        
        $stmt = $this->db->prepare("
            SELECT h.hosp_code, h.hosp_name,
                   COUNT(CASE WHEN f.file_type = 'hosxp' THEN 1 END) as hosxp_count,
                   COUNT(CASE WHEN f.file_type = 'f43' THEN 1 END) as f43_count
            FROM hospitals h
            LEFT JOIN users u ON h.hosp_code = u.hosp_code
            LEFT JOIN {$this->table} f ON u.user_id = f.user_id 
                AND f.data_date = ? AND f.status = 'completed'
            GROUP BY h.hosp_code, h.hosp_name
            ORDER BY h.hosp_name
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll();
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file_data) {
        // Check for upload errors
        if ($file_data['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'message' => 'Upload error: ' . $this->getUploadErrorMessage($file_data['error'])];
        }
        
        // Check file size
        if ($file_data['size'] > UPLOAD_MAX_SIZE) {
            return ['valid' => false, 'message' => 'File size exceeds maximum allowed size (500MB)'];
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, UPLOAD_ALLOWED_EXTENSIONS)) {
            return ['valid' => false, 'message' => 'File type not allowed. Only ZIP, 7Z, and RAR files are permitted.'];
        }
        
        // Check if file is actually uploaded via HTTP POST
        if (!is_uploaded_file($file_data['tmp_name'])) {
            return ['valid' => false, 'message' => 'Invalid file upload'];
        }
        
        return ['valid' => true, 'message' => 'File is valid'];
    }
    
    /**
     * Check for duplicate files
     */
    private function isDuplicate($user_id, $file_hash, $file_type, $data_date) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM {$this->table} 
            WHERE user_id = ? AND file_hash = ? AND file_type = ? AND data_date = ? AND status = 'completed'
        ");
        $stmt->execute([$user_id, $file_hash, $file_type, $data_date]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Create directory if not exists
     */
    private function createDirectory($path) {
        if (!file_exists($path)) {
            return mkdir($path, 0755, true);
        }
        return true;
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds upload_max_filesize directive';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds MAX_FILE_SIZE directive';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
