<?php

namespace Outlandish\Wordpress\Oowp\Views;

use Outlandish\Wordpress\Oowp\OowpQuery;
use Outlandish\Wordpress\Oowp\PostTypes\WordpressPost;

class RssFeed extends OowpView
{
    /** @var WordpressPost[]|OowpQuery */
    public $items;
    /** @var string Used in $description and $title, if not otherwise supplied */
    public $name;
    /** @var string */
    public $url;
    /** @var string */
    public $description;
    /** @var string */
    public $title;

    public function render($args = [])
    {
        echo '<?xml version="1.0" encoding="utf-8"?>';
        ?>
        <rss version="2.0">
            <channel>
                <?php
                $this->renderSiteInfo();
                $this->renderItems();
                ?>
            </channel>
        </rss>
        <?php
    }

    protected function renderSiteInfo()
    {
        $description = $this->description ?: "Latest news from {$this->name}";
        $title = $this->title ?: "{$this->name} latest updates";
?>
        <title><?php echo $title; ?></title>
        <description><?php echo $description; ?></description>
        <link><?php echo $this->url; ?></link>
        <lastBuildDate><?php date('D, d M Y H:i:s T'); ?></lastBuildDate>
        <pubDate><?php date('D, d M Y H:i:s T'); ?></pubDate>
        <ttl>1800</ttl>
<?php
    }

    protected function renderItems()
    {
        $view = new RssItem();
        foreach($this->items as $item) {
            $view->post = $item;
            $view->render();
        }
    }
}