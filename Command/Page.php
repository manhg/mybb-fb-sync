<?php
namespace Command;

class Page extends Base {

    protected $table = 'threads';

    public $page_id;

    public function execute(array $args, array $options = array())
    {
        list($this->page_id, $this->forum_id) = $args;
        $this->green('Page Crawler '. $this->page_id);
        $graph = "/{$this->page_id}/feed?fields=id,from,object_id,message,type,picture,source,link,created_time,updated_time,shares";
        $this->newest_update();
        $this->crawl($graph);
    }

    protected function proccess($data) {
        if (!is_array($data)) return true;
        foreach ($data as $item) {
            $item = $this->filter($item);
            if ($item['created_time'] < $this->newest_update) {
                return true; // stop
            }
            if (isset($item['from_id'])) {
                $thread_id = $this->mybbInsertThread($item);
                $this->mybbInsertPost($thread_id, $item);
            }
        }
        return false;
    }
    
     protected function filter($item) {
        $item = parent::filter($item);
        $item['id'] = str_replace($this->page_id. '_', '', $item['id']);
        $item = $this->filterFrom($item);
        $item = $this->filterCount($item);
        if ($item['type'] == 'link') {
            $item['picture'] = NULL;
        }
        return $item;
    }

}
