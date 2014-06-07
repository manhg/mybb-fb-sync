<?php
namespace Command;

use \ConsoleKit\Colors;

abstract class Base extends \ConsoleKit\Command {

    protected
        $db,
        $config,
        $facebook,
        $table,
        $debug_paging = true,
        $sleep = 100000,
        $newest_update = 0,
        $forum_id;
        
    public function __construct(\ConsoleKit\Console $console) {
        parent::__construct($console);
        require __DIR__. '/../util.php';
        @require(__DIR__. '/../../mybb/inc/config.php');
        $this->config = parse_ini_file(__DIR__ . '/../config.ini', true);
        extract($this->config);
        $this->db = new \PDO(
            $database['dsn'],
            $config['database']['username'],
            $config['database']['password']
        );
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->facebook = new \Facebook(array(
            'appId' => $facebook['app_id'],
            'secret' => $facebook['app_secret'],
            'fileUpload' => false,
            'allowSignedRequest' => false, // must false for non-canvas apps
        ));
    }

    function yellow($text) {
        $this->writeln($text, Colors::YELLOW);
    }

    function red($text) {
        $this->writeln($text, Colors::RED);
    }

    function green($text) {
        $this->writeln($text, Colors::GREEN);
    }

    protected function newest_update() {
        $date = $this->db->query(
            "SELECT MAX(dateline) AS newest_update FROM {$this->table}
            WHERE fid = {$this->forum_id}"
        )->fetchColumn();
        if ($date) {
            $this->newest_update = $date;
            $this->yellow('Last crawl: '. $date);
        }
    }


    protected function old_update() {
        $mid_date = $this->db
            ->query('SELECT MIN(dateline) AS newest_update FROM '. $this->table . ' WHERE fid = '. $this->forum_id)
            ->fetchColumn();
        return is_null($mid_date) ? 0 : $mid_date - 1;
    }

    

    public function api($path, $params = array()) {
        return $this->facebook->api($path, 'GET', $params);
    }
    public function query($fql) {
        return $this->facebook->api(array(
            'method' => 'fql.query',
            'query' => $fql,
        ));
    }

