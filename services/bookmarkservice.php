<?php
class BookmarkService {
  var $db;

  function &getInstance(&$db) {
    static $instance;
    if (!isset($instance)) {
      $instance = new BookmarkService($db);
    }
    return $instance;
  }

  function BookmarkService(&$db) {
    $this->db =& $db;
	$this->voltaire =  new Voltaire();
	$this->voltaire->set_database('voltaire');
  }

  function _getbookmark($fieldname, $value, $all = false) {
      
	  
	
	   $response = (array)$this->voltaire->query("subtle","get_bookmarks_by_field",array("key"=>array($fieldname,$value))); 
	  
	 	
		if(isset($response['error']) || !count($response['rows'])>0)
		{
			return false; 
		}
	
		$bookmark= $response['rows'][0]->value; //Get the first row 
		$bookmark->id = $bookmark->_id; 
		
		
		return (array)$boomark; 
	  
	  
	  
  }

  function & getBookmark($bid, $include_tags = false) {
      
	  $bookmark = $this->voltaire->doc($bid);
	  //print_r($bookmark);
	  return (array)$bookmark;  
  }

  function getBookmarkByAddress($address) {
      $hash = md5($address);
      return $this->getBookmarkByHash($hash);
  }

  function getBookmarkByHash($hash) {
      $crit = array ('key' => $hash);
     

      return (array)$this->voltaire->query("subtle","get_bookmarks_by_url",$crit,TRUE);
  }

  function editAllowed($bookmark) {
     
	
      if (!is_array($bookmark))
          if (!($bookmark = $this->getBookmark($bookmark)))
              return false;

      $userservice = & ServiceFactory :: getServiceInstance('UserService');
      $userid = $userservice->getCurrentUserId();
      if ($userservice->isAdmin($userid))
          return true;
      else
          return ($bookmark['uId'] == $userid);
  }

  function bookmarkExists($address = false, $uid = NULL) {
      if (!$address) {
          return;
      }

      // If address doesn't contain ":", add "http://" as the default protocol
      if (strpos($address, ':') === false) {
          $address = 'http://'. $address;
      }

      $crit = array ('key' => md5($address));
      

      $bookmark = $this->voltaire->query("subtle","get_bookmarks_by_url",$crit,TRUE);
	  
	  if(!isset($bookmark->_id)){ return false; }
	  else
	  	{
	  		if(!empty($uid))
			{
				return $bookmark->uId == $uid; 
			}
			return true; 
	  	}
  }

  // Adds a bookmark to the database.
  // Note that date is expected to be a string that's interpretable by strtotime().
  function addBookmark($address, $title, $description, $status, $categories, $date = NULL, $fromApi = false, $fromImport = false) {
      		
      $userservice = & ServiceFactory :: getServiceInstance('UserService');
      $sId = $userservice->getCurrentUserId();

      // If bookmark address doesn't contain ":", add "http://" to the start as a default protocol
      if (strpos($address, ':') === false) {
          $address = 'http://'. $address;
      }

      // Get the client's IP address and the date; note that the date is in GMT.
      if (getenv('HTTP_CLIENT_IP'))
          $ip = getenv('HTTP_CLIENT_IP');
      else
          if (getenv('REMOTE_ADDR'))
              $ip = getenv('REMOTE_ADDR');
          else
              $ip = getenv('HTTP_X_FORWARDED_FOR');

      // Note that if date is NULL, then it's added with a date and time of now, and if it's present,
      // it's expected to be a string that's interpretable by strtotime().
      if (is_null($date))
          $time = time();
      else
          $time = strtotime($date);
      $datetime = gmdate('Y-m-d H:i:s', $time);

      // Set up the SQL insert statement and execute it.
      $values = array('uId' => $sId, 'bIp' => $ip, 'bDatetime' => $datetime, 'bModified' => $datetime, 'bTitle' => $title, 'bAddress' => $address, 'bDescription' => $description, 'bStatus' => intval($status), 'bHash' => md5($address));
      
	  
	
	  $values_with_tags = $values;
	  $values_with_tags['categories'] = explode(" ",$categories);
	  $result_doc = $this->voltaire->create_document($values_with_tags); 
	  
      if($result_doc->ok)
	  {
	  	return $result_doc->id; 
	  }
      return false;
  }

