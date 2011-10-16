<?php
class TagService {
  var $db;
  var $tablename;

  function &getInstance(&$db) {
    static $instance;
    if (!isset($instance)) {
      $instance = new TagService($db);
    }
    return $instance;
  }

  function TagService(&$db) {
    $this->db =& $db;
    $this->tablename = $GLOBALS['tableprefix'] .'tags';
	$this->voltaire  = new Voltaire();
	$this->voltaire->set_database("voltaire");
  }

  function isNotSystemTag($var) {
    return !(utf8_substr($var, 0, 7) == 'system:');
  }

  
    
    

    function &getTags($userid = NULL) {
        $userservice =& ServiceFactory::getServiceInstance('UserService');
        $logged_on_user = $userservice->getCurrentUserId();

        $query = 'SELECT T.tag, COUNT(B.bId) AS bCount FROM '. $GLOBALS['tableprefix'] .'bookmarks AS B INNER JOIN '. $userservice->getTableName() .' AS U ON B.uId = U.'. $userservice->getFieldName('primary') .' INNER JOIN '. $GLOBALS['tableprefix'] .'tags AS T ON B.bId = T.bId';

        $conditions = array();
        if (!is_null($userid)) {
            $conditions['U.'. $userservice->getFieldName('primary')] = intval($userid);
            if ($logged_on_user != $userid)
                $conditions['B.bStatus'] = 0;
        } else {
            $conditions['B.bStatus'] = 0;
        }

        $query .= ' WHERE '. $this->db->sql_build_array('SELECT', $conditions) .' AND LEFT(T.tag, 7) <> "system:" GROUP BY T.tag ORDER BY bCount DESC, tag';

        if (!($dbresult =& $this->db->sql_query($query))) {
            message_die(GENERAL_ERROR, 'Could not get tags', '', __LINE__, __FILE__, $query, $this->db);
            return false;
        }
        return $this->db->sql_fetchrowset($dbresult);
    }
    
  
    // Returns the tags related to the specified tags; i.e. attached to the same bookmarks
    function &getRelatedTags($tags, $for_user = NULL, $logged_on_user = NULL, $limit = 10) {
        $conditions = array();
        // Only count the tags that are visible to the current user.
        if ($for_user != $logged_on_user || is_null($for_user))
            $conditions['B.bStatus'] = 0;

        if (!is_null($for_user))
            $conditions['B.uId'] = $for_user;

        // Set up the tags, if need be.
        if (is_numeric($tags))
            $tags = NULL;
        if (!is_array($tags) and !is_null($tags))
            $tags = explode('+', trim($tags));

        $tagcount = count($tags);
        for ($i = 0; $i < $tagcount; $i++) {
            $tags[$i] = trim($tags[$i]);
        }

        // Set up the SQL query.
        $query_1 = 'SELECT DISTINCTROW T0.tag, COUNT(B.bId) AS bCount FROM '. $GLOBALS['tableprefix'] .'bookmarks AS B, '. $this->getTableName() .' AS T0';
        $query_2 = '';
        $query_3 = ' WHERE B.bId = T0.bId ';
        if (count($conditions) > 0)
            $query_4 = ' AND '. $this->db->sql_build_array('SELECT', $conditions);
        else
            $query_4 = '';
        // Handle the parts of the query that depend on any tags that are present.
        for ($i = 1; $i <= $tagcount; $i++) {
            $query_2 .= ', '. $this->getTableName() .' AS T'. $i;
            $query_4 .= ' AND T'. $i .'.bId = B.bId AND T'. $i .'.tag = "'. $this->db->sql_escape($tags[$i - 1]) .'" AND T0.tag <> "'. $this->db->sql_escape($tags[$i - 1]) .'"';
        }
        $query_5 = ' AND LEFT(T0.tag, 7) <> "system:" GROUP BY T0.tag ORDER BY bCount DESC, T0.tag';
        $query = $query_1 . $query_2 . $query_3 . $query_4 . $query_5;

        if (! ($dbresult =& $this->db->sql_query_limit($query, $limit)) ){
            message_die(GENERAL_ERROR, 'Could not get related tags', '', __LINE__, __FILE__, $query, $this->db);
            return false;
        }
        return $this->db->sql_fetchrowset($dbresult);
    }

