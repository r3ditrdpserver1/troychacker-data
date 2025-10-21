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
    
    public function syncKeyToGitHub($key_data) {
        // 1. Mevcut db.json'ı al
        $current_data = $this->getGitHubDB();
        
        // 2. Yeni key'i ekle
        $current_data['keys'][] = $key_data;
        
        // 3. GitHub'a yükle
        return $this->updateGitHubDB($current_data);
    }
    
    public function syncUpdateToGitHub($update_data) {
        $current_data = $this->getGitHubDB();
        $current_data['updates'][] = $update_data;
        return $this->updateGitHubDB($current_data);
    }
    
    private function getGitHubDB() {
        $url = "https://api.github.com/repos/{$this->owner}/{$this->repo}/contents/api/db.json";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: token ' . $this->token,
                'User-Agent: PHP-Script'
            ]
        ]);
        
        $response = curl_exec($ch);
        $data = json_decode($response, true);
        
        if (isset($data['content'])) {
            return json_decode(base64_decode($data['content']), true);
        }
        
        return ['keys' => [], 'updates' => []];
    }
    
    private function updateGitHubDB($data) {
        $url = "https://api.github.com/repos/{$this->owner}/{$this->repo}/contents/api/db.json";
        
        // Önce mevcut dosya bilgisini al
        $current_file = $this->getGitHubDB();
        
        $post_data = [
            'message' => 'Sync: ' . date('Y-m-d H:i:s'),
            'content' => base64_encode(json_encode($data, JSON_PRETTY_PRINT)),
            'sha' => $this->getFileSHA()
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
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        return json_decode($response, true);
    }
    
    private function getFileSHA() {
        $url = "https://api.github.com/repos/{$this->owner}/{$this->repo}/contents/api/db.json";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: token ' . $this->token,
                'User-Agent: PHP-Script'
            ]
        ]);
        
        $response = curl_exec($ch);
        $data = json_decode($response, true);
        
        return $data['sha'] ?? null;
    }
}
?>
