<?php print '<?xml version="1.0" encoding="utf-8"?>'; ?>

<?php //print_r($all); ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
	<?php foreach($view->pageItems as $item):

	$update = 'weekly';
	$priority = 0.7;
	if(in_array($item->post_type, array('place', 'topic', 'place'))){
		$update = 'daily';
		$priority = 0.8;
	}elseif(in_array($item->post_name, array('home', 'encyclopedia', 'news'))){
		$update = 'hourly';
		$priority = 1;
	}elseif(in_array($item->post_type, array('case_note', 'law', 'terminology', 'method'))){
		$update = 'monthly';
		$priority = 0.6;
	}

	?>
	<url>
		<loc><?php print $item->permalink(); ?></loc>
		<lastmod><?php print $item->date('Y-m-d'); ?></lastmod>
		<changefreq><?php print $update; ?></changefreq>
		<priority><?php print $priority; ?></priority>
	</url><?php
	endforeach; ?>

</urlset>