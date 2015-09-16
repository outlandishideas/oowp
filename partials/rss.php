<?php print '<?xml version="1.0" encoding="utf-8"?>'; ?>
<?php //print_r($all); ?>

<rss version="2.0">
    <channel>
        <title><?php bloginfo('name'); ?> latest updates</title>
        <description>Latest news from <?php bloginfo('name'); ?> </description>
        <link><?php bloginfo('url'); ?></link>
        <lastBuildDate><?php date('D, d M Y H:i:s T'); ?></lastBuildDate>
        <pubDate><?php date('D, d M Y H:i:s T'); ?></pubDate>
        <ttl>1800</ttl>
        <?php foreach($view->pageItems as $item) : ?>
            <item>
                <link><?php print $item->permalink(); ?></link>
                <pubDate><?php print $item->date('D, d M Y H:i:s T'); ?></pubDate>
                <guid><?php print $post->guid; ?></guid>
                <description><?php print $post->excerpt(); ?></description>
            </item>
    	<?php endforeach; ?>
    </channel>
</rss>
