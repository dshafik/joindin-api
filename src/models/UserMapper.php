<?php

/**
 * UserMapper
 * 
 * @uses ApiModel
 * @package API
 */
class UserMapper extends ApiMapper 
{

    /**
     * Default mapping for column names to API field names
     * 
     * @return array with keys as API fields and values as db columns
     */
    public function getDefaultFields() 
    {
        $fields = array(
            "username" => "username",
            "full_name" => "full_name",
            "twitter_username" => "twitter_username"
            );
        return $fields;
    }

    /**
     * Field/column name mappings for the verbose version
     *
     * This should contain everything above and then more in most cases
     * 
     * @return array with keys as API fields and values as db columns
     */
    public function getVerboseFields() 
    {
        $fields = array(
            "username" => "username",
            "full_name" => "full_name",
            "twitter_username" => "twitter_username",
            );
        return $fields;
    }

    public function getUserById($user_id, $verbose = false) 
    {
        $results = $this->getUsers(1, 0, 'user.ID=' . (int)$user_id, null);
        if ($results) {
            $retval = $this->transformResults($results, $verbose);
            return $retval;
        }
        return false;

    }

    public function getUserByUsername($username, $verbose = false)
    {
        $sql = 'select user.* '
            . 'from user '
            . 'where active = 1 '
            . 'and user.username = :username';

        // limit clause
        $sql .= $this->buildLimit(1, 0);

        $stmt = $this->_db->prepare($sql);
        $data = array("username" => $username);

        $response = $stmt->execute($data);
        if ($response) {
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $results['total'] = $this->getTotalCount($sql, $data);
            if ($results) {
                return $this->transformResults($results, $verbose);
            }
        }
        return false;
    }

    protected function getUsers($resultsperpage, $start, $where = null, $order = null)
    {
        $sql = 'select user.username, user.ID, user.email, '
            . 'user.full_name, user.twitter_username, user.admin '
            . 'from user '
            . 'left join user_attend ua on (ua.uid = user.ID) '
            . 'where active = 1 ';
        
        // where
        if ($where) {
            $sql .= ' and ' . $where;
        }

        // group by because of adding the user_attend join
        $sql .= ' group by user.ID ';

        // order by
        if ($order) {
            $sql .= ' order by ' . $order;
        }

        // limit clause
        $sql .= $this->buildLimit($resultsperpage, $start);

        $stmt = $this->_db->prepare($sql);

        $response = $stmt->execute();
        if ($response) {
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $results['total'] = $this->getTotalCount($sql);
            return $results;
        }
        return false;
    }

    public function getUserList($resultsperpage, $start, $verbose = false) 
    {
        $order = 'user.ID';
        $results = $this->getUsers($resultsperpage, $start, null, $order);
        if (is_array($results)) {
            $retval = $this->transformResults($results, $verbose);
            return $retval;
        }
        return false;
    }

    public function getUsersAttendingEventId($event_id, $resultsperpage, $start, $verbose)
    {
        $where = "ua.eid = " . $event_id;
        $results = $this->getUsers($resultsperpage, $start, $where);
        if (is_array($results)) {
            $retval = $this->transformResults($results, $verbose);
            return $retval;
        }
        return false;
    }

    public function transformResults($results, $verbose) 
    {
        $total = $results['total'];
        unset($results['total']);

        $list = parent::transformResults($results, $verbose);
        $base = $this->_request->base;
        $version = $this->_request->version;

        // add per-item links 
        if(is_array($list) && count($list)) {
            foreach ($results as $key => $row) {
                if (true === $verbose) {
                    $list[$key]['gravatar_hash'] = md5(strtolower($row['email']));
                }
                $list[$key]['uri'] = $base . '/' . $version . '/users/' 
                    . $row['ID'];
                $list[$key]['verbose_uri'] = $base . '/' . $version . '/users/' 
                    . $row['ID'] . '?verbose=yes';
                $list[$key]['website_uri'] = 'http://joind.in/user/view/' . $row['ID'];
                $list[$key]['talks_uri'] = $base . '/' . $version . '/users/' 
                    . $row['ID'] . '/talks/';
                $list[$key]['attended_events_uri'] = $base . '/' . $version . '/users/' 
                    . $row['ID'] . '/attended/';
                $list[$key]['email_hash'] = md5(strtolower($row['email']));

                if ($verbose) {
                    // Get latest events attended, comments, and speaking
                    $event_mapper = new EventMapper($this->_db, $this->_request);
                    $events = $event_mapper->getEventsAttendedByUser($row['ID'], 5, 0, $this->_request, false);
                    $list[$key]['events'] = $events['events'];

                    $comment_mapper = new TalkCommentMapper($this->_db, $this->_request);
                    $comments = $comment_mapper->getCommentsByUserId($row['ID'], 5, 0, true);
                    $list[$key]['comments'] = $comments['comments'];

                    $talk_mapper = new TalkMapper($this->_db, $this->_request);
                    $talks = $talk_mapper->getTalksBySpeaker($row['ID'], 5, 0, true);
                    $list[$key]['talks'] = $talks['talks'];
                }
            }
        }
        $retval = array();
        $retval['users'] = $list;
        $retval['meta'] = $this->getPaginationLinks($list, $total);

        return $retval;
    }


    public function isSiteAdmin($user_id) {
        $results = $this->getUsers(1, 0, 'user.ID=' . (int)$user_id, null);
        if($results[0]['admin'] == 1) {
            return true;
        }
        return false;
    }
}
