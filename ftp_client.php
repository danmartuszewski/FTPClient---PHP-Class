<?php

/**
 * FTPClient class is a imlementation of FTP protocol
 * for simple use in PHP scripts
 * 
 * @author Daniel Martuszewski <daniel10a at o2.pl>
 * @filesource ftp_client.php
 * 
 */
class FTPClient {

    private $username;
    private $password;
    private $host;
    private $port = 21;
    private $timeout = 90;
    private $secure = false;
    private $uri;
    private $path;
    private $isError = false;
    private $loginOk;
    private $messages;
    private $passiveMode = false;
    private $connectionHandler;


    public function __construct() {
        
    }


    public function setUri($uri) {
        $this->uri = $uri;

        // Split FTP URI into:
        // $match[0] = ftp://username:password@sld.domain.tld/path1/path2/
        // $match[1] = ftp://
        // $match[2] = username
        // $match[3] = password
        // $match[4] = sld.domain.tld
        // $match[5] = /path1/path2/
        preg_match('/ftp:\/\/(.*?):(.*?)@(.*?)(\/.*)/i', $uri, $match);
        
        $this->username = $match[2];
        $this->password = $match[3];
        $this->host     = $match[4];
        $this->path     = $match[5];
    }


    public function getMessage() {
        return $this->messages[count($this->messages) - 1];
    }


    public function connect() {
        if ($this->secure) {
            $this->connectionHandler = ftp_ssl_connect($this->host, $this->port, $this->timeout);
        } else {
            $this->connectionHandler = ftp_connect($this->host, $this->port, $this->timeout);
        }
        
        $loginRes = ftp_login($this->connectionHandler, $this->username, $this->password);
        
        if( (!$this->connectionHandler) || (!$loginRes)) {
            $this->logMessage('FTP connection has failed!');
            $this->logMessage(sprintf('Attempted to connect to %s for user %s', $this->host, $this->username), true);
            
            return false;
        } else {
            $this->logMessage(sprintf('Connected to %s for user %s'), $this->host, $this->username);
            $this->loginOk = true;
        }
        
        ftp_chdir($this->connectionHandler, $this->path);
        
        return true;
    }


    private function logMessage($message, $isError) {
        $this->messages[] = $message;
        if ($isError) {
            $this->isError = true;
        }
    }


    public function isError() {
        return $this->isError;
    }

}

?>
