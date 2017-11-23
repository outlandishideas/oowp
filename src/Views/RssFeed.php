<?php

namespace Outlandish\Wordpress\Oowp\Views;

use Outlandish\Wordpress\Oowp\OowpQuery;
use Outlandish\Wordpress\Oowp\PostTypes\WordpressPost;

class RssFeed extends OowpView
{
    /** @var WordpressPost[]|OowpQuery */
    public $items;
    /** @var string */
    public $name;
    /** @var string */
    public $url;

    public function render($args = [])
    {
        echo '<?xml version="1.0" encoding="utf-8"?>';
        ?>
        <rss version="2.0">
            <channel>
                <title><?php echo $this->name; ?> latest updates</title>
                <description>Latest news from <?php echo $this->name; ?> </description>
                <link><?php echo $this->url; ?></link>
                <lastBuildDate><?php date('D, d M Y H:i:s T'); ?></lastBuildDate>
                <pubDate><?php date('D, d M Y H:i:s T'); ?></pubDate>
                <ttl>1800</ttl>
                <?php
                $view = new RssItem();
                foreach($this->items as $item) {
                    $view->post = $item;
                    $view->render();
                }
                ?>
            </channel>
        </rss>
        <?php
    }
}