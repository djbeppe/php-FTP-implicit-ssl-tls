<?php
/**
 * FTP with Implicit SSL/TLS Class
 *
 * Simple wrapper for cURL functions to transfer an ASCII file over FTP with implicit SSL/TLS
 * https://github.com/djbeppe/php-FTP-implicit-ssl-tls
 * 
 * Licences How-To: https://choosealicense.com/licenses/
 * 
 * @category        Class - PHP FTP Implicit SSL Client
 * @author          Giuseppe Del Duca
 * @version         1.5
 * @license         BeerWare - Please offer me a beer if you find this software useful...
 *                            ...I know you'll save lots of headache/time/money, be grateful :-)
 * @donation        https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=FVWMLXE4KCFSE&source=url
 * 
 * @warranty        No warranty: I know it works, but use at your own risk!
 * 
 * @forked-from     https://github.com/nalindaDJ/php-FTP-implicit-ssl-tls/blob/master/class-ftp-implicit-ssl-tls.php
 * @other-authors   Max Rice / Damith Jayasinghe
 * 
 */



class cs_ftps_implicit {

	/** @var resource cURL resource handle */
	private $curl_handle;

	/** @var string cURL URL for upload */
	private $url;

	/** @var string cURL server host */
	private $server;

	/** @var string cURL initial path (ftp root) */
    private $initial_path;
    
    /** @var array cURL default opt, so we can reset opts before each command */
    private $curl_default_opt;

	/**
	 * Connect to FTP server over Implicit SSL/TLS
	 *
	 *
	 * @access public
	 * @since 1.0
	 * @param string $username
	 * @param string $password
	 * @param string $server
	 * @param int $port
	 * @param string $initial_path
	 * @param bool $passive_mode
	 * @throws Exception - blank username / password / port
	 * @return \FTP_Implicit_SSL
	 */
	public function __construct( $username, $password, $server, $port = 990, $initial_path = '', $passive_mode = false ) {

		// check for blank username
		if ( ! $username )
			throw new Exception( 'FTP Username is blank.' );

		// check for blank password
		if ( ! $password )
			throw new Exception( 'FTP Password is blank.' );

		// check for blank server
		if ( ! $server )
            throw new Exception( 'FTP Server is blank.' );
        else
            $this->server = $server;

		// check for blank port
		if ( ! $port )
			throw new Exception ( 'FTP Port is blank.', WC_XML_Suite::$text_domain );

        // set host/initial path - updated by cwd() or cd() functions
        $initial_path = '/' . ltrim($initial_path, '/'); // ensure path starts with slash (added if missing)
        $initial_path = rtrim($initial_path, '/') . '/'; // ensure path ends with trailing slash (added if missing)
        $this->initial_path = $initial_path;
        $this->url = "ftps://{$this->server}{$this->initial_path}";
        echo "\nCONNECT TO: ".$this->url."\n";

		// setup connection
		$this->curl_handle = curl_init();

		// check for successful connection
		if ( ! $this->curl_handle )
			throw new Exception( 'Could not initialize cURL.' );

		// connection options
		$options = array(
			CURLOPT_USERPWD        => $username . ':' . $password,
			CURLOPT_SSL_VERIFYPEER => false, // don't verify SSL
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_FTP_SSL        => CURLFTPSSL_ALL, // require SSL For both control and data connections
			CURLOPT_FTPSSLAUTH     => CURLFTPAUTH_DEFAULT, // let cURL choose the FTP authentication method (either SSL or TLS)
			CURLOPT_UPLOAD         => true,
			CURLOPT_PORT           => $port,
			CURLOPT_TIMEOUT        => 30,
		);

		// cURL FTP enables passive mode by default, so disable it by enabling the PORT command and allowing cURL to select the IP address for the data connection
		if ( ! $passive_mode )
			$options[ CURLOPT_FTPPORT ] = '-';

        // save default opts
        $this->curl_default_opt = $options;

        // set connection options
        // use foreach so useful errors can be caught instead of a generic "cannot set options" error with curl_setopt_array()
		foreach ( $options as $option_name => $option_value ) {

			if ( ! curl_setopt( $this->curl_handle, $option_name, $option_value ) )
				throw new Exception( sprintf( 'Could not set cURL option: %s', $option_name ) );
		}

    }

