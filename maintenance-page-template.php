<?php
// maintenance-page-template.php

// $mode should be passed to this template. It should be either 'maintenance' or 'coming_soon'.

get_header();

?>

<div class="simple-maintenance-mode-content">
  <div class="content">
    <?php
    if ($mode === 'maintenance') {
        echo '<h1>Maintenance Mode</h1>';
        echo '<p>Sorry, we are performing scheduled maintenance. We will be back soon!</p>';
    } elseif ($mode === 'coming_soon') {
        echo '<h1>Coming Soon</h1>';
        echo '<p>Stay tuned, something awesome is coming!</p>';
    } else {
        echo '<h1>Uh Oh!</h1>';
        echo '<p>There seems to be a problem, but don\'t worry, our team is working on it. Check back soon!</p>';
    }
    ?>
  </div>
</div>

<?php
get_footer();