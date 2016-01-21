<?php

/**
 * All user reputation actions a admin can use and see
 *
 * @author Anton Kurnitzky
 */
class AdminReputationController extends ContentContainerController
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
     *
     * @return array access control rules
     */
    public function accessRules()
    {
        return array(
            array('allow',
                'users' => array('@'),
            ),
            array('deny',
                'users' => array('*'),
            ),
        );
    }

    /*
     * Allow only space admins to see configuration
     */
    public function beforeAction($action)
    {
        if (!$this->getSpace()->isAdmin())
            throw new CHttpException(403, 'Access denied - Space Administrator only!');

        return parent::beforeAction($action);
    }

    /*
     * Initialize user reputation overview
     *
     * force update can be triggered by appending &forceUpdate at the end of the url
     * otherwise cache is used
     */
    public function actionShow()
    {
        $forceUpdate = false;
        if (isset($_GET['forceUpdate'])) {
            $forceUpdate = true;
        }

        $space = $this->getSpace();

        ReputationUser::model()->updateUserReputation($space, $forceUpdate);

        $criteria = new CDbCriteria();
        $criteria->condition = 'space_id=:spaceId';
        $criteria->order = 'id ASC';
        $criteria->params = array(':spaceId' => $space->id);

        $itemCount = ReputationUser::model()->count($criteria);

        // use pagination to only show 10 members per page
        $pages = new CPagination($itemCount);
        $pages->pageSize = 10;
        $pages->applyLimit($criteria);

        $reputations = ReputationUser::model()->findAll($criteria);
        $function = SpaceSetting::Get($space->id, 'functions', 'reputation', ReputationUser::DEFAULT_FUNCTION);

        $lastUpdatedBefore = $this->GetLastUpdateTimeInMinutes($criteria);

        $this->render('adminReputation', array(
            'function' => $function,
            'space' => $space,
            'reputations' => $reputations,
            'pages' => $pages,
            'lastUpdatedBefore' => $lastUpdatedBefore,
        ));
    }

    /**
     * Get time in minutes since last update occurred
     *
     * @param $criteria
     * @return string: The time elapsed since the last update
     */
    private function GetLastUpdateTimeInMinutes($criteria)
    {
        $now = new DateTime();
        $lastUpdateTime = new DateTime(ReputationUser::model()->find($criteria)->updated_at);
        $lastUpdatedBefore = $lastUpdateTime->diff($now)->format('%i');

        return $lastUpdatedBefore;
    }

    /**
     * Generate a csv file
     * Column: Space member
     * Row: e-mail address; score
     */
    public function actionExport()
    {
        $space = Yii::app()->getController()->getSpace();
        $function = SpaceSetting::Get($space->id, 'functions', 'reputation', ReputationBase::DEFAULT_FUNCTION);
        if ($function == ReputationBase::LINEAR) {
            $fileName = $this->createCsv($space, $function);
            if (file_exists($fileName)) {
                return Yii::app()->getRequest()->sendFile($fileName, @file_get_contents($fileName));
            }
        } else {
            Yii::import('reputation.forms.*');

            $form = new ExportForm;
            if (isset($_POST['ajax']) && $_POST['ajax'] === 'export-form') {
                echo CActiveForm::validate($form);
                Yii::app()->end();
            }
            if (isset($_POST['ExportForm'])) {
                $_POST['ExportForm'] = Yii::app()->input->stripClean($_POST['ExportForm']);
                $form->attributes = $_POST['ExportForm'];
                if ($form->validate()) {
                    $fileName = $this->createCsv($space, $function, $form->weighting);
                    if (file_exists($fileName)) {
                        return Yii::app()->getRequest()->sendFile($fileName, @file_get_contents($fileName));
                    }
                }
            } else {
                $form->weighting = 700;

                $output = $this->renderPartial('exportCsv', array('model' => $form, 'space' => $space, 'function' => $function));
                Yii::app()->clientScript->render($output);
                echo $output;
                Yii::app()->end();
            }
        }
    }

    /**
     * Generate the csv output file
     *
     * @param $space : The space to create csv from
     * @param $function : The function used
     * @param int $weighting : The weighting provided by the user. Only necessary for logarithmic function.
     * @return string
     */
    private function createCsv($space, $function, $weighting = 1)
    {
        $list = ReputationUser::model()->getSpaceUsersAndScore($space->id);

        $filepath = "./protected/modules/reputation/csv/".'reputation_' . $space->name . '.csv';

        $dirname = dirname($filepath);
        if (!is_dir($dirname))
        {
            mkdir($dirname, 0755, true);
        }

        $fp = fopen($filepath, 'w');

        $header = array(Yii::t('ReputationModule.base', 'E-mail address'), $space->name);

        // write header
        fputcsv($fp, $header, ';');
        // write content
        foreach ($list as $fields) {
            if ($function == ReputationBase::LOGARITHMIC) {
                $reputationScore = $fields['value'] / 100;

                // cap score to 100, because 100% is highest score possible
                if ($reputationScore > 1) {
                    $reputationScore = 1;
                }
                $fields['value'] = intval(round($reputationScore * $weighting));
            }
            fputcsv($fp, $fields, ';');
        }

        fclose($fp);
        return $filepath;
    }

    /**
     * Initialize configuration view
     * Allows the user to set a bunch of parameters for reputation settings inside this space
     *
     * @throws CException
     */
    public function actionConfiguration()
    {
        Yii::import('reputation.forms.*');

        $form = new ConfigureForm;
        $space = Yii::app()->getController()->getSpace();
        $spaceId = $space->id;

        if (isset($_POST['ajax']) && $_POST['ajax'] === 'configure-form') {
            echo CActiveForm::validate($form);
            Yii::app()->end();
        }

        if (isset($_POST['ConfigureForm'])) {
            $_POST['ConfigureForm'] = Yii::app()->input->stripClean($_POST['ConfigureForm']);
            $form->attributes = $_POST['ConfigureForm'];
            if ($form->validate()) {
                $form->functions = SpaceSetting::Set($spaceId, 'functions', $form->functions, 'reputation');
                $form->logarithmBase = SpaceSetting::Set($spaceId, 'logarithm_base', $form->logarithmBase, 'reputation');
                $form->create_content = SpaceSetting::Set($spaceId, 'create_content', $form->create_content, 'reputation');
                $form->smb_likes_content = SpaceSetting::Set($spaceId, 'smb_likes_content', $form->smb_likes_content, 'reputation');
                $form->smb_favorites_content = SpaceSetting::Set($spaceId, 'smb_favorites_content', $form->smb_favorites_content, 'reputation');
                $form->smb_comments_content = SpaceSetting::Set($spaceId, 'smb_comments_content', $form->smb_comments_content, 'reputation');
                $form->daily_limit = SpaceSetting::Set($spaceId, 'daily_limit', $form->daily_limit, 'reputation');
                $form->decrease_weighting = SpaceSetting::Set($spaceId, 'decrease_weighting', $form->decrease_weighting, 'reputation');
                $form->cron_job = SpaceSetting::Set($spaceId, 'cron_job', $form->cron_job, 'reputation');
                $form->lambda_short = SpaceSetting::Set($spaceId, 'lambda_short', $form->lambda_short, 'reputation');
                $form->lambda_long = SpaceSetting::Set($spaceId, 'lambda_long', $form->lambda_long, 'reputation');

                ReputationUser::model()->updateUserReputation($space, true);
                ReputationContent::model()->updateContentReputation($space, true);

                $this->redirect($this->createContainerUrl('//reputation/adminReputation/configuration'));
            }
        } else {
            $form->functions = SpaceSetting::Get($spaceId, 'functions', 'reputation', ReputationBase::DEFAULT_FUNCTION);
            $form->logarithmBase = SpaceSetting::Get($spaceId, 'logarithm_base', 'reputation', ReputationBase::DEFAULT_LOGARITHM_BASE);
            $form->create_content = SpaceSetting::Get($spaceId, 'create_content', 'reputation', ReputationBase::DEFAULT_CREATE_CONTENT);
            $form->smb_likes_content = SpaceSetting::Get($spaceId, 'smb_likes_content', 'reputation', ReputationBase::DEFAULT_SMB_LIKES_CONTENT);
            $form->smb_favorites_content = SpaceSetting::Get($spaceId, 'smb_favorites_content', 'reputation', ReputationBase::DEFAULT_SMB_FAVORITES_CONTENT);
            $form->smb_comments_content = SpaceSetting::Get($spaceId, 'smb_comments_content', 'reputation', ReputationBase::DEFAULT_SMB_COMMENTS_CONTENT);
            $form->daily_limit = SpaceSetting::Get($spaceId, 'daily_limit', 'reputation', ReputationBase::DEFAULT_DAILY_LIMIT);
            $form->decrease_weighting = SpaceSetting::Get($spaceId, 'decrease_weighting', 'reputation', ReputationBase::DEFAULT_DECREASE_WEIGHTING);
            $form->cron_job = SpaceSetting::Get($spaceId, 'cron_job', 'reputation', ReputationBase::DEFAULT_CRON_JOB);
            $form->lambda_short = SpaceSetting::Get($spaceId, 'lambda_short', 'reputation', ReputationBase::DEFAULT_LAMBDA_SHORT);
            $form->lambda_long = SpaceSetting::Get($spaceId, 'lambda_long', 'reputation', ReputationBase::DEFAULT_LAMBDA_LONG);
        }

        $this->render('adminReputationSettings', array('model' => $form, 'space' => $space));
    }
}