    /**
     * Reset cURL default options
     *  Call thix fx before each command to ensure curl use exactly the option needed
     */
    private function reset_curl_opt() {
        curl_reset($this->curl_handle);

        // reset connection options
        // use foreach so useful errors can be caught instead of a generic "cannot set options" error with curl_setopt_array()
		foreach ( $this->curl_default_opt as $option_name => $option_value ) {

			if ( ! curl_setopt( $this->curl_handle, $option_name, $option_value ) )
				throw new Exception( sprintf( 'Could not set cURL option: %s', $option_name ) );
		}        
    }
    
	/**
	 * Upload file to remote host
	 *
	 * @access public
	 * @since 1.5
     * @param string $local_file - local file to upload
	 * @param string $remote_name - remote file name to create
	 * @throws Exception - Open remote file failure or write data failure
	 */
	public function upload_file( $local_file, $remote_name=false ) {
        $this->reset_curl_opt();

        if(!$remote_name || !strlen($remote_name)) $remote_name = basename($local_file);

        echo "\nUPLOAD: ".$local_file." => ".$this->url.$remote_name."\n";

		// set destination
    	if ( ! curl_setopt( $this->curl_handle, CURLOPT_URL, $this->url . $remote_name ))
			throw new Exception ( "Could not set cURL file name: $remote_name" );

        if ($stream = fopen($local_file, 'r')) {
            curl_setopt($this->curl_handle, CURLOPT_UPLOAD, 1);
            curl_setopt($this->curl_handle, CURLOPT_INFILE, $stream);
        }

		// upload file
		if ( ! curl_exec( $this->curl_handle ) )
			throw new Exception( sprintf( 'Could not upload file. cURL Error: [%s] - %s', curl_errno( $this->curl_handle ), curl_error( $this->curl_handle ) ) );

		// close the stream handle
		fclose( $stream );
    }


