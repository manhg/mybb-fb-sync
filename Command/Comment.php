<?php
namespace Command;

class Comment extends Base {

    protected $table = 'threads';
    protected $table_id = 'fbid';
    public $target_id, $feed_id, $thread_id;

    public function execute(array $args, array $options = array()) {
        list($this->target_id, $this->forum_id) = $args;
        $this->green('Comments Crawler '. $this->target_id);
        $fields = array(
            'id',
            'from',
            'message',
            'created_time',
            'like_count',
        );
        $graph = "comments?filter=stream&fields=". implode(',', $fields);
        $sql =
            "SELECT fbid, tid FROM threads
            WHERE fid = {$this->forum_id} AND fbupdate = 0";
        $st = $this->db->prepare($sql);
        $st->execute();
        $this->crawlProgress($st, $graph);
    }

    protected function crawlProgress($st, $graph) {
        $this->debug_paging = false;
        $total = $st->rowCount();
        $this->yellow('Total thread: '. $total);
        $progress = new \ConsoleKit\Widgets\ProgressBar($this->console, $total, 50, false);
        while ($row = $st->fetch()) {
            list($this->feed_id, $this->thread_id) = array_values($row);
            $this->crawl("/{$this->feed_id}/$graph\n");
            $progress->incr();
        }
        $progress->stop();
    }

    protected function proccess($data) {
        $n = count($data);
        if (empty($n)) return false;
        foreach ($data as $item) {
            $item = $this->filter($item);
            $this->mybbInsertPost($this->thread_id, $item);
        }
        $last = end($data);
        $sql = "UPDATE `threads` SET `replies` = ?,
            `fbupdate` = ?,
            `lastposter` =  ?,
            `lastposteruid` = ?
            WHERE `tid` = ?";
        $st = $this->db->prepare($sql);
        $bind = array(
            $n,
            1,
            $last['from']['name'],
            $this->mybbFacebookUser(array('from_id' => $last['from']['id'])),
            $this->thread_id
        );
        $st->execute($bind);
        return false;
    }

    protected function filter($item) {
        $item = parent::filter($item);
        $item = $this->filterFrom($item);
        $item['likes_count'] = $item['like_count'];
        unset($item['like_count']);
        $item['parent_id'] = $this->feed_id;
        return $item;
    }
}
