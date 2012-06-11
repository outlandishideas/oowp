<div class='main main-<?php print $post::postName(); ?>'>
	<h1><?php print $post->title(); ?></h1>

	<p>By <?php print $post->htmlAuthorLink(); ?> on <?php print $post->date(); ?></p>
	<?php print $post->content(); ?>
</div>