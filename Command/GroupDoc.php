<?php
namespace Command;

class GroupDoc extends Group {

    public function execute(array $args, array $options = array())
    {
        list($this->group_id, $this->forum_id) = $args;
        $this->green('Group Crawler '. $this->group_id);
        $graph = "/{$this->group_id}/docs";
        $this->newest_update();
        // Crawl new things
        $this->crawl($graph);
        // Crawl old things
        if ($this->newest_update) {
            // Find when to continue;
            $graph .= '&until='. $this->old_update();
        }
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
        $item = apply_filters($item, array(
            'message' => 'utf8_filter',
            'created_time' => 'datetime_from_atom',
            'updated_time' => 'datetime_from_atom',
        ));
        $item = $this->filterFrom($item);
        $item['message'] = str_replace('</p>', "</p>\n", $item['message']);
        $item['message'] = str_replace('&quot;', '"', $item['message']);
        $item['message'] = strip_tags($item['message']);
        return $item;
    }
    
}
