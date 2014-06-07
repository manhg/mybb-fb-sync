<?php
namespace Command;

class GroupComment extends Base {

    protected $table = 'group_comments';

    public $feed_id;

    public function execute(array $args, array $options = array()) {
        $this->green('Group Comments Crawler');
        $fields = array(
            'id',
            'from',
            'message',
            'created_time',
            'like_count',
        );
        $graph = "comments?filter=stream&fields=". implode(',', $fields);

        $this->lastStatus();

        /*
        $this->green('Comments for new updated ');
        $st = $this->db->prepare('SELECT id FROM group_feeds' .
            ($this->last_update ? ' WHERE created_time > ?' : '')
        );
        $st->execute(array($this->last_update));
        $this->crawlProgress($st, $graph);
        */

        $this->green('Comments for old articles');
        $st = $this->db->prepare('SELECT id FROM group_feeds WHERE created_time <= ?');
        $st->execute(array(
            date('Y-m-d H:i:s', $this->old_update())
        ));
        dd(date('Y-m-d H:i:s', $this->old_update()), $st->fetchALl());
        $this->crawlProgress($st, $graph);
    }

    protected function crawlProgress($st) {
        $this->debug_paging = false;
        $total = $st->rowCount();
        $this->yellow('Total thread: '. $total);
        $progress = new \ConsoleKit\Widgets\ProgressBar($this->console, $total, 50, false);
        while ($this->feed_id = $st->fetchColumn()) {
            $this->crawl("/{$this->feed_id}/$graph\n");
            $progress->incr();
        }
        $progress->stop();
    }

    protected function proccess($data) {
        if (!is_array($data)) return true;
        foreach ($data as $item) {
            $item = $this->filter($item);
            $this->insert($this->table, $item);
        }
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
