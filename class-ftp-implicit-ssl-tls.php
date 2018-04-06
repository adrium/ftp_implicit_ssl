<?php

/**
 * FTP with Implicit SSL/TLS Class
 *
 * Simple wrapper for cURL functions to transfer an ASCII file over FTP with implicit SSL/TLS
 */
class FTP_Implicit_SSL {

	/** @var resource cURL resource handle */
	private $curl_handle;

	/** @var string cURL URL for upload */
	private $url;

	private $err;

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
	 * @param array $options - custom curl options
	 * @throws InvalidArgumentException - blank username / password / port
	 * @throws RuntimeException - curl errors
	 * @return \FTP_Implicit_SSL
	 */
	public function __construct( $username, $password, $server, $port = 990, $initial_path = '', $passive_mode = true, array $options = array() ) {

		// check libcurl version
		$versioninfo = curl_version();
		$versionnumber = $versioninfo['version_number'];

		if ( $versionnumber < 0x072200 )
			throw new RuntimeException( 'Require at least libcurl 7.34.0' );

		// check for blank username
		if ( ! $username )
			throw new InvalidArgumentException( 'FTP Username is blank.' );

		// don't check for blank password (highly-questionable use case, but still)

		// check for blank server
		if ( ! $server )
			throw new InvalidArgumentException( 'FTP Server is blank.' );

		// check for blank port
		if ( ! $port )
			throw new InvalidArgumentException ( 'FTP Port is blank.', WC_XML_Suite::$text_domain );

		// set host/initial path
		$initial_path = trim($initial_path, '/');
		$this->url = "ftps://{$server}/{$initial_path}";

		// setup connection
		$this->curl_handle = curl_init();

		// check for successful connection
		if ( ! $this->curl_handle )
			throw new RuntimeException( 'Could not initialize cURL.' );

		// connection options
		$defaults = array(
			CURLOPT_USERPWD        => $username . ':' . $password,
			CURLOPT_USE_SSL        => CURLUSESSL_ALL,
			CURLOPT_UPLOAD         => true,
			CURLOPT_PORT           => $port,
			CURLOPT_TIMEOUT        => 30,
		);

		$options = $options + $defaults;

		// cURL FTP enables passive mode by default, so disable it by enabling the PORT command and allowing cURL to select the IP address for the data connection
		if ( ! $passive_mode )
			$options[ CURLOPT_FTPPORT ] = '-';

		// set connection options, use foreach so useful errors can be caught instead of a generic "cannot set options" error with curl_setopt_array()
		foreach ( $options as $option_name => $option_value ) {

			if ( curl_setopt( $this->curl_handle, $option_name, $option_value ) !== true )
				throw new RuntimeException( sprintf( 'Could not set cURL option: %s', $option_name ) );
		}

	}

	/**
	 * Write file into temporary memory and upload stream to remote file
	 *
	 * @access public
	 * @since 1.0
	 * @param string $file_name - remote file name to create
	 * @param string $file - file content to upload
	 * @throws RuntimeException - Open remote file failure or write data failure
	 */
	public function upload( $file_name, $file ) {
		// set file name
		$file_name = trim($file_name, '/');
		curl_setopt( $this->curl_handle, CURLOPT_URL, "{$this->url}/{$file_name}" );
		// set the file to be uploaded
		$fp = fopen ($file, "r");
		curl_setopt( $this->curl_handle, CURLOPT_INFILE, $fp);
		curl_setopt($this->curl_handle, CURLOPT_INFILESIZE, filesize($file));
		// upload file
		if ( curl_exec( $this->curl_handle ) === false )
			throw new RuntimeException( sprintf( 'Could not upload file. cURL Error: [%s] - %s', curl_errno( $this->curl_handle ), curl_error( $this->curl_handle ) ) );
	}

