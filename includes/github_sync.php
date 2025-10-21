<?php
class GitHubSync {
    private $token;
    private $owner;
    private $repo;
    
    public function __construct() {
        $this->token = GITHUB_TOKEN;
        $this->owner = GITHUB_OWNER;
        $this->repo = GITHUB_REPO;
    }
    
    /**
     * Key'i GitHub'a sync et
     */
    public function syncKeyToGitHub($key_data) {
        try {
            // 1. Mevcut db.json'ı al
            $current_data = $this->getGitHubDB();
            
            // 2. Yeni key'i ekle (aynı key varsa üzerine yazma)
            $key_exists = false;
            foreach ($current_data['keys'] as &$key) {
                if ($key['key_code'] === $key_data['key_code']) {
                    $key = $key_data; // Update existing key
                    $key_exists = true;
                    break;
                }
            }
            
            if (!$key_exists) {
                $current_data['keys'][] = $key_data;
            }
            
            // 3. GitHub'a yükle
            $result = $this->updateGitHubDB($current_data);
            
            return [
                'success' => isset($result['content']),
                'message' => isset($result['content']) ? 'GitHub sync başarılı' : 'GitHub sync başarısız'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Sync hatası: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Güncellemeyi GitHub'a sync et
     */
    public function syncUpdateToGitHub($update_data) {
        try {
            $current_data = $this->getGitHubDB();
            
            // Aynı versiyon varsa üzerine yaz
            $update_exists = false;
            foreach ($current_data['updates'] as &$update) {
                if ($update['version'] === $update_data['version']) {
                    $update = $update_data;
                    $update_exists = true;
                    break;
                }
            }
            
            if (!$update_exists) {
                $current_data['updates'][] = $update_data;
            }
            
            $result = $this->updateGitHubDB($current_data);
            
            return [
                'success' => isset($result['content']),
                'message' => isset($result['content']) ? 'Güncelleme sync başarılı' : 'Güncelleme sync başarısız'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Güncelleme sync hatası: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * GitHub'dan db.json'ı getir
     */
    private function getGitHubDB() {
        $url = "https://api.github.com/repos/{$this->owner}/{$this->repo}/contents/api/db.json";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: token ' . $this->token,
                'User-Agent: PHP-Script',
                'Accept: application/vnd.github.v3+json'
            ],
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            if (isset($data['content'])) {
                return json_decode(base64_decode($data['content']), true);
            }
        }
        
        // Dosya yoksa veya ulaşılamazsa boş data döndür
        return [
            'keys' => [],
            'updates' => [],
            'settings' => [
                'high_value_threshold' => HIGH_VALUE_THRESHOLD,
                'auto_send_delay' => AUTO_SEND_DELAY_MINUTES
            ],
            'last_sync' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * GitHub'a db.json'ı yükle
     */
    private function updateGitHubDB($data) {
        $url = "https://api.github.com/repos/{$this->owner}/{$this->repo}/contents/api/db.json";
        
        // Önce mevcut dosyanın SHA'sını al
        $current_info = $this->getGitHubDB();
        $sha = $this->getFileSHA();
        
        $post_data = [
            'message' => 'Auto-sync: ' . date('Y-m-d H:i:s'),
            'content' => base64_encode(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            'sha' => $sha
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => json_encode($post_data),
            CURLOPT_HTTPHEADER => [
                'Authorization: token ' . $this->token,
                'User-Agent: PHP-Script',
                'Content-Type: application/json',
                'Accept: application/vnd.github.v3+json'
            ],
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    /**
     * Dosyanın SHA değerini al
     */
    private function getFileSHA() {
        $url = "https://api.github.com/repos/{$this->owner}/{$this->repo}/contents/api/db.json";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: token ' . $this->token,
                'User-Agent: PHP-Script'
            ],
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $data = json_decode($response, true);
        curl_close($ch);
        
        return $data['sha'] ?? null;
    }
    
    /**
     * Mevcut tüm key'leri GitHub'a sync et
     */
    public function syncAllKeysToGitHub() {
        global $conn;
        
        // Local database'den tüm key'leri al
        $sql = "SELECT key_code, expiry_date, is_frozen, hardware_id, created_at FROM `keys`";
        $result = mysqli_query($conn, $sql);
        
        $keys_data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $keys_data[] = [
                'key_code' => $row['key_code'],
                'expiry_date' => $row['expiry_date'],
                'is_frozen' => (bool)$row['is_frozen'],
                'hardware_id' => $row['hardware_id'],
                'created_at' => $row['created_at'],
                'created_by' => 'admin' // Varsayılan değer
            ];
        }
        
        // GitHub'a sync et
        $current_data = $this->getGitHubDB();
        $current_data['keys'] = $keys_data;
        
        return $this->updateGitHubDB($current_data);
    }
}
?>
