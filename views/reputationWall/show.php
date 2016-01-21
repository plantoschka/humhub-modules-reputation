<?php
/**
 * This view is shown when a user clicks on the "Popular" navigation item
 * @author Anton Kurnitzky
 */
?>

<?php
$this->widget('application.modules.reputation.widgets.ReputationStreamWidget', array(
    'contentContainer' => $this->contentContainer,
    'streamAction' => '//reputation/reputationWall/stream',
));
?>