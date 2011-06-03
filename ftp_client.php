<?php

require_once('FirePHPCore/fb.php');

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
    /**
     * Language
     * @var string 
     */
    protected $lang = 'eng';
    /**
     * Array with log messages
     * @var array 
     */
    public $localization = array(
        'eng' => array(
            'failedConnection' => 'FTP connection has failed!',
            'failedLogin' => 'Failed login to %s for user %s!',
            'successLogin' => 'Connected to %s for user %s',
            'fileNotExists' => 'File %s does not exist.',
            'successUpload' => 'File %s uploaded as %s',
            'failedUpload' => 'Failed uploading file "%s"!',
            'successMkdir' => 'Directory %s created',
            'failedMkdir' => 'Failed creating directory "%s"!',
            'currentDir' => 'Current directory %s',
            'failedChangingDir' => 'Failed changing directory to %s',
            'removeDir' => 'Directory %s removed',
            'failedRemovingDir' => 'Failed removing directory %s',
            'removeFile' => 'File %s removed',
            'failedRemovingFile' => 'Failed removing file %s',
            'exec' => 'Exec: %s',
            'failedExec' => 'Failed to exec: %s',
            'chmod' => 'Change mode of file %s to %d',
            'failedChmod' => 'Failed changing mode of file %s to %d',
            'passive' => 'Switch to passive mode',
            'active' => 'Switch to active mode',
            'failedMode' => 'Failed changing mode',
            'successRename' => 'Rename %s to %s',
            'failedRenaming' => 'Failed renaming file %s to %s',
            'successDownload' => 'Downloaded %s to %s',
            'failedDownloading' => 'Failed downloading %s'
        )
    );

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
     * Return translation from $localization array
     * @param string $logName
     * @return string 
     */
    private function getLog($logName) {
        fb::log($this->localization);
        if( isset($this->localization[$this->lang][$logName]) ) {
            return $this->localization[$this->lang][$logName];
        } elseif( isset($this->localization['eng'][$logName]) ) {
            return $this->localization['eng'][$logName];
        }
        return '';
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
        if( (!$this->connectionHandler ) ) {
            $this->logMessage($this->getLog('failedConnection'));

            return false;
        }

        $loginRes = ftp_login($this->connectionHandler, $this->username, $this->password);

        if( (!$loginRes ) ) {
            $this->logMessage(sprintf($this->getLog('failedLogin'), $this->host, $this->username), true);

            return false;
        } else {
            $this->logMessage(sprintf($this->getLog('successLogin'), $this->host, $this->username));
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
     * Turn on using secure (SSL) connection.
     * Use it before connect();
     * @param bool $secure 
     */
    public function setSecure($secure) {
        $this->secure = $secure;
    }


    /**
     * Long list of files on the sarver
     * @param string $directory optional
     * @param bool $recursive optional
     * @return mixed Array of filenames or false 
     */
    public function rawlist($directory = null, $recursive = true) {
        if( !$directory ) {
            $directory = $this->path;
        }
        return ftp_rawlist($this->connectionHandler, $directory, $recursive);
    }


    /**
     * Alias tp rawlist()
     */
    public function lsl($directory = null, $recursive = true) {
        return $this->rawlist($directory, $recursive);
    }


    /**
     * List of files on the FTP server
     * @param type $directory
     * @return mixed Array of filenames or false 
     */
    public function nlist($directory = null) {
        if( !$directory ) {
            $directory = $this->path;
        }
        return ftp_nlist($this->connectionHandler, $directory);
    }


    /**
     * Alias to nlist()
     */
    public function ls($directory = null) {
        return $this->nlist($directory);
    }


    /**
     * Add file to stack of files to upload
     * @param string $localFile
     * @param string $remoteFile
     */
    public function addFile($localFile, $remoteFile = null) {
        if( !file_exists($localFile) ) {
            $this->logMessage(sprintf($this->getLog('fileNotExists'), $localFile), true);

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
        $arr = explode('.', $filename);
        if( in_array(array_pop($arr), $this->asciiExtensions) ) {
            return true;
        }
        return false;
    }


    /**
     * Upload files from $files property
     * @param string $localFile
     * @param string $remoteFile 
     */
    public function put($localFile = null, $remoteFile = null) {
        if( $localFile ) {
            $this->addFile($localFile, $remoteFile);
        }
        foreach( $this->files as $remote => $local ) {
            if( $this->isAsciiFile($local) ) {
                $mode = FTP_ASCII;
            } else {
                $mode = FTP_BINARY;
            }
            if( ftp_put($this->connectionHandler, $remote, $local, $mode) ) {
                $this->logMessage(sprintf($this->getLog('successUpload'), $local, $remote));
            } else {
                $this->logMessage(sprintf($this->getLog('failedUpload'), $local), true);
            }
        }
    }


    /**
     * Alias to put
     */
    public function upload($localFile = null, $remoteFile = null) {
        $this->put($localFile, $remoteFile);
    }


    /**
     * Make dir $directory on FTP 
     * @param string $directory
     * @return bool 
     */
    public function mkdir($directory) {
        if( ftp_mkdir($this->connectionHandler, $directory) ) {
            $this->logMessage(sprintf($this->getLog('successMkdir'), $directory));

            return true;
        }
        $this->logMessage(sprintf($this->getLog('failedMkdir'), $directory));

        return false;
    }


    /**
     * Change current directory on FTP server
     * @param string $path
     * @return bool 
     */
    public function chdir($path) {
        if( ftp_chdir($this->connectionHandler, $path) ) {
            $this->path = $this->pwd();
            $this->logMessage(sprintf($this->getLog('currentDir'), $this->path));

            return true;
        }

        $this->logMessage(sprintf($this->getLog('failedChangingDir'), $path), true);

        return false;
    }


    /**
     * Alias to chdir
     */
    public function cd($path) {
        return $this->chdir($path);
    }


    /**
     * Return current path
     * @return string 
     */
    public function pwd() {
        return ftp_pwd($this->connectionHandler);
    }


    /**
     * Remove directory from FTP
     * @param string $directory This must be either an absolute or relative path to an empty directory.
     * @return bool 
     */
    public function rmdir($directory) {
//        if(!in_array($directory[0], array('/', '\') ) {

        if( ftp_rmdir($this->connectionHandler, $directory) ) {
            $this->logMessage(sprintf($this->getLog('removeDir'), $directory));

            return true;
        }
        $this->logMessage(sprintf($this->getLog('failedRemovingDir'), $directory), true);

        return false;
    }


    /**
     * Remove file from FTP
     * @param string $file
     * @return bool
     */
    public function delete($file) {
        if( ftp_delete($this->connectionHandler, $file) ) {
            $this->logMessage(sprintf($this->getLog('removeFile'), $file));

            return true;
        }
        $this->logMessage(sprintf($this->getLog('failedRemovingFile'), $file), true);

        return false;
    }


    /**
     * Execute command on the FTP server
     * @param string $command
     * @return mixed Result of command or false;
     */
    public function exec($command) {
        $result = ftp_exec($this->connectionHandler, $command);
        if( $result ) {
            $this->logMessage(sprintf($this->getLog('exec'), $command));

            return $result;
        }
        $this->logMessage(sprintf($this->getLog('failedExec'), $command), true);

        return false;
    }


    /**
     * Set permissions on the specified remote file.
     * @param int $mode New permissions. Must be octal value.
     * @param string $filename
     * @return mixed New mode of file or false 
     */
    public function chmod($mode, $filename) {
        if( ftp_chmod($this->connectionHandler, $mode, $filename) ) {
            $this->logMessage(sprintf($this->getLog('chmod'), $filename, decoct($mode)));

            return true;
        }
        $this->logMessage(sprintf($this->getLog('failedChmod'), $filename, decoct($mode)));

        return false;
    }


    /**
     * Last modify time to file. Does not work with directories!
     * @param string $filename
     * @return mixed Last access time to $filename or false 
     */
    public function mdtm($filename) {
        $result = ftp_mdtm($this->connectionHandler, $filename);
        if( $result === -1 ) {
            return false;
        }
        return $result;
    }


    /**
     * Switch on/off passive mode
     * @param bool $mode optional Default true
     * @return bool 
     */
    public function pasv($mode = true) {
        if( ftp_pasv($this->connectionHandler, $mode) ) {
            $log = $mode ? 'passive' : 'active';
            $this->logMessage($this->getLog($log));
            $this->passiveMode = true;

            return true;
        }
        $this->logMessage($this->getLog('failedMode'), true);

        return false;
    }


    /**
     * Rename file or dictionary on the FTP server
     * @param string $oldname
     * @param string $newname 
     */
    public function rename($oldname, $newname) {
        if( ftp_rename($this->connectionHandler, $oldname, $newname) ) {
            $this->logMessage(sprintf($this->getLog('successRename'), $oldname, $newname));

            return true;
        }
        $this->logMessage(sprintf($this->getLog('failedRenaming'), $oldname, $newname), true);

        return false;
    }


    /**
     * Return filesize in chosen unit
     * @param string $filename
     * @param string $unit optional Default is B (bytes). Can be 'b', 'Kb', 'B', 'KB', 'Mb', 'MB', 'Gb', 'GB'
     * @return mixed Size of file or false 
     */
    public function size($filename, $unit = 'B') {
        $bytes = ftp_size($this->connectionHandler, $filename);
        if( $bytes === -1 ) {
            return false;
        }
        switch ($unit) {
            case 'b':
                $size = $bytes * 8;
                break;
            case 'Kb':
                $size = $bytes / 8 / 1024;
                break;
            case 'KB':
                $size = $bytes / 1024;
                break;
            case 'Mb':
                $size = $bytes * 8 / 1024 / 1024;
                break;
            case 'MB':
                $size = $bytes / 1024 / 1024;
                break;
            case 'Gb':
                $size = $bytes * 8 / 1024 / 1024 / 1024;
                break;
            case 'GB':
                $size = $bytes / 1024 / 1024 / 1024;
                break;
            default:
                $size = $bytes;
                break;
        }
        return $size;
    }


    /**
     * Returns the system type identifier of the remote FTP server.
     * @return mixed Remote system type or false
     */
    public function systype() {
        return ftp_systype($this->connectionHandler);
    }


    /**
     * Downloads file from the FTP server
     * @param string $remoteFile
     * @param string $localFile
     * @return bool 
     */
    public function get($remoteFile, $localFile = null) {
        if( !$localFile ) {
            $localFile = basename($remoteFile);
        }
        if( $this->isAsciiFile($localFile) ) {
            $mode = FTP_ASCII;
        } else {
            $mode = FTP_BINARY;
        }
        if( ftp_get($this->connectionHandler, $localFile, $remoteFile, $mode) ) {
            $this->logMessage(sprintf($this->getLog('successDownload'), $remoteFile, $localFile));
            return true;
        }
        $this->logMessage(sprintf($this->getLog('failedDownloading'), $remoteFile));

        return false;
    }


    /**
     * Alias to get()
     */
    public function download($remoteFile, $localFile = null) {
        return $this->get($remoteFile, $localFile);
    }

}

?>
