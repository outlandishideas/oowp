<div class='main main-<?php print $post::postType(); ?>'>
	<h1><?php print $post->title(); ?></h1>

	<p>By <?php print $post->htmlAuthorLink(); ?> on <?php print $post->date(); ?></p>
	<?php print $post->content(); ?>
</div>