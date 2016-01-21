<?php

/**
 * The space reputation users can see inside their own or other users profiles
 * Showing space reputation to other users is opt-in
 *
 * @author Anton Kurnitzky
 */
class ProfileReputationController extends ContentContainerController
{
    /**
     * @return array action filters
     */
    public function filters()
    {
        return array(
            'accessControl', // perform access control for CRUD operations
        );
    }

    /**
     * Specifies the access control rules.
     * This method is used by the 'accessControl' filter.
     * @return array access control rules
     */
    public function accessRules()
    {
        return array(
            array('allow', // allow authenticated user to perform 'create' and 'update' actions
                'users' => array('@'),
            ),
            array('deny', // deny all users
                'users' => array('*'),
            ),
        );
    }

    /*
     * Show the reputation profile menu
     */
    public function actionShow()
    {
        // handle space visibility changes
        if (isset($_POST['reputationUsers'])) {
            $user = $this->getUser();
            $userSpace = Yii::app()->request->getParam('reputationUsers');

            foreach ($userSpace as $space) {
                if (isset($_POST['reputationUser_' . $space])) {
                    $userSettings = Yii::app()->request->getParam('reputationUser_' . $space);

                    $reputationUser = ReputationUser::model()->findByAttributes(array('space_id' => $space, 'user_id' => $user->id));

                    if ($reputationUser != null) {
                        $reputationUser->visibility = (isset($userSettings['visibility']) && $userSettings['visibility'] == 1) ? 1 : 0;
                        $reputationUser->save();
                    }
                }
            }

            Yii::app()->user->setFlash('data-saved', Yii::t('SpaceModule.controllers_AdminController', 'Saved'));
        }

        $user = $this->getUser();

        $criteria = new CDbCriteria();
        $criteria->condition = 'user_id=:userId';
        $criteria->order = 'space_id ASC';
        $criteria->params = array(':userId' => $user->id);

        $itemCount = ReputationUser::model()->count($criteria);

        $pages = new CPagination($itemCount);
        $pages->pageSize = 10;
        $pages->applyLimit($criteria);

        $reputations = ReputationUser::model()->findAll($criteria);

        $this->render('userReputation', array(
            'user' => $user,
            'reputations' => $reputations,
            'pages' => $pages,
        ));
    }
}