<?php

namespace Outlandish\Wordpress\Oowp\Views;

class RssItem extends PostView
{
    public function render($args = [])
    {
        ?>
        <item>
            <link><?php echo $this->post->permalink(); ?></link>
            <pubDate><?php echo $this->post->date('D, d M Y H:i:s T'); ?></pubDate>
            <guid><?php echo $this->post->guid; ?></guid>
            <description><?php echo $this->post->excerpt(); ?></description>
        </item>
        <?php
    }

}
