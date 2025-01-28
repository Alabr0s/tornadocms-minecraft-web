<?php
class WebSender {
    private $host;
    private $port;
    private $password;
    private $timeout;
    private $socket;
    
    public function __construct($host, $port, $password, $timeout = 30) {
        $this->host = gethostbyname($host);
        $this->port = $port;
        $this->password = $password;
        $this->timeout = $timeout;
    }
    
    public function __destruct() {
        if ($this->socket) {
            $this->disconnect();
        }
    }
    
    private function connect() {
        error_log("WebSender - Connecting to {$this->host}:{$this->port}");
        
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
        if (!$this->socket) {
            error_log("WebSender - Connection failed: $errno - $errstr");
            return false;
        }
        
        // Protokol başlangıcı
        $this->writeRawByte(1);
        
        // Şifre doğrulama
        $challenge = $this->readRawInt();
        $hash = hash("SHA512", $challenge . $this->password);
        $this->writeString($hash);
        
        $response = $this->readRawInt();
        $success = ($response == 1);
        
        error_log("WebSender - Auth " . ($success ? "successful" : "failed"));
        return $success;
    }
    
    public function sendCommand($command) {
        try {
            if (!$this->connect()) {
                throw new Exception("Bağlantı başarısız");
            }
            
            error_log("WebSender - Sending command: $command");
            
            // Komut gönder
            $this->writeRawByte(2);
            $this->writeString(base64_encode($command));
            
            $response = $this->readRawInt();
            $success = ($response == 1);
            
            error_log("WebSender - Command " . ($success ? "successful" : "failed"));
            
            return [
                'success' => $success,
                'response' => $success ? 'OK' : 'Failed'
            ];
            
        } catch (Exception $e) {
            error_log("WebSender Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        } finally {
            $this->disconnect();
        }
    }
    
    private function disconnect() {
        if (!$this->socket) return;
        
        try {
            $this->writeRawByte(3);
        } catch (Exception $e) {
            error_log("WebSender - Disconnect error: " . $e->getMessage());
        }
        
        @fclose($this->socket);
        $this->socket = null;
    }
    
    private function writeRawInt($i) {
        @fwrite($this->socket, pack("N", $i), 4);
    }
    
    private function writeRawByte($b) {
        @fwrite($this->socket, strrev(pack("C", $b)));
    }
    
    private function writeString($string) {
        $array = str_split($string);
        $this->writeRawInt(count($array));
        foreach ($array as $cur) {
            $v = ord($cur);
            $this->writeRawByte((0xff & ($v >> 8)));
            $this->writeRawByte((0xff & $v));
        }
    }
    
    private function readRawInt() {
        $a = $this->readRawByte();
        $b = $this->readRawByte();
        $c = $this->readRawByte();
        $d = $this->readRawByte();
        $i = ((($a & 0xff) << 24) | (($b & 0xff) << 16) | (($c & 0xff) << 8) | ($d & 0xff));
        if ($i > 2147483648)
            $i -= 4294967296;
        return $i;
    }
    
    private function readRawByte() {
        $up = unpack("Ci", fread($this->socket, 1));
        $b = $up["i"];
        if ($b > 127)
            $b -= 256;
        return $b;
    }
    
    public static function executeCommands($serverId, $commands, $playerName) {
        global $db;
        
        try {
            error_log("WebSender - Starting command execution for server ID: " . $serverId);
            error_log("WebSender - Player name: " . $playerName); // Debug için
            
            // Sunucu bilgilerini al
            $stmt = $db->prepare("SELECT * FROM websender_servers WHERE id = ? AND status = 1");
            $stmt->execute([$serverId]);
            $server = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$server) {
                throw new Exception('Sunucu bulunamadı veya aktif değil (ID: ' . $serverId . ')');
            }
            
            $ws = new WebSender($server['host'], (int)$server['port'], $server['password']);
            
            if (empty($commands)) {
                throw new Exception('Çalıştırılacak komut bulunamadı');
            }
            
            $commands = explode("\n", $commands);
            $results = [];
            
            foreach ($commands as $command) {
                $command = trim($command);
                if (empty($command)) continue;
                
                // Değişken değiştirme işlemleri
                $replacements = [
                    '{player}' => $playerName,
                    '{playername}' => $playerName,
                    '{username}' => $playerName,
                    '{name}' => $playerName,
                    '{PLAYER}' => $playerName,
                    '{PLAYERNAME}' => $playerName,
                    '{USERNAME}' => $playerName,
                    '{NAME}' => $playerName
                ];
                
                $command = str_replace(array_keys($replacements), array_values($replacements), $command);
                error_log("WebSender - Executing command after replacement: " . $command); // Debug için
                
                $result = $ws->sendCommand($command);
                if (!$result['success']) {
                    throw new Exception($result['error'] ?? 'Komut çalıştırma hatası');
                }
                
                $results[] = $result;
            }
            
            return [
                'success' => true,
                'results' => $results
            ];
            
        } catch (Exception $e) {
            error_log("WebSender Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
} 