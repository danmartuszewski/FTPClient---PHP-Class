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

    /**
     * FTP login 
     * @var string 
     */
    private $username;
    /**
     * FTP password
     * @var string 
     */
    private $password;
    /**
     * FTP host without ftp:// prefix
     * @var type 
     */
    private $host;
    /**
     * FTP port
     * @var int 
     */
    private $port = 21;
    /**
     * Timeout for all subsequent network operations
     * @var int 
     */
    private $timeout = 90;
    /**
     * Determinate if use secure connection
     * @var bool 
     */
    private $secure = false;
    /**
     * URI setted by user
     * @var string
     */
    private $uri;
    /**
     * Path to directory within go after login
     * @var string
     */
    private $path;
    /**
     * Determinate if any error has uccured
     * @var bool
     */
    private $isError = false;
    /**
     * Determinate if login went without errors
     * @var bool
     */
    private $loginOk;
    /**
     * All logs
     * @var array
     */
    private $messages;
    /**
     * Connection mode
     * @var bool
     */
    private $passiveMode = false;
    /**
     * Connection to FTP server handler
     * @var resource
     */
    private $connectionHandler;
    /**
     * Files to upload in format $files['remoteFile'] => 'localFile'
     * @var array 
     */
    private $files = array();
    /**
     * Extensions of ASCII files
     * @var array 
     */
    private $asciiExtensions = array('txt', 'csv');


    public function __construct() {
        
    }


    /**
     * Extract all data from URI and set in the class properties
     * @param string $uri 
     */
    public function setUri($uri) {
        $this->uri = $uri;

        // Split FTP URI into:
        // $match[0] = ftp://username:password@sld.domain.tld/path1/path2/
        // $match[1] = username
        // $match[2] = password
        // $match[3] = sld.domain.tld
        // $match[4] = /path1/path2/
        preg_match('/ftp:\/\/(.*?):(.*?)@(.*?)(\/.*)/i', $uri, $match);
        $this->username = $match[1];
        $this->password = $match[2];
        $this->host = $match[3];
        $this->path = $match[4];
    }


    /**
     * Return all log messages
     * @return array
     */
    public function getMessages() {
        return $this->messages;
    }


    /**
     * Return last log message
     * @return string 
     */
    public function getMessage() {
        return $this->messages[count($this->messages) - 1];
    }


    /**
     * Connect and login to the FTP server.
     * @return bool
     */
    public function connect() {
        if( $this->secure ) {
            $this->connectionHandler = ftp_ssl_connect($this->host, $this->port, $this->timeout);
        } else {
            $this->connectionHandler = ftp_connect($this->host, $this->port, $this->timeout);
        }

        $loginRes = ftp_login($this->connectionHandler, $this->username, $this->password);

        if( (!$this->connectionHandler) || (!$loginRes) ) {
            $this->logMessage('FTP connection has failed!');
            $this->logMessage(sprintf('Attempted to connect to %s for user %s', $this->host, $this->username), true);

            return false;
        } else {
            $this->logMessage(sprintf('Connected to %s for user %s', $this->host, $this->username));
            $this->loginOk = true;
        }

        ftp_chdir($this->connectionHandler, $this->path);

        return true;
    }


    /**
     * Log all messages into property
     * @param string $message
     * @param bool $isError optional
     */
    private function logMessage($message, $isError = null) {
        $this->messages[] = $message;
        if( $isError ) {
            $this->isError = true;
        }
    }


    /**
     * Show if error was occured
     * @return bool
     */
    public function isError() {
        return $this->isError;
    }


    /**
     * Get list of files on the sarver
     * @param string $directory optional
     * @param bool $recursive optional
     * @return array
     */
    public function ls($directory = null, $recursive = true) {
        if( $directory ) {
            $this->path = $directory;
        }
        return ftp_rawlist($this->connectionHandler, $this->path, $recursive);
    }


    /**
     * Add file to stack of files to upload
     * @param string $localFile
     * @param string $remoteFile
     */
    public function addFile($localFile, $remoteFile = null) {
        if( !file_exists($localFile) ) {
            $this->logMessage(sprintf('File %s does not exist.', $localFile), true);

            return false;
        }
        if( !$remoteFile ) {
            $remoteFile = basename($localFile);
        }
        $this->files[$remoteFile] = $localFile;
    }


    /**
     * Set ASCII files extensions 
     * (use to determinate filetype during upload)
     * @param array $extensions 
     */
    public function setAsciiExtensions($extensions) {
        $this->asciiExtensions = $extensions;
    }


    /**
     * Add extension to ASCII file extensions
     * (use to determinate filetype during upload)
     * @param string $extension 
     */
    public function addAsciiExtension($extension) {
        $this->asciiExtensions[] = $extension;
    }


    /**
     * Determinate if file is ASCII file. Based on
     * filename extension and $asciiExtensions property.
     * @param string $filename
     * @return bool 
     */
    private function isAsciiFile($filename) {
        if( in_array(array_pop(explode('.', $filename)), $this->asciiExtensions) ) {
            return true;
        }
        return false;
    }


    /**
     * Upload files from $files property
     */
    public function upload() {
        foreach( $this->files as $remote => $local ) {
            if( $this->isAsciiFile($local) ) {
                $mode = FTP_ASCII;
            } else {
                $mode = FTP_BINARY;
            }
            if( ftp_put($this->connectionHandler, $remote, $local, $mode) ) {
                $this->logMessage(sprintf('File %s uploaded as %s', $local, $remote));
            } else {
                $this->logMessage(sprintf('Failed uploading file "%s"!', $local), true);
            }
        }
    }


    /**
     * Make dir $directory on FTP 
     * @param string $directory
     * @return bool 
     */
    public function mkdir($directory) {
        if( ftp_mkdir($this->connectionHandler, $directory) ) {
            $this->logMessage(sprintf('Directory %s created', $directory));

            return true;
        }
        $this->logMessage(sprintf('Failed creating directory "%s"!', $directory));

        return false;
    }

}

?>
