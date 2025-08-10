<?php
// classes/FileUpload.php - แก้ไขใหม่ทั้งหมด
class FileUpload {
    private $db;
    private $logger;
    private $allowedExtensions = ['.zip', '.7z', '.rar'];
    private $allowedMimeTypes = [
        'application/zip',
        'application/x-zip-compressed',
        'application/x-7z-compressed',
        'application/x-rar-compressed',
        'application/x-rar',
        'application/octet-stream' // Sometimes compressed files show as this
    ];
    private $maxFileSize = 536870912; // 512MB
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
    }
    
    /**
     * Upload file with comprehensive validation and error handling
     */
    public function uploadFile($userId, $file, $category, $uploadDate) {
        try {
            // Validate user
            if (!$userId || !is_numeric($userId)) {
                return ['success' => false, 'message' => 'ข้อมูลผู้ใช้ไม่ถูกต้อง'];
            }
            
            // Validate category
            if (!in_array($category, ['HIS', 'F43'])) {
                return ['success' => false, 'message' => 'ประเภทไฟล์ไม่ถูกต้อง'];
            }
            
            // Validate upload date
            if (!$this->isValidDate($uploadDate)) {
                return ['success' => false, 'message' => 'วันที่ไม่ถูกต้อง'];
            }
            
            if (strtotime($uploadDate) > time()) {
                return ['success' => false, 'message' => 'วันที่ส่งข้อมูลไม่สามารถเป็นวันในอนาคตได้'];
            }
            
            // Validate file
            $validation = $this->validateFile($file);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Check user storage quota (optional)
            if (!$this->checkUserQuota($userId, $file['size'])) {
                return ['success' => false, 'message' => 'พื้นที่จัดเก็บไฟล์เต็ม'];
            }
            
            // Create upload directory
            $uploadPath = $this->createUploadPath($category, $uploadDate);
            if (!$uploadPath) {
                return ['success' => false, 'message' => 'ไม่สามารถสร้างโฟลเดอร์สำหรับอัพโหลดได้'];
            }
            
            // Generate new filename
            $newFilename = $this->generateNewFilename($file['name'], $userId);
            $fullPath = $uploadPath . $newFilename;
            
            // Check if file already exists
            if (file_exists($fullPath)) {
                $newFilename = $this->generateNewFilename($file['name'], $userId, true);
                $fullPath = $uploadPath . $newFilename;
            }
            
            // Start database transaction
            $this->db->beginTransaction();
            
            try {
                // Move uploaded file
                if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                    throw new Exception('ไม่สามารถอัพโหลดไฟล์ได้');
                }
                
                // Set proper file permissions
                chmod($fullPath, 0644);
                
                // Save file info to database
                $fileId = $this->saveFileInfo($userId, $file, $newFilename, $category, $uploadDate, $fullPath);
                
                if (!$fileId) {
                    throw new Exception('ไม่สามารถบันทึกข้อมูลไฟล์ได้');
                }
                
                // Commit transaction
                $this->db->commit();
                
                // Log successful upload
                $this->logger->logUserActivity($userId, 'FILE_UPLOAD', 
                    "Uploaded file: {$file['name']} as $newFilename, Size: {$file['size']}, Category: $category");
                
                return [
                    'success' => true, 
                    'message' => 'อัพโหลดไฟล์สำเร็จ',
                    'file_id' => $fileId,
                    'filename' => $newFilename,
                    'size' => $file['size'],
                    'category' => $category
                ];
                
            } catch (Exception $e) {
                // Rollback transaction
                $this->db->rollback();
                
                // Remove uploaded file if exists
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
                
                throw $e;
            }
            
        } catch (Exception $e) {
            $this->logger->error('File upload error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Enhanced file validation
     */
    private function validateFile($file) {
        // Check for upload errors
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'ไฟล์มีขนาดใหญ่เกินที่กำหนดในเซิร์ฟเวอร์',
                UPLOAD_ERR_FORM_SIZE => 'ไฟล์มีขนาดใหญ่เกินที่กำหนด',
                UPLOAD_ERR_PARTIAL => 'อัพโหลดไฟล์ไม่สมบูรณ์',
                UPLOAD_ERR_NO_FILE => 'ไม่มีไฟล์ที่อัพโหลด',
                UPLOAD_ERR_NO_TMP_DIR => 'ไม่มีโฟลเดอร์ชั่วคราว',
                UPLOAD_ERR_CANT_WRITE => 'ไม่สามารถเขียนไฟล์ลงดิสก์ได้',
                UPLOAD_ERR_EXTENSION => 'การอัพโหลดถูกหยุดโดยส่วนขยาย'
            ];
            
            $message = $errorMessages[$file['error']] ?? 'เกิดข้อผิดพลาดในการอัพโหลด';
            return ['success' => false, 'message' => $message];
        }
        
        // Check if file was actually uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'ไฟล์ไม่ได้ถูกอัพโหลดผ่านระบบ'];
        }
        
        // Check file size
        if (!isset($file['size']) || $file['size'] > $this->maxFileSize) {
            return ['success' => false, 'message' => 'ไฟล์มีขนาดใหญ่เกิน 512MB'];
        }
        
        if ($file['size'] == 0) {
            return ['success' => false, 'message' => 'ไฟล์ว่างเปล่า'];
        }
        
        // Check filename
        if (!isset($file['name']) || empty($file['name'])) {
            return ['success' => false, 'message' => 'ชื่อไฟล์ไม่ถูกต้อง'];
        }
        
        // Validate filename length
        if (strlen($file['name']) > 255) {
            return ['success' => false, 'message' => 'ชื่อไฟล์ยาวเกิน 255 ตัวอักษร'];
        }
        
        // Check for dangerous characters in filename
        if (preg_match('/[<>:"/\\|?*\x00-\x1f]/', $file['name'])) {
            return ['success' => false, 'message' => 'ชื่อไฟล์มีตัวอักษรที่ไม่ได้รับอนุญาต'];
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fullExtension = '.' . $extension;
        
        if (!in_array($fullExtension, $this->allowedExtensions)) {
            return ['success' => false, 'message' => 'นามสกุลไฟล์ไม่ได้รับอนุญาต (อนุญาตเฉพาะ .zip, .7z, .rar)'];
        }
        
        // Verify file type using multiple methods
        if (!$this->verifyFileType($file['tmp_name'], $extension)) {
            return ['success' => false, 'message' => 'ประเภทไฟล์ไม่ตรงกับนามสกุล'];
        }
        
        // Check for PHP code in file (security)
        if ($this->containsPhpCode($file['tmp_name'])) {
            return ['success' => false, 'message' => 'ไฟล์มีเนื้อหาที่ไม่ปลอดภัย'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Verify file type using multiple methods
     */
    private function verifyFileType($tmpPath, $extension) {
        // Method 1: Check MIME type
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $this->allowedMimeTypes)) {
                // Some servers return different MIME types, so check file signature
                return $this->verifyFileSignature($tmpPath, $extension);
            }
        }
        
        // Method 2: Check file signature
        return $this->verifyFileSignature($tmpPath, $extension);
    }
    
    /**
     * Verify file signature (magic bytes)
     */
    private function verifyFileSignature($tmpPath, $extension) {
        $handle = fopen($tmpPath, 'rb');
        if (!$handle) {
            return false;
        }
        
        $header = fread($handle, 10);
        fclose($handle);
        
        $signatures = [
            'zip' => [
                "\x50\x4B\x03\x04", // Standard ZIP
                "\x50\x4B\x05\x06", // Empty ZIP
                "\x50\x4B\x07\x08"  // Spanned ZIP
            ],
            '7z' => [
                "\x37\x7A\xBC\xAF\x27\x1C" // 7z signature
            ],
            'rar' => [
                "\x52\x61\x72\x21\x1A\x07\x00", // RAR v1.5+
                "\x52\x61\x72\x21\x1A\x07\x01\x00" // RAR v5.0+
            ]
        ];
        
        if (!isset($signatures[$extension])) {
            return false;
        }
        
        foreach ($signatures[$extension] as $signature) {
            if (substr($header, 0, strlen($signature)) === $signature) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check for PHP code in uploaded file (security measure)
     */
    private function containsPhpCode($tmpPath) {
        $handle = fopen($tmpPath, 'rb');
        if (!$handle) {
            return false;
        }
        
        // Read first 1KB to check for PHP tags
        $content = fread($handle, 1024);
        fclose($handle);
        
        // Look for PHP opening tags
        $phpPatterns = [
            '/<\?php/i',
            '/<\?=/i',
            '/<\?[^x]/i',
            '/<script[^>]*language\s*=\s*["\']?php["\']?[^>]*>/i'
        ];
        
        foreach ($phpPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check user storage quota
     */
    private function checkUserQuota($userId, $fileSize) {
        try {
            // Get user's current storage usage
            $sql = "SELECT COALESCE(SUM(file_size), 0) as total_size 
                    FROM uploadfiles 
                    WHERE user_id = ?";
            $result = $this->db->fetch($sql, [$userId]);
            
            $currentUsage = $result['total_size'] ?? 0;
            $maxQuota = 5 * 1024 * 1024 * 1024; // 5GB per user (configurable)
            
            return ($currentUsage + $fileSize) <= $maxQuota;
        } catch (Exception $e) {
            $this->logger->error('Error checking user quota: ' . $e->getMessage());
            return true; // Allow upload if we can't check quota
        }
    }
    
    /**
     * Create upload directory with proper structure
     */
    private function createUploadPath($category, $uploadDate) {
        try {
            $date = new DateTime($uploadDate);
            $year = $date->format('Y');
            $month = $date->format('m');
            
            $basePath = UPLOAD_PATH . strtolower($category) . DIRECTORY_SEPARATOR . 
                       $year . DIRECTORY_SEPARATOR . $month . DIRECTORY_SEPARATOR;
            
            if (!is_dir($basePath)) {
                if (!mkdir($basePath, 0755, true)) {
                    $this->logger->error('Failed to create upload directory: ' . $basePath);
                    return false;
                }
                
                // Create .htaccess file in each directory for security
                $htaccessContent = "Options -Indexes\n" .
                                 "Options -ExecCGI\n" .
                                 "<Files *.php>\n" .
                                 "    Deny from all\n" .
                                 "</Files>\n";
                
                file_put_contents($basePath . '.htaccess', $htaccessContent);
            }
            
            return $basePath;
        } catch (Exception $e) {
            $this->logger->error('Error creating upload path: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate secure filename
     */
    private function generateNewFilename($originalName, $userId, $forceUnique = false) {
        $pathInfo = pathinfo($originalName);
        $extension = isset($pathInfo['extension']) ? $pathInfo['extension'] : '';
        $basename = isset($pathInfo['filename']) ? $pathInfo['filename'] : 'file';
        
        // Clean basename
        $basename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $basename);
        $basename = preg_replace('/_{2,}/', '_', $basename); // Replace multiple underscores
        $basename = trim($basename, '_');
        
        if (empty($basename)) {
            $basename = 'file';
        }
        
        // Limit basename length
        if (strlen($basename) > 100) {
            $basename = substr($basename, 0, 100);
        }
        
        // Generate timestamp and random components
        $timestamp = time();
        $randomNumber = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $userHash = substr(md5($userId . $timestamp), 0, 4);
        
        if ($forceUnique) {
            $randomNumber = str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $userHash = substr(md5(uniqid($userId, true)), 0, 6);
        }
        
        return $basename . '_' . $timestamp . '_' . $userHash . '_' . $randomNumber . '.' . $extension;
    }
    
    /**
     * Save file information to database
     */
    private function saveFileInfo($userId, $file, $newFilename, $category, $uploadDate, $fullPath) {
        try {
            $sql = "INSERT INTO uploadfiles (
                        user_id, original_filename, new_filename, 
                        file_size, file_type, file_category, 
                        file_path, upload_date
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $userId,
                $file['name'],
                $newFilename,
                $file['size'],
                $file['type'] ?? 'application/octet-stream',
                $category,
                $fullPath,
                $uploadDate
            ];
            
            $this->db->execute($sql, $params);
            return $this->db->lastInsertId();
            
        } catch (Exception $e) {
            $this->logger->error('Failed to save file info: ' . $e->getMessage());
            throw new Exception('ไม่สามารถบันทึกข้อมูลไฟล์ได้');
        }
    }
    
    /**
     * Get user files with pagination and filtering
     */
    public function getUserFiles($userId, $category = null, $limit = null, $offset = 0, $orderBy = 'file_uploaded_at DESC') {
        try {
            $sql = "SELECT * FROM uploadfiles WHERE user_id = ?";
            $params = [$userId];
            
            if ($category) {
                $sql .= " AND file_category = ?";
                $params[] = $category;
            }
            
            // Validate order by to prevent SQL injection
            $allowedOrderBy = [
                'file_uploaded_at DESC', 'file_uploaded_at ASC',
                'original_filename ASC', 'original_filename DESC',
                'file_size ASC', 'file_size DESC',
                'upload_date DESC', 'upload_date ASC'
            ];
            
            if (in_array($orderBy, $allowedOrderBy)) {
                $sql .= " ORDER BY " . $orderBy;
            } else {
                $sql .= " ORDER BY file_uploaded_at DESC";
            }
            
            if ($limit && is_numeric($limit)) {
                $sql .= " LIMIT ? OFFSET ?";
                $params[] = (int)$limit;
                $params[] = (int)$offset;
            }
            
            return $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            $this->logger->error('Error getting user files: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get file by ID with user verification
     */
    public function getFileById($fileId, $userId = null) {
        try {
            $sql = "SELECT * FROM uploadfiles WHERE file_id = ?";
            $params = [$fileId];
            
            if ($userId) {
                $sql .= " AND user_id = ?";
                $params[] = $userId;
            }
            
            return $this->db->fetch($sql, $params);
        } catch (Exception $e) {
            $this->logger->error('Error getting file by ID: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update file information
     */
    public function updateFile($fileId, $userId, $data) {
        try {
            // Verify file ownership
            $file = $this->getFileById($fileId, $userId);
            if (!$file) {
                return ['success' => false, 'message' => 'ไม่พบไฟล์หรือไม่มีสิทธิ์แก้ไข'];
            }
            
            // Validate upload date
            if (isset($data['upload_date'])) {
                if (!$this->isValidDate($data['upload_date'])) {
                    return ['success' => false, 'message' => 'วันที่ไม่ถูกต้อง'];
                }
                
                if (strtotime($data['upload_date']) > time()) {
                    return ['success' => false, 'message' => 'วันที่ส่งข้อมูลไม่สามารถเป็นวันในอนาคตได้'];
                }
            }
            
            $sql = "UPDATE uploadfiles SET upload_date = ?, file_updated_at = NOW() 
                    WHERE file_id = ? AND user_id = ?";
            
            $this->db->execute($sql, [$data['upload_date'], $fileId, $userId]);
            
            $this->logger->logUserActivity($userId, 'FILE_UPDATE', 
                "Updated file: {$file['original_filename']}");
            
            return ['success' => true, 'message' => 'อัพเดทข้อมูลไฟล์สำเร็จ'];
            
        } catch (Exception $e) {
            $this->logger->error('File update error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัพเดทไฟล์'];
        }
    }
    
    /**
     * Delete file with comprehensive cleanup
     */
    public function deleteFile($fileId, $userId) {
        try {
            // Verify file ownership
            $file = $this->getFileById($fileId, $userId);
            if (!$file) {
                return ['success' => false, 'message' => 'ไม่พบไฟล์หรือไม่มีสิทธิ์ลบ'];
            }
            
            $this->db->beginTransaction();
            
            try {
                // Delete from database first
                $sql = "DELETE FROM uploadfiles WHERE file_id = ? AND user_id = ?";
                $result = $this->db->execute($sql, [$fileId, $userId]);
                
                if (!$result) {
                    throw new Exception('ไม่สามารถลบข้อมูลไฟล์จากฐานข้อมูลได้');
                }
                
                // Delete physical file
                if (file_exists($file['file_path'])) {
                    if (!unlink($file['file_path'])) {
                        $this->logger->warning("Failed to delete physical file: {$file['file_path']}");
                        // Don't fail the operation if we can't delete the physical file
                    }
                }
                
                $this->db->commit();
                
                $this->logger->logUserActivity($userId, 'FILE_DELETE', 
                    "Deleted file: {$file['original_filename']}");
                
                return ['success' => true, 'message' => 'ลบไฟล์สำเร็จ'];
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            $this->logger->error('File delete error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการลบไฟล์'];
        }
    }
    
    /**
     * Download file with access verification
     */
    public function downloadFile($fileId, $userId) {
        try {
            // Verify file ownership
            $file = $this->getFileById($fileId, $userId);
            if (!$file) {
                return ['success' => false, 'message' => 'ไม่พบไฟล์หรือไม่มีสิทธิ์ดาวน์โหลด'];
            }
            
            if (!file_exists($file['file_path'])) {
                return ['success' => false, 'message' => 'ไฟล์ไม่อยู่ในระบบ'];
            }
            
            // Verify file integrity
            if (filesize($file['file_path']) != $file['file_size']) {
                $this->logger->warning("File size mismatch for file ID: $fileId");
                return ['success' => false, 'message' => 'ไฟล์เสียหาย'];
            }
            
            $this->logger->logUserActivity($userId, 'FILE_DOWNLOAD', 
                "Downloaded file: {$file['original_filename']}");
            
            return ['success' => true, 'file' => $file];
            
        } catch (Exception $e) {
            $this->logger->error('File download error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการดาวน์โหลดไฟล์'];
        }
    }
    
    /**
     * Get file statistics with caching
     */
    public function getFileStatistics($userId) {
        try {
            $sql = "SELECT 
                        file_category,
                        COUNT(*) as file_count,
                        SUM(file_size) as total_size,
                        AVG(file_size) as avg_size,
                        MAX(file_uploaded_at) as latest_upload
                    FROM uploadfiles 
                    WHERE user_id = ? 
                    GROUP BY file_category";
            
            $stats = $this->db->fetchAll($sql, [$userId]);
            
            $result = [
                'HIS' => ['count' => 0, 'size' => 0, 'avg_size' => 0, 'latest_upload' => null],
                'F43' => ['count' => 0, 'size' => 0, 'avg_size' => 0, 'latest_upload' => null]
            ];
            
            foreach ($stats as $stat) {
                $result[$stat['file_category']] = [
                    'count' => (int)$stat['file_count'],
                    'size' => (int)$stat['total_size'],
                    'avg_size' => (float)$stat['avg_size'],
                    'latest_upload' => $stat['latest_upload']
                ];
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('File statistics error: ' . $e->getMessage());
            return [
                'HIS' => ['count' => 0, 'size' => 0, 'avg_size' => 0, 'latest_upload' => null],
                'F43' => ['count' => 0, 'size' => 0, 'avg_size' => 0, 'latest_upload' => null]
            ];
        }
    }
    
    /**
     * Format file size for display
     */
    public static function formatFileSize($bytes) {
        if ($bytes <= 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor(log($bytes, 1024));
        $factor = min($factor, count($units) - 1);
        
        $size = $bytes / pow(1024, $factor);
        $precision = ($factor >= 2) ? 2 : 0; // Show decimals for MB and above
        
        return round($size, $precision) . ' ' . $units[$factor];
    }
    
    /**
     * Clean old temporary files
     */
    public function cleanTempFiles() {
        try {
            $tempDir = sys_get_temp_dir();
            $pattern = $tempDir . '/php*';
            $files = glob($pattern);
            $cleaned = 0;
            
            foreach ($files as $file) {
                if (is_file($file) && (time() - filemtime($file)) > 3600) { // 1 hour old
                    if (unlink($file)) {
                        $cleaned++;
                    }
                }
            }
            
            $this->logger->info("Cleaned $cleaned temporary files");
            return $cleaned;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to clean temp files: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get upload progress - Enhanced implementation (แก้ไขจากโค้ดเดิม)
     */
    public function getUploadProgress($uploadId) {
        if (empty($uploadId)) {
            return null;
        }
        
        // Sanitize upload ID
        $uploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', $uploadId);
        if (empty($uploadId)) {
            return null;
        }
        
        try {
            // Method 1: Session-based progress (Primary method)
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $progressKey = 'upload_progress_' . $uploadId;
            $progress = $_SESSION[$progressKey] ?? null;
            
            if ($progress && is_array($progress)) {
                // ตรวจสอบความสด
                $maxAge = 300; // 5 minutes
                if (isset($progress['timestamp']) && (time() - $progress['timestamp']) <= $maxAge) {
                    return $progress;
                } else {
                    unset($_SESSION[$progressKey]);
                }
            }
            
            // Method 2: File-based progress (Alternative method)
            $progressFile = sys_get_temp_dir() . '/upload_progress_' . $uploadId . '.json';
            if (file_exists($progressFile)) {
                $maxAge = 300; // 5 minutes
                if ((time() - filemtime($progressFile)) <= $maxAge) {
                    $data = file_get_contents($progressFile);
                    $fileProgress = json_decode($data, true);
                    
                    if ($fileProgress && is_array($fileProgress)) {
                        return [
                            'percent' => max(0, min(100, (float)($fileProgress['percent'] ?? 0))),
                            'uploaded' => max(0, (int)($fileProgress['uploaded'] ?? 0)),
                            'total' => max(0, (int)($fileProgress['total'] ?? 0)),
                            'speed' => max(0, (float)($fileProgress['speed'] ?? 0)),
                            'status' => $fileProgress['status'] ?? 'uploading',
                            'source' => 'file'
                        ];
                    }
                } else {
                    // Clean old progress file
                    unlink($progressFile);
                }
            }
            
            // Method 3: uploadprogress extension (ที่ปรับปรุงแล้ว)
            // if (extension_loaded('uploadprogress') && function_exists('uploadprogress_get_info')) {
            //     $uploadInfo = uploadprogress_get_info($uploadId);
                
            //     if ($uploadInfo && is_array($uploadInfo)) {
            //         // ตรวจสอบข้อมูลที่จำเป็น
            //         $uploaded = isset($uploadInfo['bytes_uploaded']) ? (int)$uploadInfo['bytes_uploaded'] : 0;
            //         $total = isset($uploadInfo['bytes_total']) ? (int)$uploadInfo['bytes_total'] : 0;
                    
            //         if ($total > 0) {
            //             $percent = min(100, round(($uploaded / $total) * 100, 2));
                        
            //             // คำนวณความเร็ว
            //             $speed = 0;
            //             if (isset($uploadInfo['speed_average'])) {
            //                 $speed = max(0, (float)$uploadInfo['speed_average']);
            //             } elseif (isset($uploadInfo['time_start']) && $uploadInfo['time_start'] > 0) {
            //                 $timeElapsed = time() - $uploadInfo['time_start'];
            //                 if ($timeElapsed > 0) {
            //                     $speed = $uploaded / $timeElapsed;
            //                 }
            //             }
                        
            //             // กำหนดสถานะ
            //             $status = 'uploading';
            //             if ($percent >= 100) {
            //                 $status = 'completed';
            //             } elseif (isset($uploadInfo['cancel_upload']) && $uploadInfo['cancel_upload']) {
            //                 $status = 'cancelled';
            //             }
                        
            //             return [
            //                 'percent' => $percent,
            //                 'uploaded' => $uploaded,
            //                 'total' => $total,
            //                 'speed' => round($speed, 2),
            //                 'status' => $status,
            //                 'source' => 'extension'
            //             ];
            //         }
            //     }
            // }
            
            return null;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Error getting upload progress: ' . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Set upload progress in session and file
     */
    public function setUploadProgress($uploadId, $progress) {
        try {
            // Validate progress data
            if (!is_array($progress)) {
                return false;
            }
            
            // Add timestamp
            $progress['timestamp'] = time();
            
            // Method 1: Store in session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $progressKey = 'upload_progress_' . $uploadId;
            $_SESSION[$progressKey] = $progress;
            
            // Method 2: Store in temporary file as backup
            $progressFile = sys_get_temp_dir() . '/upload_progress_' . $uploadId . '.json';
            $jsonData = json_encode($progress);
            
            if ($jsonData !== false) {
                file_put_contents($progressFile, $jsonData, LOCK_EX);
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Error setting upload progress: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean upload progress data
     */
    public function cleanUploadProgress($uploadId) {
        try {
            // Clean from session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $progressKey = 'upload_progress_' . $uploadId;
            unset($_SESSION[$progressKey]);
            
            // Clean from temporary file
            $progressFile = sys_get_temp_dir() . '/upload_progress_' . $uploadId . '.json';
            if (file_exists($progressFile)) {
                unlink($progressFile);
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Error cleaning upload progress: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean all old upload progress files
     */
    public function cleanOldUploadProgress($maxAge = 3600) {
        try {
            $tempDir = sys_get_temp_dir();
            $pattern = $tempDir . '/upload_progress_*.json';
            $files = glob($pattern);
            $cleaned = 0;
            
            foreach ($files as $file) {
                if (is_file($file) && (time() - filemtime($file)) > $maxAge) {
                    if (unlink($file)) {
                        $cleaned++;
                    }
                }
            }
            
            $this->logger->info("Cleaned $cleaned old upload progress files");
            return $cleaned;
            
        } catch (Exception $e) {
            $this->logger->error('Error cleaning old upload progress files: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Validate date format
     */
    private function isValidDate($date, $format = 'Y-m-d') {
        try {
            $d = DateTime::createFromFormat($format, $date);
            return $d && $d->format($format) === $date;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get file count by category for user
     */
    public function getFileCount($userId, $category = null) {
        try {
            $sql = "SELECT COUNT(*) as count FROM uploadfiles WHERE user_id = ?";
            $params = [$userId];
            
            if ($category) {
                $sql .= " AND file_category = ?";
                $params[] = $category;
            }
            
            $result = $this->db->fetch($sql, $params);
            return (int)($result['count'] ?? 0);
            
        } catch (Exception $e) {
            $this->logger->error('Error getting file count: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get total storage used by user
     */
    public function getTotalStorageUsed($userId) {
        try {
            $sql = "SELECT COALESCE(SUM(file_size), 0) as total_size FROM uploadfiles WHERE user_id = ?";
            $result = $this->db->fetch($sql, [$userId]);
            return (int)($result['total_size'] ?? 0);
            
        } catch (Exception $e) {
            $this->logger->error('Error getting total storage: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Check if filename already exists for user
     */
    public function isFilenameExists($userId, $filename, $excludeFileId = null) {
        try {
            $sql = "SELECT COUNT(*) as count FROM uploadfiles WHERE user_id = ? AND original_filename = ?";
            $params = [$userId, $filename];
            
            if ($excludeFileId) {
                $sql .= " AND file_id != ?";
                $params[] = $excludeFileId;
            }
            
            $result = $this->db->fetch($sql, $params);
            return ((int)($result['count'] ?? 0)) > 0;
            
        } catch (Exception $e) {
            $this->logger->error('Error checking filename existence: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get recent files for user
     */
    public function getRecentFiles($userId, $limit = 5) {
        try {
            $sql = "SELECT * FROM uploadfiles 
                    WHERE user_id = ? 
                    ORDER BY file_uploaded_at DESC 
                    LIMIT ?";
            
            return $this->db->fetchAll($sql, [$userId, (int)$limit]);
            
        } catch (Exception $e) {
            $this->logger->error('Error getting recent files: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Scan for orphaned files (files in filesystem but not in database)
     */
    public function scanOrphanedFiles() {
        try {
            $orphanedFiles = [];
            $uploadCategories = ['his', 'f43'];
            
            foreach ($uploadCategories as $category) {
                $categoryPath = UPLOAD_PATH . $category;
                
                if (!is_dir($categoryPath)) {
                    continue;
                }
                
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($categoryPath, RecursiveDirectoryIterator::SKIP_DOTS)
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() !== 'htaccess') {
                        $filePath = $file->getPathname();
                        
                        // Check if file exists in database
                        $sql = "SELECT COUNT(*) as count FROM uploadfiles WHERE file_path = ?";
                        $result = $this->db->fetch($sql, [$filePath]);
                        
                        if (((int)($result['count'] ?? 0)) === 0) {
                            $orphanedFiles[] = [
                                'path' => $filePath,
                                'size' => $file->getSize(),
                                'modified' => date('Y-m-d H:i:s', $file->getMTime())
                            ];
                        }
                    }
                }
            }
            
            return $orphanedFiles;
            
        } catch (Exception $e) {
            $this->logger->error('Error scanning orphaned files: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Clean orphaned files
     */
    public function cleanOrphanedFiles($olderThan = 86400) {
        try {
            $orphanedFiles = $this->scanOrphanedFiles();
            $cleaned = 0;
            $cutoffTime = time() - $olderThan;
            
            foreach ($orphanedFiles as $fileInfo) {
                $modifiedTime = strtotime($fileInfo['modified']);
                
                if ($modifiedTime < $cutoffTime) {
                    if (unlink($fileInfo['path'])) {
                        $cleaned++;
                        $this->logger->info("Cleaned orphaned file: " . $fileInfo['path']);
                    }
                }
            }
            
            return $cleaned;
            
        } catch (Exception $e) {
            $this->logger->error('Error cleaning orphaned files: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get system storage statistics
     */
    public function getSystemStats() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_files,
                        SUM(file_size) as total_size,
                        AVG(file_size) as avg_size,
                        MIN(file_uploaded_at) as first_upload,
                        MAX(file_uploaded_at) as latest_upload,
                        file_category,
                        COUNT(DISTINCT user_id) as unique_users
                    FROM uploadfiles 
                    GROUP BY file_category";
            
            $results = $this->db->fetchAll($sql);
            
            $stats = [
                'categories' => [],
                'total_files' => 0,
                'total_size' => 0,
                'total_users' => 0
            ];
            
            $allUsers = [];
            
            foreach ($results as $result) {
                $stats['categories'][$result['file_category']] = [
                    'files' => (int)$result['total_files'],
                    'size' => (int)$result['total_size'],
                    'avg_size' => (float)$result['avg_size'],
                    'users' => (int)$result['unique_users'],
                    'first_upload' => $result['first_upload'],
                    'latest_upload' => $result['latest_upload']
                ];
                
                $stats['total_files'] += (int)$result['total_files'];
                $stats['total_size'] += (int)$result['total_size'];
            }
            
            // Get total unique users
            $sql = "SELECT COUNT(DISTINCT user_id) as total_users FROM uploadfiles";
            $result = $this->db->fetch($sql);
            $stats['total_users'] = (int)($result['total_users'] ?? 0);
            
            // Get disk usage
            $stats['disk_free'] = disk_free_space(UPLOAD_PATH);
            $stats['disk_total'] = disk_total_space(UPLOAD_PATH);
            $stats['disk_used'] = $stats['disk_total'] - $stats['disk_free'];
            
            return $stats;
            
        } catch (Exception $e) {
            $this->logger->error('Error getting system stats: ' . $e->getMessage());
            return [
                'categories' => [],
                'total_files' => 0,
                'total_size' => 0,
                'total_users' => 0,
                'disk_free' => 0,
                'disk_total' => 0,
                'disk_used' => 0
            ];
        }
    }
    
    /**
     * Backup file metadata to JSON
     */
    public function backupFileMetadata($outputPath = null) {
        try {
            if (!$outputPath) {
                $outputPath = UPLOAD_PATH . 'backup_metadata_' . date('Y-m-d_H-i-s') . '.json';
            }
            
            $sql = "SELECT f.*, u.user_name, r.reg_firstname, r.reg_lastname 
                    FROM uploadfiles f
                    LEFT JOIN users u ON f.user_id = u.user_id
                    LEFT JOIN registers r ON u.reg_id = r.reg_id
                    ORDER BY f.file_uploaded_at";
            
            $files = $this->db->fetchAll($sql);
            
            $backup = [
                'backup_date' => date('Y-m-d H:i:s'),
                'total_files' => count($files),
                'files' => $files
            ];
            
            $json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            if (file_put_contents($outputPath, $json) !== false) {
                $this->logger->info("File metadata backup created: $outputPath");
                return ['success' => true, 'path' => $outputPath];
            } else {
                throw new Exception('ไม่สามารถสร้างไฟล์ backup ได้');
            }
            
        } catch (Exception $e) {
            $this->logger->error('Error creating backup: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Validate and repair file integrity
     */
    public function validateFileIntegrity($fileId = null) {
        try {
            $issues = [];
            
            if ($fileId) {
                $files = [$this->getFileById($fileId)];
            } else {
                $files = $this->db->fetchAll("SELECT * FROM uploadfiles ORDER BY file_id");
            }
            
            foreach ($files as $file) {
                if (!$file) continue;
                
                $fileIssues = [];
                
                // Check if physical file exists
                if (!file_exists($file['file_path'])) {
                    $fileIssues[] = 'Physical file missing';
                }
                
                // Check file size
                if (file_exists($file['file_path'])) {
                    $actualSize = filesize($file['file_path']);
                    if ($actualSize != $file['file_size']) {
                        $fileIssues[] = "Size mismatch: DB={$file['file_size']}, Actual=$actualSize";
                    }
                }
                
                // Check filename validity
                if (empty($file['original_filename'])) {
                    $fileIssues[] = 'Empty original filename';
                }
                
                // Check category validity
                if (!in_array($file['file_category'], ['HIS', 'F43'])) {
                    $fileIssues[] = 'Invalid category';
                }
                
                if (!empty($fileIssues)) {
                    $issues[] = [
                        'file_id' => $file['file_id'],
                        'filename' => $file['original_filename'],
                        'issues' => $fileIssues
                    ];
                }
            }
            
            return $issues;
            
        } catch (Exception $e) {
            $this->logger->error('Error validating file integrity: ' . $e->getMessage());
            return [];
        }
    }
}
