<?php

namespace Outlandish\Wordpress\Oowp\Views;

class PostMenuItem extends PostView
{
    public function render($args = [])
    {
        $post    = $this->post;
        $classes = ['page_item', 'page-item-' . $post->ID];
        if ($post->isCurrentPage()) {
            $classes[] = 'current_page_item';
        }
        if ($post->isCurrentPageParent()) {
            $classes[] = 'current_page_parent';
        }
        if ($post->isCurrentPageAncestor()) {
            $classes[] = 'current_page_ancestor';
        }
        $children = $post->children();
        $args     = array_merge([
            'max_depth' => 0,
            'current_depth' => 1
        ], $args);
        ?>
        <li class="<?php echo implode(' ', $classes); ?>">
            <a href="<?php echo $post->permalink(); ?>"><?php echo $post->title(); ?></a>

            <?php if ($children->post_count && (!$args['max_depth'] || $args['current_depth'] < $args['max_depth'])): ?>
                <ul class="children">
                    <?php
                    $childView = new PostMenuItem();
                    $args['current_depth']++;
                    foreach ($post->children() as $child) {
                        $childView->post = $child;
                        $childView->render($args);
                    }
                    ?>
                </ul>
            <?php endif; ?>
        </li>
        <?php
    }
}
