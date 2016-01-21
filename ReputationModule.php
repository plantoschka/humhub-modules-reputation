<?php

/**
 * Class ReputationModule
 *
 * This module integrates a reputation system in HumHub
 * @author Anton Kurnitzky
 */
class ReputationModule extends HWebModule
{
    /**
     * Recalculate user and content reputation every hour
     * Only do this in spaces where reputation module is enabled
     *
     * @param $event
     * @throws CException
     */
    public static function onCronHourlyRun($event)
    {
        Yii::import('application.modules.reputation.models.*');
        $cron = $event->sender;

        foreach (Space::model()->findAll() as $space) {
            if($space->isModuleEnabled('reputation'))
            {
                $cronJobEnabled = SpaceSetting::Get($space->id, 'cron_job', 'reputation', ReputationBase::DEFAULT_CRON_JOB);
                if($cronJobEnabled)
                {
                    print "- Recalculating reputation for space: $space->id  $space->name\n";
                    ReputationUser::model()->updateUserReputation($space, true);
                    ReputationContent::model()->updateContentReputation($space, true);
                }
            }
        }
    }

    /**
     * On user delete, also delete all reputation of this user
     *
     * @param type $event
     */
    public static function onUserDelete($event)
    {
        foreach (ReputationUser::model()->findAllByAttributes(array('user_id' => $event->sender->id)) as $reputationUser) {
            $reputationUser->delete();
        }
    }

    /**
     * When a user leaves a space remove the user reputation for this space
     *
     * @param type $event
     */
    public static function onSpaceMembershipDelete($event)
    {
        foreach (ReputationUser::model()->findAllByAttributes(array('user_id' => $event->sender->user_id, 'space_id' => $event->sender->space_id)) as $reputationUser)
        {
            $reputationUser->delete();
        }
    }

    /**
     * On space delete, also delete all reputation of this space
     *
     * @param type $event
     */
    public static function onSpaceDelete($event)
    {
        foreach (ReputationUser::model()->findAllByAttributes(array('space_id' => $event->sender->id)) as $reputationSpace) {
            $reputationSpace->delete();
        }
    }

    /**
     * On content delete, also delete the content reputation
     *
     * @param type $event
     */
    public static function onContentDelete($event)
    {
        foreach (ReputationContent::model()->findAllByAttributes(array('object_id' => $event->sender->id)) as $reputationContent) {
            $reputationContent->delete();
        }
    }

    /**
     * Show reputation menu in user profile
     *
     * @param $event
     */
    public static function onProfileMenuInit($event)
    {
        $userGuid = Yii::app()->getController()->getUser()->guid;

        $event->sender->addItem(array(
            'label' => Yii::t('ReputationModule.base', 'Reputation'),
            'group' => 'profile',
            'url' => Yii::app()->createUrl('//reputation/profileReputation/show', array('uguid' => $userGuid)),
            'isActive' => Yii::app()->controller->module && Yii::app()->controller->module->id == 'reputation',
            'sortOrder' => 1000,
        ));
    }

    /*
     * Show reputation menu in space admin menu
     */
    public static function onSpaceAdminMenuWidgetInit($event)
    {
        $space = Yii::app()->getController()->getSpace();
        if ($space->isAdmin() && $space->isModuleEnabled('reputation'))
            $event->sender->addItem(array(
                'label' => Yii::t('ReputationModule.base', 'User Reputation'),
                'url' => Yii::app()->createUrl('//reputation/adminReputation/show', array('sguid' => $space->guid)),
                'icon' => '<i class="fa fa-book"></i>',
                'group' => 'admin',
                'isActive' => (Yii::app()->controller->module && Yii::app()->controller->module->id == 'reputation' &&
                    Yii::app()->controller->id == 'adminReputation' && Yii::app()->controller->action->id == 'show'),
                'sortOrder' => 1000,
            ));
    }

    /*
     * Show reputation menu in space admin menu
     */
    public static function onSpaceMenuInit($event)
    {
        $space = Yii::app()->getController()->getSpace();
        if ($space->isModuleEnabled('reputation'))
            $event->sender->addItem(array(
                'label' => Yii::t('ReputationModule.base', 'Hot'),
                'url' => Yii::app()->createUrl('//reputation/reputationWall/show', array('sguid' => $space->guid)),
                'icon' => '<i class="fa fa-fire"></i>',
                'isActive' => (Yii::app()->controller->module && Yii::app()->controller->module->id == 'reputation' && Yii::app()->controller->id == 'reputationWall'),
                'group' => 'modules',
                'sortOrder' => 101,
            ));
    }

    public function init()
    {
        // import the module-level models and components
        $this->setImport(array(
            'reputation.models.*',
            'reputation.controllers.*',
            'reputation.behaviors.*',
        ));
    }

    public function behaviors()
    {
        return array(
            'SpaceModuleBehavior' => array(
                'class' => 'application.modules_core.space.behaviors.SpaceModuleBehavior',
            ),
            'UserModuleBehavior' => array(
                'class' => 'application.modules_core.user.behaviors.UserModuleBehavior',
            ),
        );
    }

    /**
     * Returns module config url for spaces of reputation module.
     *
     * @return String
     */
    public function getSpaceModuleConfigUrl(Space $space)
    {
        return Yii::app()->createUrl('//reputation/adminReputation/configuration', array('sguid' => $space->guid));
    }

    /**
     * On run of integrity check command, validate all module data
     *
     * @param type $event
     */
    public static function onIntegrityCheck($event)
    {
        $integrityChecker = $event->sender;
        $integrityChecker->showTestHeadline("Validating Reputation Content (" . ReputationContent::model()->count() . " entries)");
        $integrityChecker->showTestHeadline("Validating Reputation User (" . ReputationUser::model()->count() . " entries)");
    }
}