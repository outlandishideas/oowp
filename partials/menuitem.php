<li class="page_item page-item-<?php echo $post->ID;
	if ($post->isCurrentPage()) echo ' current_page_item';
	if ($post->isCurrentPageParent()) echo ' current_page_parent';
	if ($post->isCurrentPageAncestor()) echo ' current_page_ancestor'; ?>">

	<a href="<?php echo $post->permalink(); ?>"><?php echo $post->title(); ?></a>
	<?php

    $args['current_depth']++;
    if ($post->children()->posts && ( ($args['current_depth'] < $args['max_depth']) || (!$args['max_depth']) )): ?>
		<ul class='children'>
			<?php
			foreach($post->children() as $child){
				$child->printMenuItem($args);
			}
			?>
		</ul>
		<?php endif; ?>
</li>