    // Returns the most popular tags used for a particular bookmark hash
    function &getRelatedTagsByHash($hash, $limit = 20) {
        $userservice = & ServiceFactory :: getServiceInstance('UserService');
        $sId = $userservice->getCurrentUserId();
        // Logged in
        if ($userservice->isLoggedOn()) {
            $arrWatch = $userservice->getWatchList($sId);
            // From public bookmarks or user's own
            $privacy = ' AND ((B.bStatus = 0) OR (B.uId = '. $sId .')';
            // From shared bookmarks in watchlist
            foreach ($arrWatch as $w) {
                $privacy .= ' OR (B.uId = '. $w .' AND B.bStatus = 1)';
            }
            $privacy .= ') ';
        // Not logged in
        } else {
            $privacy = ' AND B.bStatus = 0 ';
        }

        $query = 'SELECT T.tag, COUNT(T.tag) AS bCount FROM sc_bookmarks AS B LEFT JOIN sc_tags AS T ON B.bId = T.bId WHERE B.bHash = "'. $hash .'" '. $privacy .'AND LEFT(T.tag, 7) <> "system:" GROUP BY T.tag ORDER BY bCount DESC';

        if (!($dbresult =& $this->db->sql_query_limit($query, $limit))) {
            message_die(GENERAL_ERROR, 'Could not get related tags for this hash', '', __LINE__, __FILE__, $query, $this->db);
            return false;
        }
        return $this->db->sql_fetchrowset($dbresult);
    }

    function &getPopularTags($user = NULL, $limit = 30, $logged_on_user = NULL, $days = NULL) {
        // Only count the tags that are visible to the current user.
        if (($user != $logged_on_user) || is_null($user) || ($user === false))
            $privacy = ' AND B.bStatus = 0';
        else
            $privacy = '';

        if (is_null($days) || !is_int($days))
            $span = '';
        else
            $span = ' AND B.bDatetime > "'. date('Y-m-d H:i:s', time() - (86400 * $days)) .'"';

        $query = 'SELECT T.tag, COUNT(T.bId) AS bCount FROM '. $this->getTableName() .' AS T, '. $GLOBALS['tableprefix'] .'bookmarks AS B WHERE ';
        if (is_null($user) || ($user === false)) {
            $query .= 'B.bId = T.bId AND B.bStatus = 0';
        } else {
            $query .= 'B.uId = '. $this->db->sql_escape($user) .' AND B.bId = T.bId'. $privacy;
        }
        $query .= $span .' AND LEFT(T.tag, 7) <> "system:" GROUP BY T.tag ORDER BY bCount DESC, tag';

        if (!($dbresult =& $this->db->sql_query_limit($query, $limit))) {
            message_die(GENERAL_ERROR, 'Could not get popular tags', '', __LINE__, __FILE__, $query, $this->db);
            return false;
        }

        return $this->db->sql_fetchrowset($dbresult);
    }

    function hasTag($bookmarkid, $tag) {
        $query = 'SELECT COUNT(*) AS tCount FROM '. $this->getTableName() .' WHERE bId = '. intval($bookmarkid) .' AND tag ="'. $this->db->sql_escape($tag) .'"';

        if (! ($dbresult =& $this->db->sql_query($query)) ) {
            message_die(GENERAL_ERROR, 'Could not find tag', '', __LINE__, __FILE__, $query, $this->db);
            return false;
        }
        
        if ($row =& $this->db->sql_fetchrow($dbresult)) {
            if ($row['tCount'] > 0) {
                return true;
            }
        }
        return false;
    }

    function renameTag($userid, $old, $new, $fromApi = false) {
        $bookmarkservice =& ServiceFactory::getServiceInstance('BookmarkService');

        if (is_null($userid) || is_null($old) || is_null($new))
            return false;

        // Find bookmarks with old tag
        $bookmarksInfo =& $bookmarkservice->getBookmarks(0, NULL, $userid, $old);
        $bookmarks =& $bookmarksInfo['bookmarks'];

        // Delete old tag
        $this->deleteTag($old);

        // Attach new tags
        foreach(array_keys($bookmarks) as $key) {
            $row =& $bookmarks[$key];
            $this->attachTags($row['bId'], $new, $fromApi, NULL, false);
        }

        return true;
    }

    function &tagCloud($tags = NULL, $steps = 5, $sizemin = 90, $sizemax = 225, $sortOrder = NULL) {

        if (is_null($tags) || count($tags) < 1) {
            return false;
        }

        $min = $tags[count($tags) - 1]['bCount'];
        $max = $tags[0]['bCount'];

        for ($i = 1; $i <= $steps; $i++) {
            $delta = ($max - $min) / (2 * $steps - $i);
            $limit[$i] = $i * $delta + $min;
        }
        $sizestep = ($sizemax - $sizemin) / $steps;
        foreach ($tags as $row) {
            $next = false;
            for ($i = 1; $i <= $steps; $i++) {
                if (!$next && $row['bCount'] <= $limit[$i]) {
                    $size = $sizestep * ($i - 1) + $sizemin;
                    $next = true;
                }
            }
            $tempArray = array('size' => $size .'%');
            $row = array_merge($row, $tempArray); 
            $output[] = $row;
        }

        if ($sortOrder == 'alphabet_asc') {
            usort($output, create_function('$a,$b','return strcmp(utf8_strtolower($a["tag"]), utf8_strtolower($b["tag"]));'));
        }

        return $output;
    }

    // Properties
    function getTableName()       { return $this->tablename; }
    function setTableName($value) { $this->tablename = $value; }
}