	/**
	 * @param string $dir_name - remote directory name to list
	 * @return array array of file names
	 * @throws RuntimeException
	 */
	public function ftplist($dir_name){

		$dir_name = trim($dir_name, '/');
		if ( curl_setopt( $this->curl_handle, CURLOPT_URL, "{$this->url}/{$dir_name}/") !== true )
			throw new RuntimeException ("Could not set cURL directory: {$this->url}/{$dir_name}");

			curl_setopt( $this->curl_handle, CURLOPT_UPLOAD, false);
			curl_setopt( $this->curl_handle,CURLOPT_FTPLISTONLY,1);
			curl_setopt( $this->curl_handle, CURLOPT_RETURNTRANSFER, 1);

			$result = curl_exec($this->curl_handle);
			$files = explode("\n",trim($result));
			if( count($files) ){
				return $files;
			} else {
				return array();
			}

			//or die ($this->err = curl_error( $this->curl_handle ));
	}


	/**
	 * Download file from FTPS default directory
	 *
	 * @param $file_name
	 * @return string
	 */
	public function download($file_name,$local_path='/'){
		$file_name = trim($file_name, '/');
		$local_path = rtrim($local_path, '/');
		$file = basename($file_name);
		$fp = fopen("{$local_path}/{$file}", "w");
		curl_setopt( $this->curl_handle, CURLOPT_URL, "{$this->url}/{$file_name}");
		curl_setopt( $this->curl_handle, CURLOPT_UPLOAD, false);
		curl_setopt( $this->curl_handle, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt( $this->curl_handle, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt( $this->curl_handle, CURLOPT_FILE, $fp);
		curl_setopt( $this->curl_handle, CURLOPT_CUSTOMREQUEST, "RETR $file_name" );

		$result = curl_exec($this->curl_handle);
		fclose($fp);

		if ( $result === false )
			throw new RuntimeException( sprintf( 'Could not download file. cURL Error: [%s] - %s', curl_errno( $this->curl_handle ), curl_error( $this->curl_handle ) ) );

		if( strlen($result) ){
			return $result;
		} else {
			return "";
		}

	}

	public function remote_file_size($file_name){
		$file_name = trim($file_name, '/');
		curl_setopt( $this->curl_handle, CURLOPT_URL, "{$this->url}/{$file_name}");
		curl_setopt( $this->curl_handle, CURLOPT_UPLOAD, false);
		curl_setopt( $this->curl_handle, CURLOPT_RETURNTRANSFER, TRUE);
    		curl_setopt( $this->curl_handle, CURLOPT_HEADER, TRUE);
    		curl_setopt( $this->curl_handle, CURLOPT_NOBODY, TRUE);

     		$data = curl_exec( $this->curl_handle);
     		$size = curl_getinfo( $this->curl_handle, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

		if ( $data === false )
			throw new RuntimeException( sprintf( 'Could not get file size. cURL Error: [%s] - %s', curl_errno( $this->curl_handle ), curl_error( $this->curl_handle ) ) );

     		return $size;
	}

	public function delete($file_name){
		$file_name = trim($file_name, '/');
		curl_setopt( $this->curl_handle, CURLOPT_URL, "{$this->url}/{$file_name}");
		curl_setopt( $this->curl_handle, CURLOPT_UPLOAD, false);
		curl_setopt( $this->curl_handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt( $this->curl_handle, CURLOPT_HEADER, false);
		//curl_setopt( $this->curl_handle, CURLOPT_CUSTOMREQUEST, "DELETE");
		curl_setopt( $this->curl_handle, CURLOPT_QUOTE,array('DELE ' . $file_name ));
		$result = curl_exec( $this->curl_handle );
		$files = explode("\n",trim($result));

		if ( $result === false )
			throw new RuntimeException( sprintf( 'Could not delete file. cURL Error: [%s] - %s', curl_errno( $this->curl_handle ), curl_error( $this->curl_handle ) ) );

		if( ! in_array( $file_name, $files ) ){
			return $this->url . $file_name;
		} else {
			return 'FAILED';
		}
	}

	/**
	 * Attempt to close cURL handle
	 * Note - errors suppressed here as they are not useful
	 *
	 * @access public
	 * @since 1.0
	 */
	public function __destruct() {

		@curl_close( $this->curl_handle );
	}

}
