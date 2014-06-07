<?php
namespace Command;

class Group extends Base {

    protected $table = 'threads';

    public $group_id;

    public function execute(array $args, array $options = array())
    {
        list($this->group_id, $this->forum_id) = $args;
        $this->green('Group Crawler '. $this->group_id);
        $fields = array(
            'id',
            'type',
            'object_id,picture,link,source',
            'from',
            'message',
            'created_time',
            'updated_time',
            'comments.limit(1).summary(true),likes.limit(1).summary(true)',
        );
        $graph = "/{$this->group_id}/feed?fields=". urlencode(implode(',', $fields));
        $this->newest_update();

        // Crawl new things
        $this->crawl($graph);

        // Crawl old things
        if ($this->newest_update) {
            // Find when to continue;
            $graph .= '&until='. $this->old_update();
        }
        $this->recount();
    }

    protected function proccess($data) {
        if (!is_array($data)) return true;
        foreach ($data as $item) {
            $item = $this->filter($item);
            if ($item['created_time'] <= $this->newest_update) {
                return true; // stop
            }
            $thread_id = $this->mybbInsertThread($item);
            $this->mybbInsertPost($thread_id, $item);
        }
        return false;
    }

    protected function filter($item) {
        $item = parent::filter($item);
        $item['id'] = str_replace($this->group_id. '_', '', $item['id']);
        $item = $this->filterFrom($item);
        $item = $this->filterCount($item);
        return $item;
    }
    
}
