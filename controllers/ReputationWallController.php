<?php

/*
 * The wall controller for content reputation
 * Show different sorting options to get a better overview over popular posts
 *
 * @author Anton Kurnitzky
 */

class ReputationWallController extends ContentContainerController
{
    public function init()
    {
        /**
         * Fallback for older versions
         */
        if (Yii::app()->request->getParam('containerClass') == 'Space') {
            $_GET['sguid'] = Yii::app()->request->getParam('containerGuid');
        } elseif (Yii::app()->request->getParam('containerClass') == 'User') {
            $_GET['uguid'] = Yii::app()->request->getParam('containerGuid');
        }

        return parent::init();
    }

    public function actions()
    {
        return array(
            'stream' => array(
                'class' => 'ReputationStreamAction',
                'contentContainer' => $this->contentContainer
            ),
        );
    }

    /**
     * Shows the reputation_content wall
     */
    public function actionShow()
    {
        $forceUpdate = false;
        if (isset($_GET['forceUpdate'])) {
            $forceUpdate = true;
        }

        ReputationContent::model()->updateContentReputation($this->getSpace(), $forceUpdate);
        $this->render('show', array());
    }
}