  function updateBookmark($bId, $address, $title, $description, $status, $categories, $date = NULL, $fromApi = false) {
      if (!is_numeric($bId))
          return false;

      // Get the client's IP address and the date; note that the date is in GMT.
      if (getenv('HTTP_CLIENT_IP'))
          $ip = getenv('HTTP_CLIENT_IP');
      else
          if (getenv('REMOTE_ADDR'))
              $ip = getenv('REMOTE_ADDR');
          else
              $ip = getenv('HTTP_X_FORWARDED_FOR');

      $moddatetime = gmdate('Y-m-d H:i:s', time());

      // Set up the SQL update statement and execute it.
      $updates = array('bModified' => $moddatetime, 'bTitle' => $title, 'bAddress' => $address, 'bDescription' => $description, 'bStatus' => $status, 'bHash' => md5($address));
      $values_with_tags = $updates;
	  $values_with_tags['categories'] = explode(",",$categories);
	  
	  $doc = $this->voltaire->doc($bId); 
	  $values_with_tags["_rev"] = $doc->_rev; 
	  $values_with_tags["_id"] = $doc->_id; 
	  $result_doc = $this->voltaire->create_document($values_with_tags); 
	  
      if($result_doc->ok)
	  {
	  	return $result_doc->_id; 
	  }
      return false;
      
      
  }

/*** THIS IS ONE FUNCTION RESPONSIBLE FOR FETCHING ALL KIND OF BOOKMARKS ***/



  function & getBookmarks($start = 0, $perpage = NULL, $user = NULL, $tags = NULL, $terms = NULL, $sortOrder = NULL, $watched = NULL, $startdate = NULL, $enddate = NULL, $hash = NULL) {
    

      $userservice =& ServiceFactory::getServiceInstance('UserService');
      $tagservice =& ServiceFactory::getServiceInstance('TagService');
      $sId = $userservice->getCurrentUserId();

	  
     
	 
	  $res = $this->voltaire->doc("_design/subtle/_view/get_bookmarks_by_user");
	  
	  $bookmarks = array(); 
	  foreach($res->rows as $doc)
	  {
	  	$arr = (array)$this->voltaire->doc($doc->value);
		$arr['tags'] = $arr['categories'];
		$user = $userservice->getUser($arr['uId']); 
		$arr['username'] = $user['username'];
	  	$bookmarks[]= $arr;
	  }
	  $total = $res->total_rows;   
      
      return array ('bookmarks' => $bookmarks, 'total' => $total);
  }

  function deleteBookmark($bookmarkid) {
      $query = 'DELETE FROM '. $GLOBALS['tableprefix'] .'bookmarks WHERE bId = '. intval($bookmarkid);
      $this->db->sql_transaction('begin');
      if (!($dbresult = & $this->db->sql_query($query))) {
          $this->db->sql_transaction('rollback');
          message_die(GENERAL_ERROR, 'Could not delete bookmarks', '', __LINE__, __FILE__, $query, $this->db);
          return false;
      }

      $query = 'DELETE FROM '. $GLOBALS['tableprefix'] .'tags WHERE bId = '. intval($bookmarkid);
      $this->db->sql_transaction('begin');
      if (!($dbresult = & $this->db->sql_query($query))) {
          $this->db->sql_transaction('rollback');
          message_die(GENERAL_ERROR, 'Could not delete bookmarks', '', __LINE__, __FILE__, $query, $this->db);
          return false;
      }

      $this->db->sql_transaction('commit');
      return true;
  }

  function countOthers($address) {
  	
	  return 0;
  }
}
