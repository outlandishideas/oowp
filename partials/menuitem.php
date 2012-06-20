<li class="page_item page-item-<?php echo $post->ID;
	if ($post->isCurrentPage()) echo ' current_page_item';
	if ($post->isCurrentPageParent()) echo ' current_page_parent';
	if ($post->isCurrentPageAncestor()) echo ' current_page_ancestor'; ?>">
	<a href="<?php echo $post->permalink(); ?>">
		<span><?php echo $post->title(); ?></span>
	</a>
	<?php if ($post->children()->posts): ?>
		<ul class='children'>
			<?php
			foreach($post->children() as $child){
				$child->printMenuItem();
			}
			?>
		</ul>
		<?php endif; ?>
</li>