    public function json($var) {
        return json_encode($var, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    protected function insert($table, $data) {
        $values = array_values($data);
        $values = array_map(function($v) {
            return $this->db->quote($v);
        }, $values);
        try {
            $sql = "INSERT `$table` ("
                . implode(',', array_keys($data)). ')'
                . " VALUES (". implode(", ", $values) . ")";
            $this->db->query($sql);
        } catch (\PDOException $e) {
            $this->red($e->getMessage());
        }
        return $this->db->lastInsertId();
    }

    /**
     * Insert if the created_time is newer than oldest item in database
     */
    protected function insertIf($item) {
        if ($item['created_time'] > $this->newest_update) {
            $this->insert($this->table, $item);
            return true;
        } else {
            $this->red("Updated");
            return false;
        }
    }

    protected function filter($item) {
        return apply_filters($item, array(
            'message' => 'utf8_filter',
            'created_time' => 'datetime_from_atom',
            'updated_time' => 'datetime_from_atom',
        ));
    }

    protected function filterFrom($item) {
        if (isset($item['from'])) {
            $item['from_name'] = string_excerpt($item['from']['name'], 25);
            $item['from_id'] = $item['from']['id'];
            unset($item['from']);
        }
        return $item;
    }

    protected function filterShareCount($item) {
        if (isset($item['shares'])) {
            $s = $item['shares'];
            unset($item['shares']);
            $item['shares_count'] = $s['count'];
        }
        return $item;
    }

    protected function filterCount($item) {
        if (isset($item['comments'])) {
            $item['comments_count'] = $item['comments']['summary']['total_count'];
            unset($item['comments']);
        }
        if (isset($item['likes'])) {
            $item['likes_count'] = $item['likes']['summary']['total_count'];
            unset($item['likes']);
        }
        return $item;
    }

    /**
     * Call Facebook API
     */
    public function crawl($graph) {
        $count = 1;
        $stop = false;
        while (! $stop) {
            if ($this->debug_paging) {
                $this->green("Page @ ". $count++);
            }
            $feed = $this->api($graph);
            $next_graph = @$feed['paging']['next'];
            $stop = $this->proccess($feed['data'])
                || ! $next_graph;
            $graph = str_replace('https://graph.facebook.com', '', $next_graph);
            if ($this->sleep) {
                usleep($this->sleep);
            }
        }
    }
    
    /**
     * Required:
     array(
        'subject',
        'username',
        'dateline',
     )
     */
    public function mybbInsertThread($data) {
        extract($data);
        if (!isset($subject)) {
            $subject = isset($message) ? string_excerpt($message, 100) : '';
        }
        $uid = $this->mybbFacebookUser($data);
        $thread = array(
            'fid' => $this->forum_id,
            'fbid' => $id,
            'subject' => $subject,
            'prefix' => 0,
            'uid' => $uid,
            'username' => $from_name,
            'dateline' => $created_time,
            'firstpost' => $uid,
            'lastpost' => $updated_time,
            'lastposter' => $from_name,
            'lastposteruid' => $uid,
            'views' => 0,
            'notes' => '',
            'replies' => 0,
            'closed' => NULL,
            'sticky' => 0,
            'visible' => 1,
        );
        $tid = $this->insert('threads', $thread);
        return $tid;
    }
    
    public function mybbInsertPost($thread_id, $data) {
        extract($data);
        $uid = $this->mybbFacebookUser($data);
        $message = isset($message) ? $message : '';
        if (isset($picture)) {
            $picture = strtr($picture, array(
                'https://fbcdn' => 'http://fbcdn',
                '_s.jpg' => '_n.jpg',
            ));
            $message = "[img]{$picture}[/img]\n". $message;
        }
        if (isset($source)) {
            $message .= "\n[video]{$source}[/video]";
        }
        if (isset($link)) {
            $message .= "\n\n{$link}";
        }
        if (isset($object)) {
            $message .= "\n[fb_object]{$object_id}[/fb_object]";
        }
        $post = array(
            'tid' => $thread_id,
            // 'fbid' => $id,
            'replyto' => 0,
            'fid' => $this->forum_id,
            'subject' => '',
            'icon' => 0,
            'uid' => $uid,
            'username' => $from_name,
            'dateline' => $created_time,
            'message' => $message,
            'includesig' => 0,
            'smilieoff' => 0,
            'edituid' => 0,
            'edittime' => 0,
            'visible' => 1,
            'posthash' => '',
            'mobile' => 0,
            'pthx' => isset($likes_count) ? $likes_count : 0,
        );
        $pid = $this->insert('posts', $post);
    }
    
    public function mybbFacebookUser($raw) {
        $st = $this->db->prepare('SELECT uid FROM users WHERE sync_fbid = ?');
        $st->execute(array($raw['from_id']));
        $uid = $st->fetchColumn();
        if (empty($uid)) {
            $user = array(
                'usergroup' => $this->config['user_group'],
                'displaygroup' => $this->config['user_group'],
                'username' => ($raw['from_id'] % 100) .' '. $raw['from_name'],
                'signature' => '',	 	 
                'buddylist' => '',	 
                'ignorelist' => '',	 
                'pmfolders' => '',	 
                'notepad' => '',	 	 
                'usernotes' => '',
                'sync_fbid' => $raw['from_id'],
            );
            $uid = $this->insert('users', $user);
        }
        return $uid;
    }
    
    protected function dbQuery($sql, $params) {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }
    
    public function recount() {
        $t = $this->dbQuery(
            "SELECT COUNT(tid) AS n FROM threads WHERE fid = ?",
            array($this->forum_id)
        )->fetchColumn();
        $p = $this->dbQuery(
            "SELECT COUNT(pid) AS n FROM posts WHERE fid = ?",
            array($this->forum_id)
        )->fetchColumn();
        $this->dbQuery(
            "UPDATE forums SET threads = ?, posts = ? WHERE fid = ?",
            array($t, $p, $this->forum_id)
        );
    }

    /**
     * @return must be:
     *       false: if continue crawl,
     *       true: to stop
     */
    abstract protected function proccess($data);
}