	/**
	 * Write file into temporary memory and upload stream to remote file
	 *
	 * @access public
	 * @since 1.5
	 * @param string $remote_name - remote file name to create
	 * @param string $file_content - file content to upload
	 * @throws Exception - Open remote file failure or write data failure
	 */
	public function upload_stream( $file_content, $remote_name ) {
        $this->reset_curl_opt();
        echo "\nSTREAM: ".$file_content." => ".$this->url.$remote_name."\n";

		// set destination
    	if ( ! curl_setopt( $this->curl_handle, CURLOPT_URL, $this->url . $remote_name ))
			throw new Exception ( "Could not set cURL file name: $remote_name" );

		// open memory stream for writing
		$stream = fopen( 'php://temp', 'w+' );

		// check for valid stream handle
		if ( ! $stream )
			throw new Exception( 'Could not open php://temp for writing.' );

		// write file into the temporary stream
		fwrite( $stream, $file_content );

		// rewind the stream pointer
		rewind( $stream );

		// set the file to be uploaded
		if ( ! curl_setopt( $this->curl_handle, CURLOPT_INFILE, $stream ) )
			throw new Exception( "Could not load file $remote_name" );

		// upload file
		if ( ! curl_exec( $this->curl_handle ) )
			throw new Exception( sprintf( 'Could not upload file. cURL Error: [%s] - %s', curl_errno( $this->curl_handle ), curl_error( $this->curl_handle ) ) );

		// close the stream handle
		fclose( $stream );
    }    
    
  
	/**
     * Download remote file to the given location
     * 
     * @param string $remote_name - remote file name (path relative to cwd)
     * @param string $local_path - local destination path
     * @return boolean - true on success, false elsewhere
	 */
	public function download( $remote_name, $local_path='/tmp/'){
        $this->reset_curl_opt();
        echo "\nDOWNLOAD: ".$this->url.$remote_name." => ".$local_path."\n";

        $file = fopen("$local_path$remote_name", "w");
        
		curl_setopt( $this->curl_handle, CURLOPT_URL, $this->url . $remote_name);
		curl_setopt( $this->curl_handle, CURLOPT_UPLOAD, false);
		curl_setopt( $this->curl_handle, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt( $this->curl_handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt( $this->curl_handle, CURLOPT_FILE, $file);
        
		$result = curl_exec($this->curl_handle);
        fclose($file);
        
		if( strlen($result) ){
			return $result;
		} else {
			return false;
		}
    }

    /**
     * Navigate through remote filesystem
     * 
	 * @access public
	 * @since 1.5
     * @param string $new_path - remote path to change to (relative or absolute)
     * @return boolean - see cwd() function
     */
    public function cd( $new_path ) {
        $new_path = rtrim($new_path, '/') . '/'; // ensure path ends with trailing slash (added if missing)

        $destination = false;
        if(substr($new_path,0,1)=='/') { // absolute path
            $destination = $new_path;
            echo "\nCD: ".$new_path." => ".$destination." (goto absolute path)";
        }    
        else { // relative path
            switch($new_path) {
                case './':
                    echo "\nCD: ".$new_path." => ".$this->initial_path." (same folder)\n";
                    return true;
                    break;
                case '../':
                    $tmp_path = $this->initial_path;
                    $tmp_path = ltrim($tmp_path,'/');
                    $tmp_path = rtrim($tmp_path,'/');
                    $tmp_path = explode('/',$tmp_path);
                    array_pop($tmp_path);
                    $destination = '/'.implode('/',$tmp_path).'/';
                    echo "\nCD: ".$new_path." => ".$destination." (goto parent folder)";
                    break;
                default:
                    $destination = $this->initial_path . $new_path;
                    echo "\nCD: ".$new_path." => ".$destination." (goto child path)";
                    break;
            }
        }

        // go ahead with cwd function...
        return $this->cwd($destination);
    }
    
    /**
     * Change remote working dir
     * 
     * @param string $remote_path - remote path to change to (absolute path from ftp root!)
     * @return boolean - path (true) on success, false elsewhere
     */
    public function cwd( $new_path ) {
        $this->reset_curl_opt();

        // $this->url = "ftps://".$this->server."/".$this->initial_path."/".$remote_path;
        $new_path = '/' . ltrim($new_path, '/'); // ensure path starts with slash (added if missing)
        $new_path = rtrim($new_path, '/') . '/'; // ensure path ends with trailing slash (added if missing)
        if($new_path == '//') $new_path == '/'; // go to root folder
        $new_url = "ftps://{$this->server}{$new_path}";
        echo "\nCWD: ".$this->initial_path." => ".$new_path." ";
        if($new_url == $this->url) { // if new path == current path, no action needed
            echo "(same as current path, nothing to do)";
            return $new_url;
        }
        echo "\n";
        
        
        curl_setopt( $this->curl_handle, CURLOPT_URL, $new_url);
        curl_setopt( $this->curl_handle, CURLOPT_UPLOAD, false);
        curl_setopt( $this->curl_handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt( $this->curl_handle, CURLOPT_HEADER, false);
        
        $result = curl_exec( $this->curl_handle ); // or die( curl_error( $this->curl_handle ) );

        if($result===FALSE) { // on success returns string(0), so === needed
            return false; // user asked to cwd to non existent path
        }

        // cwd() succeded, return path to callcer
        $this->initial_path = $new_path;
        $this->url = "ftps://{$this->server}{$this->initial_path}";
        return $this->url;
    } 

    /**
     * Print working directory. Returns the current directory of the host. 
     * 
     * @return string - path
    */
    public function pwd() {
        return $this->initial_path;
    }


    /**
     * Move (or rename) file. If no path is specified, assume to rename in the cwd 
     * 
	 * @access public
	 * @since 1.5
     * @param string $old_file - file to be moved/renamed
     * @param string $new_file - new path/name to assign
     * @return boolean - true on success, false elsewhere
     */
    public function mv( $old_file, $new_file ) {
        echo "\nMV: ".$old_file." => ".$new_file;

        if($old_file == $new_file ) {
            echo " (are you sure? no action needed) ";
            return true;
        }
        echo "\n";

        $this->reset_curl_opt();

        curl_setopt( $this->curl_handle, CURLOPT_URL, $this->url);
        curl_setopt( $this->curl_handle, CURLOPT_UPLOAD, false);
        curl_setopt( $this->curl_handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt( $this->curl_handle, CURLOPT_HEADER, false);        
        curl_setopt( $this->curl_handle, CURLOPT_POSTQUOTE, array("RNFR $old_file", "RNTO $new_file"));
        // echo "\nRNFR $old_file => RNTO $new_file\n"; // DEBUG

        $result = curl_exec( $this->curl_handle ); // or die ( curl_error( $this->curl_handle ));
        if($result===FALSE) return false;

        return true;
    }

	/**
     * List files/folders in current working directory
     * 
	 * @return array of file/directory names
	 * @throws Exception (removed)
	 */
	public function remote_file_list(){
        $this->reset_curl_opt();

        echo "\nLS: ".$this->url."\n";
        
        /*
		if ( ! curl_setopt( $this->curl_handle, CURLOPT_URL, $this->url))
            throw new Exception ("Could not set cURL directory: $this->url");
        */
        curl_setopt( $this->curl_handle, CURLOPT_URL, $this->url);
		curl_setopt( $this->curl_handle, CURLOPT_UPLOAD, false);
		curl_setopt( $this->curl_handle, CURLOPT_FTPLISTONLY, true);
        curl_setopt( $this->curl_handle, CURLOPT_RETURNTRANSFER, true);
        // pre(curl_getinfo($this->curl_handle));
        
		$result = curl_exec( $this->curl_handle ); // or die ( curl_error( $this->curl_handle ));
        $files = explode("\n",trim($result));
        
        if($result===FALSE) { // on success returns string(0), so === needed
            return false; // user asked to list non existent path
        }

		if( count($files) ){
			return $files;
		} else {
			return array();
		}
    }    

	/**
	 * Get remote file size - (usefull to create a progress bar)
     * 
     * @param string $file_name - file to get size of
     * @return int - file size in bytes
	 */
	public function remote_file_size($file_name){
        echo "\nSIZE: ".$this->url . $file_name."\n";
		$size=0;
		curl_setopt( $this->curl_handle, CURLOPT_URL, $this->url . $file_name);
		curl_setopt( $this->curl_handle, CURLOPT_UPLOAD, false);
		curl_setopt( $this->curl_handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt( $this->curl_handle, CURLOPT_HEADER, true);
        curl_setopt( $this->curl_handle, CURLOPT_NOBODY, true);
        
		$data = curl_exec( $this->curl_handle);
        $size = curl_getinfo( $this->curl_handle, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        
		return $size;
    } 
    
	/**
     * Delete remote file
     * 
     * @param string $file_name - file (or folder) to be deleted
     * @return boolean - true on success, false elsewhere
     */    
    public function delete($file_name){
        $this->reset_curl_opt();

        echo "\nDELE: ".$this->url . $file_name."\n";
		curl_setopt( $this->curl_handle, CURLOPT_URL, $this->url . $file_name);
		curl_setopt( $this->curl_handle, CURLOPT_UPLOAD, false);
		curl_setopt( $this->curl_handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt( $this->curl_handle, CURLOPT_HEADER, false);
		//curl_setopt( $this->curl_handle, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt( $this->curl_handle, CURLOPT_QUOTE, array('DELE ' . $file_name ));
        
		$result = curl_exec( $this->curl_handle ); // or die( curl_error( $this->curl_handle ) );
        $files = explode("\n",trim($result));
        
        if($result===FALSE) { // on success returns string(0), so === needed
            return false; // user asked to delete non existent path/file
        }
		
		if( !in_array( $file_name, $files ) ){
			return true;
		} else {
			return false;
		}
	}
    
	/**
     * Create remote folder
     *  Note: folder name must ends with / (trailing slash), otherwise mkdir will not work (tipically returns error 550 (permission denied)
     * 
     * @param string $folder_name - folder to be deleted
     * @return boolean - folder path on success, false elsewhere    
	 */    
    public function mkdir($folder_name){
        $this->reset_curl_opt();
        

        // $folder_name = ltrim($folder_name, '/'); // ensure path starts with trailing slash (added if missing)
        $folder_name = rtrim($folder_name, '/') . '/'; // ensure path ends with trailing slash (added if missing)

        $destination = $this->url . $folder_name; // use relative path by default
        if(substr($folder_name,0,1)=='/') // absolute path, if params
            $destination = "ftps://{$this->server}{$folder_name}";

        echo "\nMKDIR: ".$destination."\n";



		curl_setopt( $this->curl_handle, CURLOPT_URL, $destination);
		curl_setopt( $this->curl_handle, CURLOPT_UPLOAD, false);
		curl_setopt( $this->curl_handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt( $this->curl_handle, CURLOPT_HEADER, false);
        curl_setopt( $this->curl_handle, CURLOPT_FTP_CREATE_MISSING_DIRS, true );
        
        $result = curl_exec( $this->curl_handle );

        if($result===FALSE) { // on success returns string(0), so === needed
            return false; // user asked to create folder in non existent path
        } else {
            return $this->url . $folder_name; // folder created
        }
	}

	/**
	 * Attempt to close cURL handle
	 *  Note - errors suppressed here as they are not useful
	 *
	 * @access public
	 * @since 1.0
	 */
	public function __destruct() {

		@curl_close( $this->curl_handle );
	}

}
