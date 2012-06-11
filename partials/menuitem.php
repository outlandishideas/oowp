<li class="page_item page-item-<?php echo $post->ID; ?><?php if ($post->isCurrentPageAncestor()) echo ' current_page_item'; ?>">
	<a href="<?php echo $post->permalink(); ?>">
		<span><?php echo $post->title(); ?></span>
	</a>
</li>
