<?php

namespace Outlandish\Wordpress\Oowp\Views;

class SimplePost extends PostView
{
    public function render($args = [])
    {
        $post = $this->post;
        ?>
        <a href='<?php echo $post->permalink(); ?>' class='item item-<?php echo $post::postType(); ?>'>
            <h3><?php echo $post->title(); ?></h3>
            <p><?php echo $post->excerpt(); ?></p>
        </a>

        <?php
    }
}
