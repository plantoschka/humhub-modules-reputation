<?php
/**
 * Set autostart for reputation module
 *
 * @author Anton Kurnitzky
 */

Yii::app()->moduleManager->register(array(
    'id' => 'reputation',
    'class' => 'application.modules.reputation.ReputationModule',
    'import' => array(
        'application.modules.reputation.*',
        'application.modules.reputation.behaviors.*',
        'application.modules.reputation.models.*',
    ),
    'events' => array(
        array('class' => 'ZCronRunner', 'event' => 'onHourlyRun', 'callback' => array('ReputationModule', 'onCronHourlyRun')),
        array('class' => 'HActiveRecordContent', 'event' => 'onBeforeDelete', 'callback' => array('ReputationModule', 'onContentDelete')),
        array('class' => 'IntegrityChecker', 'event' => 'onRun', 'callback' => array('ReputationModule', 'onIntegrityCheck')),
        array('class' => 'User', 'event' => 'onBeforeDelete', 'callback' => array('ReputationModule', 'onUserDelete')),
        array('class' => 'SpaceMembership', 'event' => 'onBeforeDelete', 'callback' => array('ReputationModule', 'onSpaceMembershipDelete')),
        array('class' => 'Space', 'event' => 'onBeforeDelete', 'callback' => array('ReputationModule', 'onSpaceDelete')),
        array('class' => 'ProfileMenuWidget', 'event' => 'onInit', 'callback' => array('ReputationModule', 'onProfileMenuInit')),
        array('class' => 'SpaceAdminMenuWidget', 'event' => 'onInit', 'callback' => array('ReputationModule', 'onSpaceAdminMenuWidgetInit')),
        array('class' => 'SpaceMenuWidget', 'event' => 'onInit', 'callback' => array('ReputationModule', 'onSpaceMenuInit')),
    ),
));
?>