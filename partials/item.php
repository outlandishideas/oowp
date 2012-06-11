<a href='<?php print $post->permalink(); ?>'>
	<div class='item item-<?php print $post::postName(); ?>'>
		<h3><?php print $post->title(); ?></h3>
		<?php print $post->excerpt(); ?>
	</div>
</a>
