(function($) {
    // Remove the counts for posts, categories and tags from the 'right now' box
    var $rows = $('#dashboard_right_now .table_content tr');
    $rows.each(function() {
        var $cell = $(this).find('td.first');
        if ($cell.hasClass('b-posts') || $cell.hasClass('b-cats') || $cell.hasClass('b-tags')) {
            $(this).remove();
        }
    });
    $('#dashboard_right_now .table_content tr').eq(0).addClass('first');
})(jQuery);
