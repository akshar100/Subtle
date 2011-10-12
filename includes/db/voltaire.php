<?php
class Voltaire
{
	protected $host='127.0.0.1';
	protected $port=5984;
	protected $dsn = '';
	protected $username; 
	protected $password;
	protected $database;
	
	/**
	 * Supports on basic HTTP authentication
	 * The Voltaire
	 **/
	public function __construct($host='127.0.0.1',$port=5984,$username='',$password='')
	{
		$this->host = $host; 
		$this->port = $port;
		$this->username = $username; 
		$this->password = $password; 
		$this->database = $database; 
		if(empty($this->username))
		{
			$this->dsn = "http://{$this->host}:{$this->port}/";
		}
		else
		{
			$this->dsn = "http://{this->username}:{$this->password}{$this->host}:{$this->port}/";
		}
	}
	
	/**
	 * The basic method invoked to make CURL calls
	 */
	public function request($type='GET',$urlstring='',$params=array())
	{
		
		$ch = curl_init(); 
		curl_setopt($ch,CURLOPT_URL,$this->dsn.$urlstring);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		 
		
		if(!empty($params) AND is_array($params))
		{
			//url-ify the data for the POST
			foreach($params as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
				rtrim($fields_string,'&');
		}
		switch($type)
		{
			case "POST":
				//set the url, number of POST vars, POST data
				curl_setopt($ch,CURLOPT_URL,$url);
				curl_setopt($ch,CURLOPT_POST,count($params));
				curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
				break;
			case "PUT":
				$fh = fopen('php://memory', 'rw');
    			fwrite($fh, $params);
   				rewind($fh);
   					
				curl_setopt($ch, CURLOPT_PUT, 1);
				curl_setopt($ch, CURLOPT_INFILE, $fh);
    			curl_setopt($ch, CURLOPT_INFILESIZE, strlen($params)); //this $params will contain string in case of Put requests
				break;
			case "DELETE":
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
				break;
			case "HEADER":
				curl_setopt($ch, CURLOPT_HEADER, TRUE);
				break;
			default:
				break; 
				
		}
		$response = curl_exec($ch);
		
		$response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		
		return json_decode($response); 
		
		
		
	}

	public function create_database($dbname='')
	{
		
		return $this->request("PUT",$dbname);
		
	}
	
	public function all_dbs()
	{
		return $this->request("GET",'_all_dbs');
		
		
	}
	
	public function delete_database($dbname)
	{
		return $this->request("DELETE",$dbname);
		
	}
	
	public function create_document($content=array(),$uuid)
	{
		if(empty($uuid))
			$uuid=md5(time().rand());
		
		$response = $this->request('PUT',$this->database."/".$uuid,json_encode($content));
			
		return $response;
		
	}
	
	public function set_database($dbname)
	{
		$this->database = $dbname;
	}
	
	public function doc($doc)
	{
		
		if(empty($doc))
		{
			die("No doc is provided.");
		}
		
		return $this->request("GET",$this->database."/".$doc);
	}
	
	
	public function save($doc)
	{
		if(empty($doc))
		{
			die("No doc is provided.");
		}
		
		return $this->request("PUT",$this->database."/".$doc->_id,json_encode($doc)); 
		
		
	}
	
	public function design_file($filename,$doc)
	{
		$fh = fopen($filename,"r");
		$contents = fread($fh,filesize($filename));
		fclose($fh);
		
		return $this->request("PUT",$this->database."/_design/".$doc, $contents);
	}
	
	
	public function design($doc,$contents)
	{
		return $this->request("PUT",$this->database."/_design/".$doc, json_encode($contents));
	}
	
	public function query($design,$view,$arr,$first=TRUE)
	{
		
		$response = (array)$this->doc("_design/$design/_view/$view?".$this->generate_query_string($arr)); 
	 
		if(isset($response['error']) || !count($response['rows'])>0)
		{
			return false; 
		}
		
		if($first)
		{
			$row = $response['rows'][0]->value; //Get the first row 
			$row->id = $row->_id; 
		
		
			return $row; 
				
		}
		else
		{
			return $response['rows'];
		}
		
		
	}
	
	protected function generate_query_string($arr)
	{
		$string ="";
		$i=0; 
		foreach($arr as $k=>$v)
		{
			if($i==0)
			{
				$string.="$k=".json_encode($v); 
			}
			else
			{
				$string.="&$k=".json_encode($v); 
			}
			
		}
		return $string; 
	}
	
}




